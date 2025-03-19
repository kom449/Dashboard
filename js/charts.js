function parseDate(dateString) {
  return new Date(dateString);
}

function formatDate(date) {
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function groupDataDaily(data) {
  data.sort((a, b) => parseDate(a.date) - parseDate(b.date));
  const labels = data.map(item => formatDate(parseDate(item.date)));
  const sales = data.map(item => parseFloat(item.total_sales));
  const margin = data.map(item => parseFloat(item.db_this_year || 0));
  return { labels, currentSales: sales, currentMargin: margin };
}

function groupDataWeekly(data, startDate, endDate) {
  const start = parseDate(startDate);
  const weekGroups = {};
  data.forEach(item => {
    const d = parseDate(item.date);
    const diffDays = Math.floor((d - start) / (1000 * 60 * 60 * 24));
    const weekNum = Math.floor(diffDays / 7);
    if (!weekGroups[weekNum]) {
      weekGroups[weekNum] = { total_sales: 0, db_this_year: 0, dates: [] };
    }
    weekGroups[weekNum].total_sales += parseFloat(item.total_sales);
    weekGroups[weekNum].db_this_year += parseFloat(item.db_this_year || 0);
    weekGroups[weekNum].dates.push(d);
  });
  const labels = [];
  const sales = [];
  const margin = [];
  Object.keys(weekGroups).sort((a, b) => a - b).forEach(weekNum => {
    const group = weekGroups[weekNum];
    const minDate = new Date(Math.min(...group.dates));
    const maxDate = new Date(Math.max(...group.dates));
    labels.push(formatDate(minDate) + " - " + formatDate(maxDate));
    sales.push(group.total_sales);
    margin.push(group.db_this_year);
  });
  return { labels, currentSales: sales, currentMargin: margin };
}

function groupDataMonthly(data) {
  const monthGroups = {};
  data.forEach(item => {
    const d = parseDate(item.date);
    const key = d.getFullYear() + "-" + (d.getMonth() + 1);
    if (!monthGroups[key]) {
      monthGroups[key] = { total_sales: 0, db_this_year: 0, year: d.getFullYear(), month: d.getMonth() };
    }
    monthGroups[key].total_sales += parseFloat(item.total_sales);
    monthGroups[key].db_this_year += parseFloat(item.db_this_year || 0);
  });
  const keys = Object.keys(monthGroups).sort();
  const labels = keys.map(key => {
    const group = monthGroups[key];
    const d = new Date(group.year, group.month);
    return d.toLocaleString("default", { month: "short", year: "numeric" });
  });
  const sales = keys.map(key => monthGroups[key].total_sales);
  const margin = keys.map(key => monthGroups[key].db_this_year);
  return { labels, currentSales: sales, currentMargin: margin };
}

// ====================
// Overlay Plugin
// ====================

const overlayPlugin = {
  id: 'overlayPlugin',
  afterDatasetsDraw(chart, args, options) {
    const ctx = chart.ctx;
    chart.data.datasets.forEach((dataset, datasetIndex) => {
      if (!dataset.contributionMargin) return;
      const meta = chart.getDatasetMeta(datasetIndex);
      const marginData = dataset.contributionMargin;
      meta.data.forEach((bar, index) => {
        const salesValue = dataset.data[index];
        const marginValue = marginData[index];
        if (salesValue <= 0) return;
        const model = bar.getProps(['x', 'y', 'base', 'width'], true);
        const barHeight = model.base - model.y;
        let fraction = marginValue / salesValue;
        if (fraction > 1) fraction = 1;
        const overlayHeight = barHeight * fraction;
        ctx.save();
        const dsOverlayColor = dataset.overlayColor || options.overlayColor || "rgba(55, 152, 152, 0.4)";
        ctx.fillStyle = dsOverlayColor;
        ctx.fillRect(model.x - model.width / 2, model.base - overlayHeight, model.width, overlayHeight);
        ctx.restore();
      });
    });
  }
};

const chartInstances = {};

// ====================
// Functions for Monthly and Yearly View
// ====================

function fetchStoreList() {
  const dropdown = document.getElementById("storeDropdown");
  fetch("https://dashboard.designcykler.dk.linux100.curanetserver.dk/api.php?fetch_stores=1")
    .then((response) => response.json())
    .then((data) => {
      if (!Array.isArray(data)) {
        console.error("Unexpected store list response:", data);
        return;
      }
      dropdown.innerHTML = '';
      data.forEach((store) => {
        const option = document.createElement("option");
        option.value = store.shop_id;
        option.textContent = store.shop_name;
        dropdown.appendChild(option);
      });
    })
    .catch((error) => {
      console.error("Error fetching store list:", error);
    });
}


function fetchAndRenderMonthlyChart(storeId, selectedYear, selectedMonth) {
  const queryParams = new URLSearchParams({
    interval: "monthly_comparison",
    year: selectedYear,
    store_id: storeId,
  });
  if (selectedMonth && selectedMonth !== "all") {
    queryParams.append("month", selectedMonth);
  }
  fetch(`https://dashboard.designcykler.dk.linux100.curanetserver.dk/api.php?${queryParams.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      renderMonthlyChart(data, selectedYear, selectedMonth);
    })
    .catch((error) => {
      console.error("Error fetching monthly data:", error);
    });
}

function renderMonthlyChart(data, selectedYear, selectedMonth) {
  const ctx = document.getElementById("monthlyChart").getContext("2d");
  let labels = [];
  let currentYearSales, currentYearMargin, previousYearSales, previousYearMargin;
  const now = new Date();
  let limit; // number of days to include in the totals

  if (selectedMonth && selectedMonth !== "all") {
    // Daily (specific month) branch
    data.sort((a, b) => parseInt(a.day) - parseInt(b.day));
    const monthNum = parseInt(selectedMonth);
    // Get full number of days in the month for the selected year
    const daysInMonth = new Date(selectedYear, monthNum, 0).getDate();
    labels = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    currentYearSales = Array(daysInMonth).fill(0);
    currentYearMargin = Array(daysInMonth).fill(0);
    previousYearSales = Array(daysInMonth).fill(0);
    previousYearMargin = Array(daysInMonth).fill(0);
    
    data.forEach((item) => {
      const index = parseInt(item.day) - 1;
      if (parseInt(item.year) === parseInt(selectedYear)) {
        currentYearSales[index] += parseFloat(item.total_sales);
        currentYearMargin[index] += parseFloat(item.db_this_year || 0);
      } else if (parseInt(item.year) === parseInt(selectedYear) - 1) {
        previousYearSales[index] += parseFloat(item.total_sales);
        previousYearMargin[index] += parseFloat(item.db_this_year || 0);
      }
    });
    
    // If the selected month is the current month of the current year, truncate to today’s day.
    if (parseInt(selectedYear) === now.getFullYear() && parseInt(selectedMonth) === (now.getMonth() + 1)) {
      limit = now.getDate();
      labels = labels.slice(0, limit);
      currentYearSales = currentYearSales.slice(0, limit);
      currentYearMargin = currentYearMargin.slice(0, limit);
      previousYearSales = previousYearSales.slice(0, limit);
      previousYearMargin = previousYearMargin.slice(0, limit);
    } else {
      limit = labels.length;
    }
  } else {
    // "All months" branch (unchanged)
    labels = Array.from({ length: 12 }, (_, i) =>
      new Date(0, i).toLocaleString("default", { month: "short" })
    );
    currentYearSales = Array(12).fill(0);
    currentYearMargin = Array(12).fill(0);
    previousYearSales = Array(12).fill(0);
    previousYearMargin = Array(12).fill(0);
    data.forEach((item) => {
      const index = parseInt(item.month) - 1;
      if (parseInt(item.year) === parseInt(selectedYear)) {
        currentYearSales[index] = parseFloat(item.total_sales);
        currentYearMargin[index] = parseFloat(item.db_this_year || 0);
      } else if (parseInt(item.year) === parseInt(selectedYear) - 1) {
        previousYearSales[index] = parseFloat(item.total_sales);
        previousYearMargin[index] = parseFloat(item.db_this_year || 0);
      }
    });
    if (parseInt(selectedYear) === now.getFullYear()) {
      const currentMonthIndex = now.getMonth();
      const daysInCurrentMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
      const fraction = now.getDate() / daysInCurrentMonth;
      currentYearSales[currentMonthIndex] *= fraction;
      currentYearMargin[currentMonthIndex] *= fraction;
      previousYearSales[currentMonthIndex] *= fraction;
      previousYearMargin[currentMonthIndex] *= fraction;
      labels = labels.slice(0, currentMonthIndex + 1);
      currentYearSales = currentYearSales.slice(0, currentMonthIndex + 1);
      currentYearMargin = currentYearMargin.slice(0, currentMonthIndex + 1);
      previousYearSales = previousYearSales.slice(0, currentMonthIndex + 1);
      previousYearMargin = previousYearMargin.slice(0, currentMonthIndex + 1);
      limit = currentMonthIndex + 1;
    } else {
      limit = labels.length;
    }
  }

  const currentYearValue = parseInt(selectedYear);
  const previousYearValue = currentYearValue - 1;

  // Build chart data and store the selectedMonth and limit for the legend
  const chartData = {
    labels: labels,
    datasets: [
      {
        label: `Sales for ${selectedYear}`,
        year: currentYearValue,
        data: currentYearSales,
        backgroundColor: "rgba(75, 192, 192, 0.7)",
        borderColor: "rgba(75, 192, 192, 1)",
        borderWidth: 2,
        contributionMargin: currentYearMargin,
        overlayColor: "rgba(55, 152, 152, 0.4)"
      },
      {
        label: `Sales for ${previousYearValue}`,
        year: previousYearValue,
        data: previousYearSales,
        backgroundColor: "rgba(255, 159, 64, 0.7)",
        borderColor: "rgba(255, 159, 64, 1)",
        borderWidth: 2,
        contributionMargin: previousYearMargin,
        overlayColor: "rgba(255, 140, 0, 0.4)"
      }
    ],
    selectedMonth: selectedMonth,
    limit: limit
  };

  if (chartInstances["monthlyChart"]) {
    chartInstances["monthlyChart"].destroy();
  }

  chartInstances["monthlyChart"] = new Chart(ctx, {
    type: "bar",
    data: chartData,
    options: {
      layout: { padding: { bottom: 40 } },
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          enabled: false,
          external: function (context) {
            const tooltip = context.tooltip;
            let tooltipEl = document.getElementById("chartjs-tooltip");
            if (!tooltipEl) {
              tooltipEl = document.createElement("div");
              tooltipEl.id = "chartjs-tooltip";
              tooltipEl.className = "custom-tooltip";
              document.body.appendChild(tooltipEl);
            }
            if (tooltip.opacity === 0) {
              tooltipEl.style.opacity = "0";
              return;
            }
            const hoveredDatasetIndex = tooltip.dataPoints[0].datasetIndex;
            const index = tooltip.dataPoints[0].dataIndex;
            const label = chartData.labels[index];
            const datasetYear = chartData.datasets[hoveredDatasetIndex].year;
            const currentSalesVal = chartData.datasets[0].data[index] || 0;
            const previousSalesVal = chartData.datasets[1].data[index] || 0;
            const currentMarginVal = chartData.datasets[0].contributionMargin
              ? chartData.datasets[0].contributionMargin[index] || 0
              : 0;
            const previousMarginVal = chartData.datasets[1].contributionMargin
              ? chartData.datasets[1].contributionMargin[index] || 0
              : 0;
            let html = "";
            if (hoveredDatasetIndex === 0) {
              let salesDiff = 'N/A';
              if (previousSalesVal > 0) {
                salesDiff = (((currentSalesVal - previousSalesVal) / previousSalesVal) * 100).toFixed(2);
              }
              let marginDiff = 'N/A';
              if (previousMarginVal > 0) {
                marginDiff = (((currentMarginVal - previousMarginVal) / previousMarginVal) * 100).toFixed(2);
              }
              const salesColor = (salesDiff !== 'N/A' && salesDiff >= 0) ? "green" : "red";
              const marginColor = (marginDiff !== 'N/A' && marginDiff >= 0) ? "green" : "red";
              html += 
                `<div class="tooltip-header"><strong>${label} ${datasetYear}</strong></div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Sales:</span>
                  <span class="tooltip-current">Current: ${currentSalesVal.toLocaleString()}</span>
                  <span class="tooltip-previous">Previous: ${previousSalesVal.toLocaleString()}</span>
                  <span class="tooltip-diff" style="color: ${salesColor};">(${salesDiff}%)</span>
                </div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Margin:</span>
                  <span class="tooltip-current">Current: ${currentMarginVal.toLocaleString()}</span>
                  <span class="tooltip-previous">Previous: ${previousMarginVal.toLocaleString()}</span>
                  <span class="tooltip-diff" style="color: ${marginColor};">(${marginDiff}%)</span>
                </div>`;
            } else if (hoveredDatasetIndex === 1) {
              html += 
                `<div class="tooltip-header"><strong>${label} ${datasetYear}</strong></div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Sales:</span>
                  <span class="tooltip-current">${previousSalesVal.toLocaleString()}</span>
                </div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Margin:</span>
                  <span class="tooltip-current">${previousMarginVal.toLocaleString()}</span>
                </div>`;
            }
            tooltipEl.innerHTML = html;
            const position = context.chart.canvas.getBoundingClientRect();
            tooltipEl.style.opacity = "1";
            tooltipEl.style.left = position.left + window.pageXOffset + tooltip.caretX + "px";
            tooltipEl.style.top = position.top + window.pageYOffset + tooltip.caretY + "px";
          }
        }
      },
      scales: {
        x: { title: { display: true, text: selectedMonth && selectedMonth !== "all" ? "Day" : "Month" } },
        y: { beginAtZero: true }
      }
    },
    plugins: [overlayPlugin]
  });

  generateCustomLegend(chartInstances["monthlyChart"]);
}

// ====================
// Custom Range Functions
// ====================

function fetchAndRenderCustomRangeChart(storeId, startDate, endDate) {
  const queryParams = new URLSearchParams({
    interval: "custom_range",
    store_id: storeId,
    start: startDate,
    end: endDate
  });
  fetch(`https://dashboard.designcykler.dk.linux100.curanetserver.dk/api.php?${queryParams.toString()}`)
    .then(response => response.json())
    .then(data => {
      renderCustomRangeChart(data, startDate, endDate);
    })
    .catch(error => {
      console.error("Error fetching custom range data:", error);
    });
}

function renderCustomRangeChart(data, startDate, endDate) {
  const ctx = document.getElementById("monthlyChart").getContext("2d");
  const start = new Date(startDate);
  const end = new Date(endDate);
  const rangeDays = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;
  const currentData = data.filter(item => parseDate(item.date).getFullYear() === start.getFullYear());
  const previousData = data.filter(item => parseDate(item.date).getFullYear() === (start.getFullYear() - 1));

  let groupedCurrent, groupedPrevious;
  if (rangeDays <= 31) {
    groupedCurrent = groupDataDaily(currentData);
    groupedPrevious = groupDataDaily(previousData);
  } else if (rangeDays <= 224) {
    groupedCurrent = groupDataWeekly(currentData, startDate, endDate);
    groupedPrevious = groupDataWeekly(previousData, startDate, endDate);
  } else {
    groupedCurrent = groupDataMonthly(currentData);
    groupedPrevious = groupDataMonthly(previousData);
  }
  const labels = groupedCurrent.labels;

  if (chartInstances["monthlyChart"]) {
    chartInstances["monthlyChart"].destroy();
  }

  const currentYear = start.getFullYear();
  const previousYear = currentYear - 1;

  const chartData = {
    labels: labels,
    datasets: [
      {
        label: `Current (${startDate} to ${endDate})`,
        year: currentYear,
        data: groupedCurrent.currentSales,
        backgroundColor: "rgba(75, 192, 192, 0.7)",
        borderColor: "rgba(75, 192, 192, 1)",
        borderWidth: 2,
        contributionMargin: groupedCurrent.currentMargin,
        overlayColor: "rgba(55, 152, 152, 0.4)",
        customRange: true
      },
      {
        label: `Previous (${previousYear})`,
        year: previousYear,
        data: groupedPrevious.currentSales,
        backgroundColor: "rgba(255, 159, 64, 0.7)",
        borderColor: "rgba(255, 159, 64, 1)",
        borderWidth: 2,
        contributionMargin: groupedPrevious.currentMargin,
        overlayColor: "rgba(255, 140, 0, 0.4)",
        customRange: true
      }
    ]
  };

  chartInstances["monthlyChart"] = new Chart(ctx, {
    type: "bar",
    data: chartData,
    options: {
      layout: { padding: { bottom: 40 } },
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          enabled: false,
          external: function (context) {
            const tooltip = context.tooltip;
            let tooltipEl = document.getElementById("chartjs-tooltip");
            if (!tooltipEl) {
              tooltipEl = document.createElement("div");
              tooltipEl.id = "chartjs-tooltip";
              tooltipEl.className = "custom-tooltip";
              document.body.appendChild(tooltipEl);
            }
            if (tooltip.opacity === 0) {
              tooltipEl.style.opacity = "0";
              return;
            }
            const hoveredDatasetIndex = tooltip.dataPoints[0].datasetIndex;
            const index = tooltip.dataPoints[0].dataIndex;
            const label = chartData.labels[index];
            const datasetYear = chartData.datasets[hoveredDatasetIndex].year;
            const currentSalesVal = chartData.datasets[0].data[index] || 0;
            const previousSalesVal = chartData.datasets[1].data[index] || 0;
            const currentMarginVal = chartData.datasets[0].contributionMargin
              ? chartData.datasets[0].contributionMargin[index] || 0
              : 0;
            const previousMarginVal = chartData.datasets[1].contributionMargin
              ? chartData.datasets[1].contributionMargin[index] || 0
              : 0;
            let html = "";
            if (hoveredDatasetIndex === 0) {
              let salesDiff = 'N/A';
              if (previousSalesVal > 0) {
                salesDiff = (((currentSalesVal - previousSalesVal) / previousSalesVal) * 100).toFixed(2);
              }
              let marginDiff = 'N/A';
              if (previousMarginVal > 0) {
                marginDiff = (((currentMarginVal - previousMarginVal) / previousMarginVal) * 100).toFixed(2);
              }
              const salesColor = (salesDiff !== 'N/A' && salesDiff >= 0) ? "green" : "red";
              const marginColor = (marginDiff !== 'N/A' && marginDiff >= 0) ? "green" : "red";
              html += `
                <div class="tooltip-header"><strong>${label} ${datasetYear}</strong></div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Sales:</span>
                  <span class="tooltip-current">Current: ${currentSalesVal.toLocaleString()}</span>
                  <span class="tooltip-previous">Previous: ${previousSalesVal.toLocaleString()}</span>
                  <span class="tooltip-diff" style="color: ${salesColor};">(${salesDiff}%)</span>
                </div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Margin:</span>
                  <span class="tooltip-current">Current: ${currentMarginVal.toLocaleString()}</span>
                  <span class="tooltip-previous">Previous: ${previousMarginVal.toLocaleString()}</span>
                  <span class="tooltip-diff" style="color: ${marginColor};">(${marginDiff}%)</span>
                </div>
              `;
            } else if (hoveredDatasetIndex === 1) {
              html += `
                <div class="tooltip-header"><strong>${label} ${datasetYear}</strong></div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Sales:</span>
                  <span class="tooltip-current">${previousSalesVal.toLocaleString()}</span>
                </div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Margin:</span>
                  <span class="tooltip-current">${previousMarginVal.toLocaleString()}</span>
                </div>
              `;
            }
            tooltipEl.innerHTML = html;
            const position = context.chart.canvas.getBoundingClientRect();
            tooltipEl.style.opacity = "1";
            tooltipEl.style.left = position.left + window.pageXOffset + tooltip.caretX + "px";
            tooltipEl.style.top = position.top + window.pageYOffset + tooltip.caretY + "px";
          }
        }
      },
      scales: {
        x: { title: { display: true, text: (rangeDays <= 31) ? "Day" : (rangeDays <= 224) ? "Week" : "Month" } },
        y: { beginAtZero: true }
      }
    },
    plugins: [overlayPlugin]
  });

  generateCustomLegend(chartInstances["monthlyChart"]);
}

