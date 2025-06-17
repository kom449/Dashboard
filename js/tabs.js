// js/tabs.js

let isAllProductsLoaded = false;  // track whether we’ve already fetched all products

document.addEventListener("DOMContentLoaded", () => {
    const tabLinks = document.querySelectorAll("#tabs ul li a");
    const tabs     = document.querySelectorAll(".tab");

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
            const targetId      = tabLink.getAttribute("href").substring(1);
            const targetElement = document.getElementById(targetId);

            if (!targetElement) {
                console.error(`Element with id "${targetId}" not found.`);
                return;
            }

            targetElement.classList.add("active");
            targetElement.style.display = "block";

            // Tab‐specific initialization logic
            switch (targetId) {
                case "all-products":
                    // only fetch once, if the function exists
                    if (!isAllProductsLoaded && typeof window.fetchAllProducts === "function") {
                        window.fetchAllProducts();
                        isAllProductsLoaded = true;
                    }
                    break;

                case "product-creation":
                    // … existing product-creation logic …
                    // e.g. initProductCreationForm();
                    break;

                case "product-catalog":
                    resetCatalog();
                    const catalogGrid = document.getElementById("catalogGrid");
                    catalogGrid.innerHTML = "";
                    fetchCatalog(currentPage);
                    setTimeout(() => {
                        initIntersectionObserver();
                        initScrollListener();
                    }, 200);
                    break;

                case "store-catalog":
                    if (typeof window.initStoreCatalogTab === "function") {
                        window.initStoreCatalogTab();
                    } else {
                        console.error("initStoreCatalogTab() is not defined. Did you include js/store_catalog.js?");
                    }
                    break;

                case "store-traffic":
                    if (typeof window.initStoreTrafficTab === "function") {
                        window.initStoreTrafficTab();
                    } else {
                        console.error("initStoreTrafficTab() is not defined. Did you include js/store_traffic.js?");
                    }
                    break;

                case "store-transfer":
                    if (typeof window.initStoreTransferTab === "function") {
                        window.initStoreTransferTab();
                    } else {
                        console.error("initStoreTransferTab() is not defined. Did you include js/store_transfer.js?");
                    }
                    break;

                case "transfer-monitor":
                    if (typeof window.initTransferMonitorTab === "function") {
                        window.initTransferMonitorTab();
                    } else {
                        console.error("initTransferMonitorTab() is not defined. Did you include js/admin_transfers.js?");
                    }
                    break;

                case "admin-tab":
                    setupAdminTab();
                    break;

                default:
                    // no special init
                    break;
            }
        });
    });

    // Auto‐initialize whichever tab link is marked .active on page load
    const activeLink = document.querySelector("#tabs ul li a.active");
    if (activeLink) {
        activeLink.click();
    }
});
