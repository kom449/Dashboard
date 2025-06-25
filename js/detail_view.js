// js/detail_view.js

// — one‐time flag so we only fetch years once
var _detailYearsLoaded = false;

// — chart variables
var shopPerformanceChart = null;
var salesTrendChart      = null;
var storeComparisonChart = null;

// — helper: restore year/month from sessionStorage
function restoreDetailSelections() {
    var detailYearDropdown  = document.getElementById('detailYearDropdown');
    var detailMonthDropdown = document.getElementById('detailMonthDropdown');
    var storedYear  = sessionStorage.getItem('selectedYear');
    var storedMonth = sessionStorage.getItem('selectedMonth');

    if (detailYearDropdown && storedYear) {
        var optY = detailYearDropdown.querySelector('option[value="' + storedYear + '"]');
        if (optY) detailYearDropdown.value = storedYear;
    }
    if (detailMonthDropdown && storedMonth) {
        var optM = detailMonthDropdown.querySelector('option[value="' + storedMonth + '"]');
        if (optM) detailMonthDropdown.value = storedMonth;
    }
}

// — fetches and populates the year dropdown; returns a Promise
function fetchDetailYears() {
    return fetch('api.php?interval=fetch_years')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var yearDropdown = document.getElementById('detailYearDropdown');
            yearDropdown.innerHTML = '';
            if (!data.length) {
                var fallback = new Date().getFullYear();
                var opt = document.createElement('option');
                opt.value = fallback;
                opt.textContent = fallback;
                yearDropdown.appendChild(opt);
            } else {
                data.forEach(function(year) {
                    var opt = document.createElement('option');
                    opt.value = year;
                    opt.textContent = year;
                    yearDropdown.appendChild(opt);
                });
            }
            restoreDetailSelections();
        })
        .catch(function(err) {
            console.error('Error fetching detail years:', err);
        });
}

// — number formatting helper
function formatNumberDan(num) {
    return new Intl.NumberFormat('da-DK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

// — main entry point, called after script is injected
function loadDetailView(productId) {
    // on first call, load years before doing anything else
    if (!_detailYearsLoaded) {
        _detailYearsLoaded = true;
        return fetchDetailYears().then(function() {
            loadDetailView(productId);
        });
    }

    // now fetch and render the detail for real
    var detailView = document.getElementById('detailView');
    detailView.setAttribute('data-product-id', productId);
    restoreDetailSelections();

    var year  = document.getElementById('detailYearDropdown').value;
    var month = document.getElementById('detailMonthDropdown').value;
    var params = new URLSearchParams({
        interval:   'item_detail',
        product_id: productId,
        year:       year
    });
    if (month !== 'all') params.append('month', month);

    fetch('api.php?' + params.toString())
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.error) {
                console.error('API error:', data.error);
                return alert('Error loading item details: ' + data.error);
            }

            // — info box —
            document.getElementById('boxItemInfo').innerHTML =
                '<h2 style="font-size:20px;">' + data.item.title + '</h2>' +
                '<small style="display:block; font-size:16px; color:#666;">ID: ' + data.item.id + '</small>' +
                '<img src="' + (data.item.image_link || 'img/placeholder.jpg') + '" ' +
                     'alt="' + data.item.title + '" ' +
                     'onerror="this.src=\'img/placeholder.jpg\'">';

            // — sales summary —
            var sales = data.sales || {};
            document.getElementById('boxSalesSummary').innerHTML =
                '<p style="font-size:16px;">Count: ' + (sales.count_sales || 0) + '</p>' +
                '<p style="font-size:18px; color:green;">Sales: ' + formatNumberDan(sales.total_sales || 0) + '</p>' +
                '<p style="font-size:14px; color:orange;">Gross Profit: ' +
                    formatNumberDan((sales.total_sales || 0) * 0.8 - (sales.total_cost || 0)) +
                '</p>';

            // — group variations —
            var groupEl = document.getElementById('boxItemGroup');
            if (data.group && data.group.length) {
                groupEl.innerHTML = data.group.map(function(item) {
                    return (
                        '<div class="group-item" style="margin-bottom:8px;">' +
                        '  <span class="group-item-title" style="display:block; font-size:13px; font-weight:bold;">' +
                              item.title +
                        '  </span>' +
                        '  <small style="display:block; font-size:14px; color:#666;">ID: ' + item.id + '</small>' +
                        '  <span class="group-item-count" style="display:block; font-size:12px; margin-top:4px;">' +
                              'Count: ' + (item.group_count || 0) +
                        '  </span>' +
                        '</div>'
                    );
                }).join('');
            } else {
                groupEl.innerHTML = '<p>No group variations</p>';
            }

            // — render charts —
            renderShopPerformanceChart(data.shopPerformance || []);
            renderSalesTrendChart(data.salesTrend || []);
            renderStoreComparisonChart(data.shopPerformance || []);

            // — toggle views —
            document.getElementById('product-catalog').style.display = 'none';
            detailView.style.display = 'block';
        })
        .catch(function(err) {
            console.error('Error fetching detail data:', err);
        });
}

