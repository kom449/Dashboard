// 1) helper to compute the fiscal year for any Date (Oct 1–Sep 30 → “name” is the calendar year it ends in)
function getFiscalYear(date) {
  const d = new Date(date);
  // month ≥9 (Oct, Nov, Dec) count toward next calendar year
  return (d.getMonth() >= 9) ? d.getFullYear() + 1 : d.getFullYear();
}

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

// 2) revised groupDataMonthly that keys on fiscal year and labels by fiscal year
function groupDataMonthly(data) {
  // 1) accumulate totals by calendar‐month
  const monthData = {};
  data.forEach(item => {
    const d = parseDate(item.date);
    const m = d.getMonth() + 1;                   // 1–12
    if (!monthData[m]) monthData[m] = { total: 0, margin: 0 };
    monthData[m].total += parseFloat(item.total_sales);
    monthData[m].margin += parseFloat(item.db_this_year || 0);
  });

  // 2) figure out which FY this is (end‐year)
  const fyEnd = getFiscalYear(parseDate(data[0].date));

  // 3) define the fiscal‐year month sequence Oct→Dec, Jan→Sep
  const fiscalMonths = [10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9];

  const labels = [];
  const sales = [];
  const margin = [];

  // 4) walk that sequence, only including months you actually have data for
  fiscalMonths.forEach(m => {
    if (!(m in monthData)) return;
    // a) month name
    const monthName = new Date(0, m - 1)
      .toLocaleString("default", { month: "short" });

    // b) pick the _display_ year: Oct–Dec get fyEnd−1, Jan–Sep get fyEnd
    const displayYear = m >= 10 ? fyEnd - 1 : fyEnd;
    labels.push(`${monthName} ’${String(displayYear).slice(-2)}`);

    // c) pull your sums
    sales.push(monthData[m].total);
    margin.push(monthData[m].margin);
  });

  return {
    labels,
    currentSales: sales,
    currentMargin: margin
  };
}

// 3) helper to bucket raw daily data into Oct-1→Sep-30 fiscal years, FY-to-date
function groupDataYearlyByFYtoDate(data) {
  const groups = {};
  const today = new Date();
  const todayMD = (today.getMonth() + 1) * 100 + today.getDate();

  data.forEach(item => {
    const d = parseDate(item.date);
    const fy = getFiscalYear(d);
    // skip any future days in the *current* FY
    const md = (d.getMonth() + 1) * 100 + d.getDate();
    if (fy === getFiscalYear(today) && md > todayMD) return;

    if (!groups[fy]) groups[fy] = { total_sales: 0, db_this_year: 0 };
    groups[fy].total_sales += parseFloat(item.total_sales);
    groups[fy].db_this_year += parseFloat(item.db_this_year || 0);
  });

  const years = Object.keys(groups).map(Number).sort((a, b) => a - b);
  return {
    labels: years.map(y => `${y - 1}/${y}`),
    sales: years.map(y => groups[y].total_sales),
    margin: years.map(y => groups[y].db_this_year)
  };
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
      // Clear the dropdown without hardcoding the default
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
  // if “All Months” is selected, pull a true fiscal-year range (Oct 1 → Sep 30)
  if (!selectedMonth || selectedMonth === "all") {
    const fyEnd = parseInt(selectedYear, 10);
    const fyStart = fyEnd - 1;
    const startDate = `${fyStart}-10-01`;

    // if we’re still in the current FY, end at today; otherwise end at Sep 30
    const today = new Date();
    const isCurrentFY = fyEnd === getFiscalYear(today);
    const endDate = isCurrentFY
      ? today.toISOString().slice(0, 10)
      : `${fyEnd}-09-30`;

    fetchAndRenderCustomRangeChart(storeId, startDate, endDate);
    return;
  }

  // otherwise fall back to calendar-month comparison as before
  const queryParams = new URLSearchParams({
    interval: "monthly_comparison",
    year: selectedYear,
    store_id: storeId,
  });
  queryParams.append("month", selectedMonth);

  fetch(`https://dashboard.designcykler.dk.linux100.curanetserver.dk/api.php?${queryParams.toString()}`)
    .then(response => response.json())
    .then(data => renderMonthlyChart(data, selectedYear, selectedMonth))
    .catch(err => console.error("Error fetching monthly data:", err));
}

