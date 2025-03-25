document.addEventListener('DOMContentLoaded', function () {
    function fetchDetailYears() {
        fetch('api.php?interval=fetch_years')
            .then(response => response.json())
            .then(data => {
                const yearDropdown = document.getElementById('detailYearDropdown');
                yearDropdown.innerHTML = '';
                if (data.length === 0) {
                    let option = document.createElement('option');
                    option.value = new Date().getFullYear();
                    option.textContent = new Date().getFullYear();
                    yearDropdown.appendChild(option);
                } else {
                    data.forEach(year => {
                        let option = document.createElement('option');
                        option.value = year;
                        option.textContent = year;
                        yearDropdown.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error fetching detail years:', error));
    }

    let shopPerformanceChart = null;
    let salesTrendChart = null;

    function loadDetailView(productId) {
        const detailView = document.getElementById('detailView');
        detailView.setAttribute('data-product-id', productId);

        const year = document.getElementById('detailYearDropdown').value;
        const month = document.getElementById('detailMonthDropdown').value;

        let params = new URLSearchParams();
        params.append('interval', 'item_detail');
        params.append('product_id', productId);
        params.append('year', year);
        if (month !== 'all') {
            params.append('month', month);
        }

        fetch('api.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error("API error: " + data.error);
                    alert("Error loading item details: " + data.error);
                    return;
                }

                // Box 1: Item info
                document.getElementById('boxItemInfo').innerHTML =
                    `<h2>${data.item.title}</h2>
                     <img src="${data.item.image_link || 'img/placeholder.jpg'}" 
                          alt="${data.item.title}" 
                          onerror="this.src='img/placeholder.jpg'">`;

                // Box 2: Sales summary (green for sales, red for cost)
                document.getElementById('boxSalesSummary').innerHTML =
                    `<p style="font-size:16px;">Count: ${data.sales.count_sales || 0}</p>
                     <p style="font-size:18px; color:green;">Sales: ${formatNumberDan(data.sales.total_sales || 0)}</p>
                     <p style="font-size:14px; color:red;">Cost: ${formatNumberDan(data.sales.total_cost || 0)}</p>`;

                // Box 3: Group variations – using group_count
                if (data.group && data.group.length > 0) {
                    document.getElementById('boxItemGroup').innerHTML =
                        data.group.map(item => `
                            <div class="group-item">
                                <span class="group-item-title">${item.title}</span>
                                <span class="group-item-count">${item.group_count ?? 0}</span>
                            </div>
                        `).join('');
                } else {
                    document.getElementById('boxItemGroup').innerHTML = `<p>No group variations</p>`;
                }

                // Box 4: Render shop performance chart
                renderShopPerformanceChart(data.shopPerformance);

                // Sales Trend Chart
                renderSalesTrendChart(data.salesTrend);

                // Hide product catalog, show detail view.
                document.getElementById('product-catalog').style.display = 'none';
                detailView.style.display = 'block';
            })
            .catch(error => console.error('Error fetching detail data:', error));
    }

    function formatNumberDan(num) {
        return new Intl.NumberFormat('da-DK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    }

    function renderShopPerformanceChart(data) {
        // Aggregate by shop_id and sort descending by count.
        const aggregated = {};
        data.forEach(d => {
            const shopId = d.shop_id;
            const shopName = (d.shop_name || '').trim();
            const count = parseInt(d.shop_sales, 10) || 0;
            if (!aggregated[shopId]) {
                aggregated[shopId] = { shopId, shopName, count: 0 };
            }
            aggregated[shopId].count += count;
        });
        const aggregatedArray = Object.values(aggregated).sort((a, b) => b.count - a.count);
        const labels = aggregatedArray.map(x => x.shopName);
        const counts = aggregatedArray.map(x => x.count);

        if (shopPerformanceChart) {
            shopPerformanceChart.destroy();
        }
        const ctx = document.getElementById('shopPerformanceChart').getContext('2d');
        shopPerformanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Count',
                    data: counts,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { autoSkip: true, maxTicksLimit: 6 }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { autoSkip: false, font: { size: 11 } }
                    }
                }
            }
        });
    }

    function renderSalesTrendChart(data) {
        if (salesTrendChart) {
            salesTrendChart.destroy();
        }
        const ctx = document.getElementById('salesTrendChart').getContext('2d');
        salesTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.period),
                datasets: [{
                    label: 'Sales Count',
                    data: data.map(d => d.count),
                    fill: false,
                    borderColor: 'blue'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { autoSkip: true, maxTicksLimit: 6 }
                    },
                    x: {
                        ticks: { autoSkip: true, maxTicksLimit: 12 }
                    }
                }
            }
        });
    }

    document.getElementById('detailYearDropdown').addEventListener('change', function () {
        const productId = document.getElementById('detailView').getAttribute('data-product-id');
        if (productId) loadDetailView(productId);
    });
    document.getElementById('detailMonthDropdown').addEventListener('change', function () {
        const productId = document.getElementById('detailView').getAttribute('data-product-id');
        if (productId) loadDetailView(productId);
    });

    document.getElementById('backButton').addEventListener('click', function () {
        document.getElementById('detailView').style.display = 'none';
        document.getElementById('product-catalog').style.display = 'block';
    });

    window.loadDetailView = loadDetailView;
    fetchDetailYears();
});