// — chart renderers —

function renderShopPerformanceChart(data) {
    var agg = {};
    data.forEach(function(d) {
        var id    = d.shop_id;
        var name  = (d.shop_name || '').trim();
        var count = parseInt(d.shop_sales, 10) || 0;
        if (!agg[id]) agg[id] = { name: name, total: 0 };
        agg[id].total += count;
    });
    var items  = Object.values(agg).sort(function(a,b){ return b.total - a.total; });
    var labels = items.map(function(i){ return i.name; });
    var counts = items.map(function(i){ return i.total; });

    if (shopPerformanceChart) shopPerformanceChart.destroy();
    var ctx = document.getElementById('shopPerformanceChart').getContext('2d');
    shopPerformanceChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: counts, backgroundColor: 'rgba(75,192,192,0.5)' }] },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { autoSkip: true, maxTicksLimit: 6 } },
                y: { ticks: { autoSkip: false, font: { size: 11 } } }
            }
        }
    });
}

function renderSalesTrendChart(data) {
    if (salesTrendChart) salesTrendChart.destroy();

    var year  = document.getElementById('detailYearDropdown').value;
    var month = document.getElementById('detailMonthDropdown').value;
    var labels = [], counts = [];

    if (month !== 'all') {
        var days = new Date(year, month, 0).getDate();
        labels = Array.from({ length: days }, function(_, i){ return (i+1).toString(); });
        counts = Array(days).fill(0);
        data.forEach(function(item){
            var d = parseInt(item.period, 10);
            if (d >= 1 && d <= days) counts[d-1] = item.count;
        });
    } else {
        var monthNames = ["January","February","March","April","May","June",
                          "July","August","September","October","November","December"];
        labels = data.map(function(item){
            return monthNames[parseInt(item.period,10)-1] || item.period;
        });
        counts = data.map(function(item){ return item.count; });
    }

    var ctx = document.getElementById('salesTrendChart').getContext('2d');
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

function renderStoreComparisonChart(data) {
    var agg = {};
    data.forEach(function(d){
        var id   = d.shop_id;
        var name = (d.shop_name || '').trim() || ('Shop ' + id);
        var cnt  = parseInt(d.shop_sales,10) || 0;
        if (!agg[id]) agg[id] = { name: name, total: 0 };
        agg[id].total += cnt;
    });
    var items   = Object.values(agg).sort(function(a,b){ return b.total - a.total; });
    var labels  = items.map(function(i){ return i.name; });
    var values  = items.map(function(i){ return i.total; });
    var bgColors = labels.map(function(_,i){
        return 'hsl(' + Math.round(i * 360 / labels.length) + ',70%,50%)';
    });

    if (storeComparisonChart) storeComparisonChart.destroy();
    var ctx = document.getElementById('storeComparisonChart').getContext('2d');
    storeComparisonChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ label: 'Total Sales', data: values, backgroundColor: bgColors }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Store Sales Comparison' }
            },
            scales: {
                x: { beginAtZero: true, ticks: { autoSkip: true, maxTicksLimit: 6 } },
                y: { ticks: { autoSkip: false } }
            }
        }
    });
}

// — event listeners —
document.getElementById('detailYearDropdown').addEventListener('change', function(){
    sessionStorage.setItem('selectedYear', this.value);
    var pid = document.getElementById('detailView').getAttribute('data-product-id');
    if (pid) loadDetailView(pid);
});

document.getElementById('detailMonthDropdown').addEventListener('change', function(){
    sessionStorage.setItem('selectedMonth', this.value);
    var pid = document.getElementById('detailView').getAttribute('data-product-id');
    if (pid) loadDetailView(pid);
});

document.getElementById('backButton').addEventListener('click', function(){
    document.getElementById('detailView').style.display = 'none';
    document.getElementById('product-catalog').style.display = 'block';
});

document.addEventListener('click', function(event){
    var dv = document.getElementById('detailView');
    var pc = document.getElementById('product-catalog');
    if (dv.style.display !== 'none' && !dv.contains(event.target)) {
        dv.style.display = 'none';
        if (pc) pc.style.display = 'block';
    }
});

// expose globally so the injected script can call it
window.loadDetailView = loadDetailView;