function renderMonthlyChart(data, selectedYear, selectedMonth) {
  const ctx = document.getElementById("monthlyChart").getContext("2d");
  const now = new Date();
  let labels, currentYearSales, currentYearMargin, previousYearSales, previousYearMargin, limit;

  if (selectedMonth && selectedMonth !== "all") {
    // ————— daily branch stays exactly the same —————
    // ... your existing code for specific-month rendering ...
  }
  else {
    // ———— FISCAL YEAR “All Months” OCT→SEP ————
    const fyEnd = parseInt(selectedYear, 10);
    const fyStart = fyEnd - 1;

    // define our 12 fiscal-year slots, each with its calendar month + year it belongs to
    const slots = [
      { m: 10, y: fyStart }, // Oct of previous calendar year
      { m: 11, y: fyStart }, // Nov
      { m: 12, y: fyStart }, // Dec
      { m: 1, y: fyEnd }, // Jan of selected FY
      { m: 2, y: fyEnd },
      { m: 3, y: fyEnd },
      { m: 4, y: fyEnd },
      { m: 5, y: fyEnd },
      { m: 6, y: fyEnd },
      { m: 7, y: fyEnd },
      { m: 8, y: fyEnd },
      { m: 9, y: fyEnd }
    ];

    // build labels “Oct ’24”, “Nov ’24”, … “Sep ’25”
    labels = slots.map(({ m, y }) => {
      const suffix = `’${String(y).slice(-2)}`;
      const monthName = new Date(0, m - 1)
        .toLocaleString("default", { month: "short" });
      return `${monthName} ${suffix}`;
    });

    // zero-fill
    currentYearSales = Array(12).fill(0);
    currentYearMargin = Array(12).fill(0);
    previousYearSales = Array(12).fill(0);
    previousYearMargin = Array(12).fill(0);

    // slot each API row into the correct index
    data.forEach(item => {
      const im = parseInt(item.month, 10);
      const iy = parseInt(item.year, 10);
      const idx = slots.findIndex(s => s.m === im && s.y === iy);
      if (idx < 0) {
        // maybe it's last FY → y-1
        const pidx = slots.findIndex(s => s.m === im && s.y === iy + 1);
        if (pidx >= 0) {
          previousYearSales[pidx] = parseFloat(item.total_sales);
          previousYearMargin[pidx] = parseFloat(item.db_this_year || 0);
        }
      } else {
        // current FY slot
        currentYearSales[idx] = parseFloat(item.total_sales);
        currentYearMargin[idx] = parseFloat(item.db_this_year || 0);
        // ALSO check prior FY for same month:
        previousYearSales[idx] = parseFloat(item.prev_total_sales || 0);  // if your API gives prev year totals
        previousYearMargin[idx] = parseFloat(item.prev_db_this_year || 0);
      }
    });

    // if you don’t get prev_year values from the same payload, use this instead:
    //   data.forEach(item => {
    //     const im = parseInt(item.month, 10), iy = parseInt(item.year, 10);
    //     slots.forEach((s, i) => {
    //       if (im === s.m && iy === s.y) {
    //         currentYearSales[i]  = parseFloat(item.total_sales);
    //         currentYearMargin[i] = parseFloat(item.db_this_year || 0);
    //       }
    //       else if (im === s.m && iy === s.y - 1) {
    //         previousYearSales[i]  = parseFloat(item.total_sales);
    //         previousYearMargin[i] = parseFloat(item.db_this_year || 0);
    //       }
    //     });
    //   });

    // chop off future months if we’re still in the current FY
    if (fyEnd === getFiscalYear(now)) {
      const nowM = now.getMonth() + 1;
      const cutIdx = slots.findIndex(s => s.m === nowM);
      limit = cutIdx + 1;
      labels = labels.slice(0, limit);
      currentYearSales = currentYearSales.slice(0, limit);
      currentYearMargin = currentYearMargin.slice(0, limit);
      previousYearSales = previousYearSales.slice(0, limit);
      previousYearMargin = previousYearMargin.slice(0, limit);
    }
    else {
      limit = labels.length;
    }
  }

  // — build & render Chart.js exactly as before —
  const curYr = parseInt(selectedYear, 10),
    prevYr = curYr - 1;

  const chartData = {
    labels,
    datasets: [
      {
        label: `Sales for ${curYr}`,
        year: curYr,
        data: currentYearSales,
        contributionMargin: currentYearMargin,
        backgroundColor: "rgba(75,192,192,0.7)",
        borderColor: "rgba(75,192,192,1)",
        borderWidth: 2,
        overlayColor: "rgba(55,152,152,0.4)",
      },
      {
        label: `Sales for ${prevYr}`,
        year: prevYr,
        data: previousYearSales,
        contributionMargin: previousYearMargin,
        backgroundColor: "rgba(255,159,64,0.7)",
        borderColor: "rgba(255,159,64,1)",
        borderWidth: 2,
        overlayColor: "rgba(255,140,0,0.4)",
      }
    ],
    selectedMonth,
    limit
  };

  if (chartInstances.monthlyChart) {
    chartInstances.monthlyChart.destroy();
  }
  chartInstances.monthlyChart = new Chart(ctx, {
    type: "bar",
    data: chartData,
    options: {
      layout: { padding: { bottom: 40 } },
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { /* your external tooltip code */ }
      },
      scales: {
        x: { title: { display: true, text: selectedMonth && selectedMonth !== "all" ? "Day" : "Month" } },
        y: { beginAtZero: true }
      }
    },
    plugins: [overlayPlugin]
  });

  generateCustomLegend(chartInstances.monthlyChart);
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
  const start = parseDate(startDate);
  const end = parseDate(endDate);
  const rangeDays = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;

  // 1) split current vs. previous on fiscal year
  const fyStart = getFiscalYear(start);
  const currentData = data.filter(item => getFiscalYear(item.date) === fyStart);
  const previousData = data.filter(item => getFiscalYear(item.date) === fyStart - 1);

  // 2) group by day/week/month
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

  // 3) build your chartData (and carry over limit so legend sums correctly)
  const labels = groupedCurrent.labels;
  const chartData = {
    labels,
    datasets: [
      {
        label: `Sales FY ${fyStart}`,
        year: fyStart,
        data: groupedCurrent.currentSales,
        contributionMargin: groupedCurrent.currentMargin,
        backgroundColor: "rgba(75, 192, 192, 0.7)",
        borderColor: "rgba(75, 192, 192, 1)",
        borderWidth: 2,
        overlayColor: "rgba(55, 152, 152, 0.4)",
        customRange: true
      },
      {
        label: `Sales FY ${fyStart - 1}`,
        year: fyStart - 1,
        data: groupedPrevious.currentSales,
        contributionMargin: groupedPrevious.currentMargin,
        backgroundColor: "rgba(255, 159, 64, 0.7)",
        borderColor: "rgba(255, 159, 64, 1)",
        borderWidth: 2,
        overlayColor: "rgba(255, 140, 0, 0.4)",
        customRange: true
      }
    ],
    // pass this so your legend sums exactly what's shown
    limit: labels.length
  };

  if (chartInstances.monthlyChart) {
    chartInstances.monthlyChart.destroy();
  }

  chartInstances.monthlyChart = new Chart(ctx, {
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

            const dsIndex = tooltip.dataPoints[0].datasetIndex;
            const idx = tooltip.dataPoints[0].dataIndex;
            const label = chartData.labels[idx];
            const year = chartData.datasets[dsIndex].year;
            const currSales = chartData.datasets[0].data[idx] || 0;
            const prevSales = chartData.datasets[1].data[idx] || 0;
            const currMargin = (chartData.datasets[0].contributionMargin || [])[idx] || 0;
            const prevMargin = (chartData.datasets[1].contributionMargin || [])[idx] || 0;

            let html = "";
            if (dsIndex === 0) {
              const salesDiff = prevSales > 0
                ? ((currSales - prevSales) / prevSales * 100).toFixed(2)
                : "N/A";
              const marginDiff = prevMargin > 0
                ? ((currMargin - prevMargin) / prevMargin * 100).toFixed(2)
                : "N/A";
              const salesColor = (salesDiff !== "N/A" && +salesDiff >= 0) ? "green" : "red";
              const marginColor = (marginDiff !== "N/A" && +marginDiff >= 0) ? "green" : "red";

              html = `
                <div class="tooltip-header"><strong>${label} FY ${year}</strong></div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Sales:</span>
                  <span class="tooltip-current">Current: ${currSales.toLocaleString()}</span>
                  <span class="tooltip-previous">Previous: ${prevSales.toLocaleString()}</span>
                  <span class="tooltip-diff" style="color:${salesColor}">(${salesDiff}%)</span>
                </div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Margin:</span>
                  <span class="tooltip-current">Current: ${currMargin.toLocaleString()}</span>
                  <span class="tooltip-previous">Previous: ${prevMargin.toLocaleString()}</span>
                  <span class="tooltip-diff" style="color:${marginColor}">(${marginDiff}%)</span>
                </div>`;
            } else {
              html = `
                <div class="tooltip-header"><strong>${label} FY ${year}</strong></div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Sales:</span>
                  <span class="tooltip-current">${prevSales.toLocaleString()}</span>
                </div>
                <div class="tooltip-section">
                  <span class="tooltip-label">Margin:</span>
                  <span class="tooltip-current">${prevMargin.toLocaleString()}</span>
                </div>`;
            }

            tooltipEl.innerHTML = html;
            const pos = context.chart.canvas.getBoundingClientRect();
            tooltipEl.style.opacity = "1";
            tooltipEl.style.left = `${pos.left + window.pageXOffset + tooltip.caretX}px`;
            tooltipEl.style.top = `${pos.top + window.pageYOffset + tooltip.caretY}px`;
          }
        }
      },
      scales: {
        x: { title: { display: true, text: "Month" } },
        y: { beginAtZero: true }
      }
    },
    plugins: [overlayPlugin]
  });

  generateCustomLegend(chartInstances.monthlyChart);
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
  const params = new URLSearchParams({
    interval: "yearly_summary",
    store_id: storeId
  });
  fetch(`https://dashboard.designcykler.dk.linux100.curanetserver.dk/api.php?${params}`)
    .then(res => res.json())
    .then(data => renderYearlyChart(data))
    .catch(err => console.error("Error fetching yearly summary:", err));
}