// ====================
// Static Custom Legend Generator
// ====================

function generateCustomLegend(chart) {
  const legendContainer = document.getElementById("customLegend");
  if (!legendContainer) return;
  legendContainer.innerHTML = "";
  
  // Use the precomputed limit from the chart data
  const limit = chart.data.limit || chart.data.labels.length;
  
  chart.data.datasets.forEach((dataset, datasetIndex) => {
    const dataSlice = dataset.data.slice(0, limit);
    const totalSales = dataSlice.reduce((a, b) => a + b, 0);
    const totalMargin = dataset.contributionMargin ? dataset.contributionMargin.slice(0, limit).reduce((a, b) => a + b, 0) : 0;
    let legendHTML = `<span class="legend-color-box" style="background-color: ${dataset.backgroundColor};"></span>
      <span class="legend-label">${dataset.label}</span>`;
    
    if (datasetIndex === 0 && chart.data.datasets.length > 1) {
      const previousSales = chart.data.datasets[1].data.slice(0, limit).reduce((a, b) => a + b, 0);
      let salesDiff = 'N/A';
      if (previousSales > 0) {
        salesDiff = (((totalSales - previousSales) / previousSales) * 100).toFixed(2);
      }
      const previousMargin = dataset.contributionMargin && chart.data.datasets[1].contributionMargin 
                             ? chart.data.datasets[1].contributionMargin.slice(0, limit).reduce((a, b) => a + b, 0)
                             : 0;
      let marginDiff = 'N/A';
      if (previousMargin > 0) {
        marginDiff = (((totalMargin - previousMargin) / previousMargin) * 100).toFixed(2);
      }
      const salesColor = (salesDiff !== 'N/A' && salesDiff >= 0) ? "green" : "red";
      const marginColor = (marginDiff !== 'N/A' && marginDiff >= 0) ? "green" : "red";
      legendHTML += `<div class="legend-info">
        <span>Sales: ${totalSales.toLocaleString()} <span class="legend-diff" style="color: ${salesColor};">(${salesDiff}%)</span></span><br>
        <span>Margin: ${totalMargin.toLocaleString()} <span class="legend-diff" style="color: ${marginColor};">(${marginDiff}%)</span></span>
      </div>`;
    } else {
      legendHTML += `<div class="legend-info">
        <span>Sales: ${totalSales.toLocaleString()}</span><br>
        <span>Margin: ${totalMargin.toLocaleString()}</span>
      </div>`;
    }
    const legendItem = document.createElement("div");
    legendItem.className = "legend-item";
    legendItem.innerHTML = legendHTML;
    legendContainer.appendChild(legendItem);
  });
}


