// js/product_catalog.js

// —————— manual‐load for detail_view.js ——————
var detailScriptLoaded = false;
function ensureDetailScript(callback) {
    if (detailScriptLoaded) return callback();
    const s = document.createElement('script');
    s.src = 'js/detail_view.js';
    s.onload = () => {
        detailScriptLoaded = true;
        callback();
    };
    document.head.appendChild(s);
}

// -- Helper functions for fetching and rendering catalog data --
function fetchStores() {
    return fetch('api.php?fetch_stores=1')
        .then(response => response.json())
        .then(data => {
            const storeDropdown = document.getElementById('storeDropdownCatalog');
            storeDropdown.innerHTML = '';
            data.forEach(store => {
                const option = document.createElement('option');
                option.value = store.shop_id;
                option.textContent = store.shop_name;
                storeDropdown.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching stores:', error));
}

function fetchCategories() {
    return fetch('api.php?interval=fetch_categories')
        .then(response => response.json())
        .then(data => {
            const categoryDropdown = document.getElementById('categoryDropdown');
            categoryDropdown.innerHTML = '';
            const defaultOption = document.createElement('option');
            defaultOption.value = 'all';
            defaultOption.textContent = 'All Categories';
            categoryDropdown.appendChild(defaultOption);
            data.forEach(category => {
                const option = document.createElement('option');
                option.value = category.identifier;
                option.textContent = category.display_name;
                categoryDropdown.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching categories:', error));
}

function fetchBrands() {
    return fetch('api.php?interval=fetch_brands')
        .then(response => response.json())
        .then(data => {
            const brandDropdown = document.getElementById('brandDropdown');
            brandDropdown.innerHTML = '';
            const defaultOption = document.createElement('option');
            defaultOption.value = 'all';
            defaultOption.textContent = 'All Brands';
            brandDropdown.appendChild(defaultOption);
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.brand;
                option.textContent = item.brand;
                brandDropdown.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching brands:', error));
}

function fetchYears() {
    return fetch('api.php?interval=fetch_years')
        .then(response => response.json())
        .then(data => {
            const yearDropdown = document.getElementById('yearDropdownCatalog');
            yearDropdown.innerHTML = '';
            data.forEach(year => {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                yearDropdown.appendChild(option);
            });
            const storedYear = sessionStorage.getItem('selectedYear');
            if (storedYear) yearDropdown.value = storedYear;
        })
        .catch(error => console.error('Error fetching years:', error));
}

function attachFilterListeners() {
    const filterIds = [
        'storeDropdownCatalog',
        'yearDropdownCatalog',
        'monthDropdownCatalog',
        'productSearch',
        'categoryDropdown',
        'brandDropdown',
        'sortDropdown'
    ];

    filterIds.forEach(id => {
        const element = document.getElementById(id);
        if (!element) return;

        const handler = () => {
            resetCatalog();
            fetchCatalog(currentPage);
        };

        if (id === 'yearDropdownCatalog') {
            element.addEventListener('change', () => {
                sessionStorage.setItem('selectedYear', element.value);
                handler();
            });
        } else if (id === 'monthDropdownCatalog') {
            element.addEventListener('change', () => {
                sessionStorage.setItem('selectedMonth', element.value);
                handler();
            });
        } else {
            element.addEventListener('change', handler);
        }

        if (id === 'productSearch') {
            element.addEventListener('keyup', handler);
        }
    });
}

function fetchCatalog(page = 1) {
    if (loading || allLoaded) return;
    loading = true;

    const store = document.getElementById('storeDropdownCatalog').value;
    const year = document.getElementById('yearDropdownCatalog').value;
    const month = document.getElementById('monthDropdownCatalog').value;
    const searchTerm = document.getElementById('productSearch').value.trim();
    const category = document.getElementById('categoryDropdown').value;
    const brand = document.getElementById('brandDropdown').value;
    const sort = document.getElementById('sortDropdown')?.value || 'sales';

    const params = new URLSearchParams({
        interval: 'product_catalog',
        store_id: store,
        year: year,
        page: page,
        page_size: pageSize,
        sort: sort
    });
    if (month !== 'all') params.append('month', month);
    if (searchTerm) params.append('search', searchTerm);
    if (category !== 'all') params.append('category', category);
    if (brand !== 'all') params.append('brand', brand);

    fetch(`api.php?${params}`)
        .then(res => res.json())
        .then(data => {
            renderCatalog(data, page);
            if (data.length < pageSize) allLoaded = true;
            loading = false;

            const grid = document.getElementById('catalogGrid');
            if (!allLoaded && grid.scrollHeight <= grid.clientHeight) {
                currentPage++;
                fetchCatalog(currentPage);
            }
        })
        .catch(err => {
            console.error('Error fetching catalog:', err);
            document.getElementById('catalogGrid').innerHTML = '<p>Error fetching data.</p>';
            loading = false;
        });
}

function renderCatalog(data, page) {
    const catalogGrid = document.getElementById('catalogGrid');
    if (page === 1) catalogGrid.innerHTML = '';
    if (data.error) {
        if (page === 1) catalogGrid.innerHTML = `<p>Error: ${data.error}</p>`;
        return;
    }
    if (!data.length && page === 1) {
        catalogGrid.innerHTML = '<p>No products found.</p>';
        return;
    }

    data.forEach(item => {
        const catalogItem = document.createElement('div');
        catalogItem.className = 'catalog-item';
        catalogItem.style.cursor = 'pointer';
        catalogItem.dataset.productId = item.id;

        const img = document.createElement('img');
        img.src = item.image_link || 'img/placeholder.jpg';
        img.alt = item.title;
        img.onerror = () => { img.onerror = null; img.src = 'img/placeholder.jpg'; };
        catalogItem.appendChild(img);

        const details = document.createElement('div');
        details.className = 'catalog-item-details';

        const title = document.createElement('h3');
        title.textContent = item.title;
        title.style.fontSize = '16px';
        details.appendChild(title);

        const productIdEl = document.createElement('small');
        productIdEl.textContent = `ID: ${item.id}`;
        productIdEl.style.display = 'block';
        productIdEl.style.fontSize = '12px';
        productIdEl.style.color = '#666';
        details.appendChild(productIdEl);

        const totalSales = Number(item.total_sales) || 0;
        const salesEl = document.createElement('p');
        salesEl.textContent = formatNumberDan(totalSales);
        salesEl.style.color = 'green';
        salesEl.style.fontSize = '18px';
        details.appendChild(salesEl);

        const totalCost = Number(item.total_cost) || 0;
        const grossProfit = totalSales * 0.8 - totalCost;
        const profitEl = document.createElement('p');
        profitEl.textContent = `Gross Profit: ${formatNumberDan(grossProfit)}`;
        profitEl.style.color = 'orange';
        profitEl.style.fontSize = '14px';
        details.appendChild(profitEl);

        const countEl = document.createElement('p');
        countEl.textContent = `Count: ${Number(item.count_sales) || 0}`;
        countEl.style.fontSize = '16px';
        details.appendChild(countEl);

        catalogItem.appendChild(details);
        catalogItem.addEventListener('click', () => {
            const id = item.id;
            ensureDetailScript(() => {
                loadDetailView(id);
            });
        });


        const sentinel = document.getElementById('sentinel');
        if (sentinel) catalogGrid.insertBefore(catalogItem, sentinel);
        else catalogGrid.appendChild(catalogItem);
    });
}

function formatNumberDan(num) {
    return new Intl.NumberFormat('da-DK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function resetCatalog() {
    currentPage = 1;
    allLoaded = false;
    loading = false;
}

function initIntersectionObserver() {
    const grid = document.getElementById('catalogGrid');
    let sentinel = document.getElementById('sentinel');
    if (!sentinel) {
        sentinel = document.createElement('div');
        sentinel.id = 'sentinel';
        sentinel.style.width = '100%';
        sentinel.style.height = '20px';
        sentinel.style.backgroundColor = 'rgba(255, 0, 0, 0.1)';
        grid.appendChild(sentinel);
    }
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !loading && !allLoaded) {
                currentPage++;
                fetchCatalog(currentPage);
            }
        });
    }, { root: grid, threshold: 0.1 });
    observer.observe(sentinel);
}

function initScrollListener() {
    const grid = document.getElementById('catalogGrid');
    grid.addEventListener('scroll', () => {
        const { scrollTop, scrollHeight, clientHeight } = grid;
        if (!loading && !allLoaded && scrollTop + clientHeight >= scrollHeight - 50) {
            currentPage++;
            fetchCatalog(currentPage);
        }
    });
}

// -- Public initializer --
function initCatalogTab() {
    // clear storage on reload
    const navEntries = performance.getEntriesByType("navigation");
    if (navEntries.length > 0 && navEntries[0].type === "reload") {
        sessionStorage.removeItem('selectedYear');
        sessionStorage.removeItem('selectedMonth');
    }

    fetchStores();
    fetchCategories();
    fetchBrands();
    fetchYears();
    attachFilterListeners();

    resetCatalog();
    fetchCatalog(1);
    setTimeout(() => {
        initIntersectionObserver();
        initScrollListener();
    }, 200);
}

// expose for tabs.js
window.initCatalogTab = initCatalogTab;