function renderYearlyChart(data) {
  const ctx = document.getElementById("yearlyChart").getContext("2d");
  data.sort((a, b) => a.year - b.year);
  const labels = data.map(r => `${r.year - 1}/${r.year}`);
  const sales = data.map(r => parseFloat(r.total_sales));
  const margin = data.map(r => parseFloat(r.db_this_year || 0));

  if (chartInstances.yearlyChart) chartInstances.yearlyChart.destroy();
  chartInstances.yearlyChart = new Chart(ctx, {
    type: "bar",
    data: { labels, datasets: [{ label: "Sales by Fiscal Year", data: sales, contributionMargin: margin, backgroundColor: "rgba(153,102,255,0.7)", borderColor: "rgba(153,102,255,1)", borderWidth: 2 }] },
    options: {
      layout: { padding: { bottom: 40 } },
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: "top" },
        tooltip: {
          enabled: false,
          external(ctx) {
            const tooltip = ctx.tooltip;
            let el = document.getElementById("chartjs-tooltip-yearly");
            if (!el) {
              el = document.createElement("div");
              el.id = "chartjs-tooltip-yearly";
              el.className = "custom-tooltip";
              document.body.appendChild(el);
            }
            if (tooltip.opacity === 0) {
              el.style.opacity = "0";
              return;
            }
            const idx = tooltip.dataPoints[0].dataIndex;
            el.innerHTML = `
              <div class="tooltip-header"><strong>${labels[idx]}</strong></div>
              <div class="tooltip-section"><span class="tooltip-label">Sales:</span><span class="tooltip-current">${sales[idx].toLocaleString()}</span></div>
              <div class="tooltip-section"><span class="tooltip-label">Margin:</span><span class="tooltip-current">${margin[idx].toLocaleString()}</span></div>
            `;
            const pos = ctx.chart.canvas.getBoundingClientRect();
            el.style.opacity = "1";
            el.style.left = pos.left + window.pageXOffset + tooltip.caretX + "px";
            el.style.top = pos.top + window.pageYOffset + tooltip.caretY + "px";
          }
        }
      },
      scales: {
        x: { title: { display: true, text: "Fiscal Year (Oct 1–Sep 30)" } },
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
    document.getElementById("customRange").addEventListener("change", function () {
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
