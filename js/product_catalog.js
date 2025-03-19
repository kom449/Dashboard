document.addEventListener('DOMContentLoaded', function () {
    function fetchStores() {
        fetch('api.php?fetch_stores=1')
            .then(response => response.json())
            .then(data => {
                const storeDropdown = document.getElementById('storeDropdownCatalog');
                storeDropdown.innerHTML = '';
                data.forEach(store => {
                    let option = document.createElement('option');
                    option.value = store.shop_id;
                    option.textContent = store.shop_name;
                    storeDropdown.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching stores:', error));
    }

    function fetchCategories() {
        fetch('api.php?interval=fetch_categories')
            .then(response => response.json())
            .then(data => {
                const categoryDropdown = document.getElementById('categoryDropdown');
                categoryDropdown.innerHTML = '';
                let defaultOption = document.createElement('option');
                defaultOption.value = 'all';
                defaultOption.textContent = 'All Categories';
                categoryDropdown.appendChild(defaultOption);
                data.forEach(category => {
                    let option = document.createElement('option');
                    option.value = category.identifier; // use identifier for filtering
                    option.textContent = category.display_name;
                    categoryDropdown.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching categories:', error));
    }

    function fetchBrands() {
        fetch('api.php?interval=fetch_brands')
            .then(response => response.json())
            .then(data => {
                const brandDropdown = document.getElementById('brandDropdown');
                brandDropdown.innerHTML = '';
                let defaultOption = document.createElement('option');
                defaultOption.value = 'all';
                defaultOption.textContent = 'All Brands';
                brandDropdown.appendChild(defaultOption);
                data.forEach(item => {
                    let option = document.createElement('option');
                    option.value = item.brand;
                    option.textContent = item.brand;
                    brandDropdown.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching brands:', error));
    }

    // New function to fetch and populate dynamic years
    function fetchYears() {
        fetch('api.php?interval=fetch_years')
            .then(response => response.json())
            .then(data => {
                const yearDropdown = document.getElementById('yearDropdownCatalog');
                yearDropdown.innerHTML = '';
                // Optionally, add a default option here if desired:
                // let defaultOption = document.createElement('option');
                // defaultOption.value = 'all';
                // defaultOption.textContent = 'All Years';
                // yearDropdown.appendChild(defaultOption);
                data.forEach(year => {
                    let option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    yearDropdown.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching years:', error));
    }

    function fetchCatalog() {
        const store = document.getElementById('storeDropdownCatalog').value;
        const year = document.getElementById('yearDropdownCatalog').value;
        const month = document.getElementById('monthDropdownCatalog').value;
        const searchTerm = document.getElementById('productSearch').value.trim();
        const category = document.getElementById('categoryDropdown').value;
        const brand = document.getElementById('brandDropdown').value;

        let params = new URLSearchParams();
        params.append('interval', 'product_catalog');
        params.append('store_id', store);
        params.append('year', year);
        if (month !== 'all') {
            params.append('month', month);
        }
        if (searchTerm !== '') {
            params.append('search', searchTerm);
        }
        if (category !== 'all') {
            params.append('category', category);
        }
        if (brand !== 'all') {
            params.append('brand', brand);
        }

        fetch('api.php?' + params.toString())
            .then(response => response.json())
            .then(data => renderCatalog(data))
            .catch(error => {
                console.error('Error fetching catalog:', error);
                document.getElementById('catalogGrid').innerHTML = '<p>Error fetching data.</p>';
            });
    }

    // Format number in Danish style (comma as decimal separator)
    function renderCatalog(data) {
        const catalogGrid = document.getElementById('catalogGrid');
        catalogGrid.innerHTML = ''; // Clear existing items
    
        if (data.error) {
            catalogGrid.innerHTML = '<p>Error: ' + data.error + '</p>';
            return;
        }
    
        if (!data.length) {
            catalogGrid.innerHTML = '<p>No products found.</p>';
            return;
        }
    
        data.forEach(item => {
            // Create container for each catalog item
            const catalogItem = document.createElement('div');
            catalogItem.className = 'catalog-item';
            // Set a data attribute for the product ID so we can load its details later
            catalogItem.setAttribute('data-product-id', item.id);
    
            // Create image element with error handling
            const img = document.createElement('img');
            img.src = item.image_link ? item.image_link : 'img/placeholder.jpg';
            img.alt = item.title;
            img.onerror = function () {
                this.onerror = null;
                this.src = 'img/placeholder.jpg';
            };
            img.onload = function () {
                if (this.naturalWidth === 0) {
                    this.onload = null;
                    this.src = 'img/placeholder.jpg';
                }
            };
            catalogItem.appendChild(img);
    
            // Create details container
            const details = document.createElement('div');
            details.className = 'catalog-item-details';
    
            // Product title
            const title = document.createElement('h3');
            title.textContent = item.title;
            details.appendChild(title);
    
            // Total sales number (formatted in Danish style, in green, larger)
            const sales = document.createElement('p');
            const totalSales = Number(item.total_sales) || 0;
            sales.textContent = formatNumberDan(totalSales);
            sales.style.color = 'green';
            sales.style.fontSize = '18px';
            details.appendChild(sales);
    
            // Count (with increased font size)
            const count = document.createElement('p');
            const totalCount = Number(item.count_sales) || 0;
            count.textContent = 'Count: ' + totalCount;
            count.style.fontSize = '16px';
            details.appendChild(count);
    
            catalogItem.appendChild(details);
            catalogGrid.appendChild(catalogItem);
    
            // Attach click event to load detail view using the global loadDetailView function
            catalogItem.addEventListener('click', function () {
                const productId = this.getAttribute('data-product-id');
                if (productId && typeof loadDetailView === 'function') {
                    loadDetailView(productId);
                }
            });
        });
    }
    
    // Danish number formatting helper
    function formatNumberDan(num) {
        return new Intl.NumberFormat('da-DK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    }
    
    const filterIds = [
        'storeDropdownCatalog',
        'yearDropdownCatalog',
        'monthDropdownCatalog',
        'productSearch',
        'categoryDropdown',
        'brandDropdown'
    ];
    filterIds.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', fetchCatalog);
            if (id === 'productSearch') {
                element.addEventListener('keyup', fetchCatalog);
            }
        }
    });

    fetchStores();
    fetchCategories();
    fetchBrands();
    fetchYears();  // New call for dynamic years
    fetchCatalog();
});
