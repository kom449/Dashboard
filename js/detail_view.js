document.addEventListener('DOMContentLoaded', function () {
    function restoreDetailSelections() {
        const detailYearDropdown = document.getElementById('detailYearDropdown');
        const detailMonthDropdown = document.getElementById('detailMonthDropdown');
        const storedYear = sessionStorage.getItem('selectedYear');
        const storedMonth = sessionStorage.getItem('selectedMonth');
        console.log('Restoring selections: Year =', storedYear, 'Month =', storedMonth);
        if (detailYearDropdown && storedYear) {
            if (detailYearDropdown.querySelector(`option[value="${storedYear}"]`)) {
                detailYearDropdown.value = storedYear;
                console.log('Detail Year dropdown set to:', detailYearDropdown.value);
            }
        }
        if (detailMonthDropdown && storedMonth) {
            if (detailMonthDropdown.querySelector(`option[value="${storedMonth}"]`)) {
                detailMonthDropdown.value = storedMonth;
                console.log('Detail Month dropdown set to:', detailMonthDropdown.value);
            }
        }
    }

    function fetchDetailYears() {
        fetch('api.php?interval=fetch_years')
            .then(response => response.json())
            .then(data => {
                const yearDropdown = document.getElementById('detailYearDropdown');
                yearDropdown.innerHTML = '';
                if (data.length === 0) {
                    let option = document.createElement('option');
                    option.value = String(new Date().getFullYear());
                    option.textContent = new Date().getFullYear();
                    yearDropdown.appendChild(option);
                } else {
                    data.forEach(year => {
                        let option = document.createElement('option');
                        option.value = String(year);
                        option.textContent = year;
                        yearDropdown.appendChild(option);
                    });
                }
                restoreDetailSelections();
            })
            .catch(error => console.error('Error fetching detail years:', error));
    }

    let shopPerformanceChart = null;
    let salesTrendChart = null;

    function loadDetailView(productId) {
        const detailView = document.getElementById('detailView');
        detailView.setAttribute('data-product-id', productId);
        restoreDetailSelections();

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

                document.getElementById('boxItemInfo').innerHTML =
                    `<h2 style="font-size:20px;">${data.item.title}</h2>
                    <small style="display:block; font-size:16px; color:#666;">ID: ${data.item.id}</small>
                     <img src="${data.item.image_link || 'img/placeholder.jpg'}" 
                      alt="${data.item.title}" 
                      onerror="this.src='img/placeholder.jpg'">`;

                document.getElementById('boxSalesSummary').innerHTML =
                    `<p style="font-size:16px;">Count: ${data.sales.count_sales || 0}</p>
                    <p style="font-size:18px; color:green;">Sales: ${formatNumberDan(data.sales.total_sales || 0)}</p>
                    <p style="font-size:14px; color:orange;">Gross Profit: ${formatNumberDan((data.sales.total_sales || 0) * 0.8 - (data.sales.total_cost || 0))}</p>`;

                if (data.group && data.group.length > 0) {
                    document.getElementById('boxItemGroup').innerHTML =
                        data.group.map(item => `
                            <div class="group-item" style="margin-bottom:8px;">
                                <span class="group-item-title" style="display:block; font-size:13px; font-weight:bold;">${item.title}</span>
                                <small style="display:block; font-size:14px; color:#666;">ID: ${item.id}</small>
                                <span class="group-item-count" style="display:block; font-size:12px; margin-top:4px;">Count: ${item.group_count ?? 0}</span>
                            </div>
                        `).join('');
                } else {
                    document.getElementById('boxItemGroup').innerHTML = `<p>No group variations</p>`;
                }



                renderShopPerformanceChart(data.shopPerformance);
                renderSalesTrendChart(data.salesTrend);

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
                    data: counts,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                layout: { padding: { top: 0, bottom: 0 } },
                scales: {
                    x: { beginAtZero: true, ticks: { autoSkip: true, maxTicksLimit: 6 } },
                    y: { beginAtZero: true, ticks: { autoSkip: false, font: { size: 11 } } }
                }
            }
        });
    }

    function renderSalesTrendChart(data) {
        if (salesTrendChart) {
            salesTrendChart.destroy();
        }

        const detailYearDropdown = document.getElementById('detailYearDropdown');
        const detailMonthDropdown = document.getElementById('detailMonthDropdown');
        const selectedYear = detailYearDropdown ? detailYearDropdown.value : new Date().getFullYear();
        const selectedMonth = detailMonthDropdown ? detailMonthDropdown.value : 'all';

        let labels = [];
        let counts = [];

        if (selectedMonth !== 'all') {
            const numDays = new Date(selectedYear, selectedMonth, 0).getDate();
            labels = Array.from({ length: numDays }, (_, i) => (i + 1).toString());
            counts = Array(numDays).fill(0);
            data.forEach(item => {
                const day = parseInt(item.period, 10);
                if (day >= 1 && day <= numDays) {
                    counts[day - 1] = item.count;
                }
            });
        } else {
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            labels = data.map(item => {
                const m = parseInt(item.period, 10);
                return monthNames[m - 1] || item.period;
            });
            counts = data.map(item => item.count);
        }

        const ctx = document.getElementById('salesTrendChart').getContext('2d');
        salesTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales Count',
                    data: counts,
                    fill: false,
                    borderColor: 'blue'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { autoSkip: false } },
                    x: { ticks: { autoSkip: false } }
                }
            }
        });
    }

    document.getElementById('detailYearDropdown').addEventListener('change', function () {
        console.log('Detail Year changed to:', this.value);
        const productId = document.getElementById('detailView').getAttribute('data-product-id');
        sessionStorage.setItem('selectedYear', this.value);
        if (productId) loadDetailView(productId);
    });
    document.getElementById('detailMonthDropdown').addEventListener('change', function () {
        console.log('Detail Month changed to:', this.value);
        const productId = document.getElementById('detailView').getAttribute('data-product-id');
        sessionStorage.setItem('selectedMonth', this.value);
        if (productId) loadDetailView(productId);
    });

    document.getElementById('backButton').addEventListener('click', function () {
        document.getElementById('detailView').style.display = 'none';
        document.getElementById('product-catalog').style.display = 'block';
    });

    // Add a global click listener to detect clicks outside the detail view
    document.addEventListener('click', function (event) {
        const detailView = document.getElementById('detailView');
        const productCatalog = document.getElementById('product-catalog');
        // Check if detail view is visible and the click target is not inside it.
        if (detailView && detailView.style.display !== 'none' && !detailView.contains(event.target)) {
            console.log('Click outside detail view detected. Hiding detail view.');
            detailView.style.display = 'none';
            if (productCatalog) {
                productCatalog.style.display = 'block';
            }
        }
    });

    window.loadDetailView = loadDetailView;
    fetchDetailYears();
});
