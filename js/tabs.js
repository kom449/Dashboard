// js/tabs.js

let isAllProductsLoaded = false;
let isBikeraceLoaded = false;

document.addEventListener("DOMContentLoaded", () => {
    // 1) Only real tab‐links: href="#some-id" but NOT dropbtn roots
    const tabLinks = document.querySelectorAll(
        "#tabs ul li a[href^='#']:not(.dropbtn):not([href='#'])"
    );
    const dropdownBtns = document.querySelectorAll("#tabs .dropbtn");
    const tabs = document.querySelectorAll(".tab");

    // 2) Dropdown toggle: prevent default + stopPropagation + close others
    dropdownBtns.forEach(btn => {
        btn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();

            // close any OTHER open dropdown
            document.querySelectorAll(".dropdown.open").forEach(drop => {
                if (drop !== btn.parentElement) drop.classList.remove("open");
            });

            // toggle this one
            btn.parentElement.classList.toggle("open");
        });
    });

    // 3) Clicking anywhere else closes all dropdowns
    document.addEventListener("click", () => {
        document.querySelectorAll(".dropdown.open")
            .forEach(drop => drop.classList.remove("open"));
    });

    // 4) Tab‐switch logic on filtered tabLinks only
    tabLinks.forEach(link => {
        link.addEventListener("click", e => {
            e.preventDefault();

            // a) close any open dropdown
            document.querySelectorAll(".dropdown.open")
                .forEach(drop => drop.classList.remove("open"));

            // b) hide detail view
            const detailView = document.getElementById("detailView");
            if (detailView) detailView.style.display = "none";

            // c) clear active states
            tabLinks.forEach(a => a.classList.remove("active"));
            tabs.forEach(t => {
                t.classList.remove("active");
                t.style.display = "none";
            });

            // d) activate this tab
            link.classList.add("active");
            const id = link.getAttribute("href").slice(1);
            const pane = document.getElementById(id);
            if (!pane) {
                console.error(`No tab pane found for #${id}`);
                return;
            }
            pane.classList.add("active");
            pane.style.display = "block";

            // e) your existing per‐tab init logic
            switch (id) {
                case "all-products":
                    if (!isAllProductsLoaded && typeof window.fetchAllProducts === "function") {
                        window.fetchAllProducts();
                        isAllProductsLoaded = true;
                    }
                    break;
                case "product-creation":
                    // …initProductCreationForm() etc…
                    break;
                case "product-catalog":
                    resetCatalog();
                    document.getElementById("catalogGrid").innerHTML = "";
                    fetchCatalog(currentPage);
                    setTimeout(() => {
                        initIntersectionObserver();
                        initScrollListener();
                    }, 200);
                    break;
                case "store-catalog":
                    typeof window.initStoreCatalogTab === "function"
                        ? window.initStoreCatalogTab()
                        : console.error("Did you include js/store_catalog.js?");
                    break;
                case "store-catalog-sales":
                    if (typeof window.initStoreCatalogSales === "function") {
                        window.initStoreCatalogSales();
                    } else {
                        console.error("Did you include js/store_catalog_sales.js?");
                    }
                    break;
                case "store-traffic":
                    typeof window.initStoreTrafficTab === "function"
                        ? window.initStoreTrafficTab()
                        : console.error("Did you include js/store_traffic.js?");
                    break;
                case "store-transfer":
                    typeof window.initStoreTransferTab === "function"
                        ? window.initStoreTransferTab()
                        : console.error("Did you include js/store_transfer.js?");
                    break;
                case "transfer-monitor":
                    typeof window.initTransferMonitorTab === "function"
                        ? window.initTransferMonitorTab()
                        : console.error("Did you include js/admin_transfers.js?");
                    break;
                case "store-pickups":
                    if (typeof window.initStorePickupsTab === "function")
                        window.initStorePickupsTab();
                    else
                        console.error("Did you include js/store_pickups.js?");
                    break;
                case "bikerace-cms":
                    if (typeof window.initBikeraceCMS === "function") {
                        window.initBikeraceCMS();
                        window.isBikeraceLoaded = true;
                    }
                    else
                        console.error("Did you include js/bikerace.js?");
                    break;
                case "admin-tab":
                    setupAdminTab();
                    break;
                default:
                    break;
            }
        });
    });

    // 5) Auto‐open whichever link was marked .active on load
    const active = document.querySelector(
        "#tabs ul li a.active[href^='#']:not(.dropbtn):not([href='#'])"
    );
    if (active) active.click();
});
