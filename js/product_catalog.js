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
                    option.value = category.identifier;
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

    function renderCatalog(data) {
        const catalogGrid = document.getElementById('catalogGrid');
        catalogGrid.innerHTML = '';

        if (data.error) {
            catalogGrid.innerHTML = '<p>Error: ' + data.error + '</p>';
            return;
        }

        if (!data.length) {
            catalogGrid.innerHTML = '<p>No products found.</p>';
            return;
        }

        data.forEach(item => {
            const catalogItem = document.createElement('div');
            catalogItem.className = 'catalog-item';

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

            const details = document.createElement('div');
            details.className = 'catalog-item-details';

            const title = document.createElement('h3');
            title.textContent = item.title;
            details.appendChild(title);

            const sales = document.createElement('p');
            sales.textContent = 'Total Sales: DKK ' + (item.total_sales || '0');
            details.appendChild(sales);

            const count = document.createElement('p');
            count.textContent = 'Count: ' + (item.count_sales || '0');
            details.appendChild(count);

            catalogItem.appendChild(details);
            catalogGrid.appendChild(catalogItem);
        });
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
    fetchCatalog();
});