// ====================
// Yearly Chart Functions
// ====================

function fetchAndRenderYearlyChart(storeId) {
  const queryParams = new URLSearchParams({
    interval: "yearly_summary",
    store_id: storeId,
  });
  fetch(`https://dashboard.designcykler.dk.linux100.curanetserver.dk/api.php?${queryParams.toString()}`)
    .then((response) => response.json())
    .then((data) => {
      renderYearlyChart(data);
    })
    .catch((error) => {
      console.error("Error fetching yearly data:", error);
    });
}

function renderYearlyChart(data) {
  const ctx = document.getElementById("yearlyChart").getContext("2d");
  data.sort((a, b) => a.year - b.year);
  const labels = data.map((item) => item.year);
  const yearlySales = data.map((item) => parseFloat(item.total_sales));
  const yearlyMargin = data.map((item) => parseFloat(item.db_this_year || 0));
  if (chartInstances["yearlyChart"]) {
    chartInstances["yearlyChart"].destroy();
  }
  const chartData = {
    labels: labels,
    datasets: [
      {
        label: "Yearly Sales",
        data: yearlySales,
        backgroundColor: "rgba(153, 102, 255, 0.7)",
        borderColor: "rgba(153, 102, 255, 1)",
        borderWidth: 2,
        contributionMargin: yearlyMargin
      }
    ]
  };
  chartInstances["yearlyChart"] = new Chart(ctx, {
    type: "bar",
    data: chartData,
    options: {
      layout: { padding: { bottom: 40 } },
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: "top" },
        tooltip: {
          enabled: false,
          external: function (context) {
            const tooltip = context.tooltip;
            let tooltipEl = document.getElementById("chartjs-tooltip-yearly");
            if (!tooltipEl) {
              tooltipEl = document.createElement("div");
              tooltipEl.id = "chartjs-tooltip-yearly";
              tooltipEl.className = "custom-tooltip";
              document.body.appendChild(tooltipEl);
            }
            if (tooltip.opacity === 0) {
              tooltipEl.style.opacity = "0";
              return;
            }
            const index = tooltip.dataPoints[0].dataIndex;
            const salesVal = yearlySales[index] || 0;
            const marginVal = yearlyMargin[index] || 0;
            tooltipEl.innerHTML = `
              <div class="tooltip-header"><strong>${labels[index]}</strong></div>
              <div class="tooltip-section">
                <span class="tooltip-label">Sales:</span>
                <span class="tooltip-current">${salesVal.toLocaleString()}</span>
              </div>
              <div class="tooltip-section">
                <span class="tooltip-label">Margin:</span>
                <span class="tooltip-current">${marginVal.toLocaleString()}</span>
              </div>
            `;
            const position = context.chart.canvas.getBoundingClientRect();
            tooltipEl.style.opacity = "1";
            tooltipEl.style.left = position.left + window.pageXOffset + tooltip.caretX + "px";
            tooltipEl.style.top = position.top + window.pageYOffset + tooltip.caretY + "px";
          }
        }
      },
      scales: {
        x: { title: { display: true, text: "Year" } },
        y: { beginAtZero: true }
      }
    },
    plugins: [overlayPlugin]
  });
}

