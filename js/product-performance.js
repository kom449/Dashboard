document.addEventListener('DOMContentLoaded', function() {
    var storeSelect = document.getElementById('storeSelect');  
    var performanceForm = document.getElementById('performanceSearchForm');   
    var storeChartCanvas = document.getElementById('storePerformanceChart');
    var topItemsTableBody = document.querySelector('#topItemsTable tbody');

    function loadStoreOptions() {
        fetch('api.php?fetch_stores=1')
            .then(response => response.json())
            .then(data => {
                var storeSelect = document.getElementById('storeSelect');
                if (!storeSelect) return;
                storeSelect.innerHTML = '';
                var defaultOption = document.createElement('option');
                defaultOption.value = "all";
                defaultOption.textContent = "All Stores";
                defaultOption.selected = true;
                storeSelect.appendChild(defaultOption);
                data.forEach(store => {
                    if (store.shop_id === "excludeOnline") return;
                    var option = document.createElement('option');
                    option.value = store.shop_id;
                    option.textContent = store.shop_name;
                    storeSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading store options:', error));
    }
    

    function fetchStorePerformanceData() {
        fetch('api.php?interval=store_performance')
            .then(response => response.json())
            .then(data => {
                if (!storeChartCanvas) return;
                var ctx = storeChartCanvas.getContext('2d');
                var labels = data.map(item => item.shop_name);
                var quantities = data.map(item => parseInt(item.total_quantity, 10));
                if (window.storePerformanceChart instanceof Chart) {
                    window.storePerformanceChart.destroy();
                }
                window.storePerformanceChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: quantities,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.2)',
                                'rgba(54, 162, 235, 0.2)',
                                'rgba(255, 206, 86, 0.2)',
                                'rgba(75, 192, 192, 0.2)',
                                'rgba(153, 102, 255, 0.2)',
                                'rgba(255, 159, 64, 0.2)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(255, 159, 64, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Disable the default aspect ratio
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
                
            })
            .catch(error => console.error('Error fetching store performance data:', error));
    }

    function fetchTopItemsData() {
        if (!storeSelect) return;
        var storeId = storeSelect.value;
        var apiUrl = 'api.php?interval=top_items';
        if (storeId && storeId !== 'all') {
            apiUrl += '&store_id=' + encodeURIComponent(storeId);
        }
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                var tbody = document.querySelector('#topItemsTable tbody');
                if (!tbody) {
                    console.error("Table body for 'topItemsTable' not found.");
                    return;
                }
                tbody.innerHTML = '';
                data.forEach(item => {
                    var tr = document.createElement('tr');
                    var tdTitle = document.createElement('td');
                    tdTitle.textContent = item.title ? item.title : ('Group ' + item.product_group);
                    var tdQuantity = document.createElement('td');
                    tdQuantity.textContent = item.total_quantity;
                    tr.appendChild(tdTitle);
                    tr.appendChild(tdQuantity);
                    tbody.appendChild(tr);
                });
            })
            .catch(error => console.error('Error fetching top items data:', error));
    }

    if (performanceForm) {
        performanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            fetchTopItemsData();
        });
    }

    if (storeSelect) {
        storeSelect.addEventListener('change', function() {
            fetchTopItemsData();
        });
    }

    var performanceTabLink = document.querySelector('a[href="#customer-orders"]');
    if (performanceTabLink) {
        performanceTabLink.addEventListener('click', function() {
            setTimeout(function() {
                loadStoreOptions();
                fetchStorePerformanceData();
                fetchTopItemsData();
            }, 200);
        });
    }
});
