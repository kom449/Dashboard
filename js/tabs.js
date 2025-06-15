document.addEventListener("DOMContentLoaded", () => {
    const tabLinks = document.querySelectorAll("#tabs ul li a");
    const tabs = document.querySelectorAll(".tab");

    let isAllProductsLoaded = false;

    tabLinks.forEach((tabLink) => {
        tabLink.addEventListener("click", (e) => {
            e.preventDefault();

            // Hide the detail view if it's shown.
            const detailView = document.getElementById("detailView");
            if (detailView) {
                detailView.style.display = "none";
            }

            // Remove 'active' classes and hide all tabs.
            tabLinks.forEach((link) => link.classList.remove("active"));
            tabs.forEach((tab) => {
                tab.classList.remove("active");
                tab.style.display = "none";
            });

            // Activate the clicked tab.
            tabLink.classList.add("active");
            const targetId = tabLink.getAttribute("href").substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                targetElement.classList.add("active");
                targetElement.style.display = "block";

                // Tab‐specific initialization logic
                if (targetId === "all-products") {
                    if (!isAllProductsLoaded && typeof window.fetchAllProducts === "function") {
                        window.fetchAllProducts();
                        isAllProductsLoaded = true;
                    }
                }
                else if (targetId === "product-creation") {
                    // … product-creation logic, e.g. initProductCreationForm() …
                }
                else if (targetId === "product-catalog") {
                    resetCatalog();
                    const catalogGrid = document.getElementById("catalogGrid");
                    catalogGrid.innerHTML = "";
                    fetchCatalog(currentPage);
                    setTimeout(() => {
                        initIntersectionObserver();
                        initScrollListener();
                    }, 200);
                }
                else if (targetId === "store-catalog") {
                    if (typeof window.initStoreCatalogTab === "function") {
                        window.initStoreCatalogTab();
                    } else {
                        console.error("initStoreCatalogTab() is not defined. Did you include js/store_catalog.js?");
                    }
                }
                else if (targetId === "admin-tab") {
                    setupAdminTab();
                }
                else if (targetId === "store-traffic") {
                    if (typeof window.initStoreTrafficTab === "function") {
                        window.initStoreTrafficTab();
                    } else {
                        console.error("initStoreTrafficTab() is not defined. Did you include js/store_traffic.js?");
                    }
                }
            } else {
                console.error(`Element with id "${targetId}" not found.`);
            }
        });
    });
});