// ====================
// Handle Input Change
// ====================

function handleInputChange() {
  const storeDropdown = document.getElementById("storeDropdown");
  const yearDropdown = document.getElementById("yearDropdown");
  const monthDropdown = document.getElementById("monthDropdown");
  const customRangeCheckbox = document.getElementById("customRange");
  const selectedStoreId = storeDropdown.value;
  
  if (customRangeCheckbox && customRangeCheckbox.checked) {
    const startDate = document.getElementById("startDate").value;
    const endDate = document.getElementById("endDate").value;
    if (startDate && endDate) {
      fetchAndRenderCustomRangeChart(selectedStoreId, startDate, endDate);
    }
  } else {
    const selectedYear = yearDropdown.value || new Date().getFullYear();
    const selectedMonth = monthDropdown ? monthDropdown.value : "all";
    fetchAndRenderMonthlyChart(selectedStoreId, selectedYear, selectedMonth);
  }
  fetchAndRenderYearlyChart(selectedStoreId);
}

document.addEventListener("DOMContentLoaded", () => {
  fetchStoreList();
  document.getElementById("storeDropdown").addEventListener("change", handleInputChange);
  document.getElementById("yearDropdown").addEventListener("change", handleInputChange);
  if (document.getElementById("monthDropdown")) {
    document.getElementById("monthDropdown").addEventListener("change", handleInputChange);
  }
  if (document.getElementById("customRange")) {
    document.getElementById("customRange").addEventListener("change", function() {
      const yearDropdown = document.getElementById("yearDropdown");
      const monthDropdown = document.getElementById("monthDropdown");
      const customRangeContainer = document.getElementById("customRangeContainer");
      if (this.checked) {
        yearDropdown.disabled = true;
        monthDropdown.disabled = true;
        customRangeContainer.style.display = "block";
      } else {
        yearDropdown.disabled = false;
        monthDropdown.disabled = false;
        customRangeContainer.style.display = "none";
      }
      handleInputChange();
    });
  }
  if (document.getElementById("startDate")) {
    document.getElementById("startDate").addEventListener("change", handleInputChange);
  }
  if (document.getElementById("endDate")) {
    document.getElementById("endDate").addEventListener("change", handleInputChange);
  }
  handleInputChange();
});
