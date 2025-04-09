document.addEventListener("DOMContentLoaded", () => {
    const tabLinks = document.querySelectorAll("#tabs ul li a");
    const tabs = document.querySelectorAll(".tab");

    tabLinks.forEach((tabLink) => {
        tabLink.addEventListener("click", (e) => {
            e.preventDefault();

            const detailView = document.getElementById("detailView");
            if (detailView) {
                detailView.style.display = "none";
            }

            tabLinks.forEach((link) => link.classList.remove("active"));
            tabs.forEach((tab) => {
                tab.classList.remove("active");
                tab.style.display = "none";
            });

            tabLink.classList.add("active");
            const targetId = tabLink.getAttribute("href").substring(1); // Get target id.
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.classList.add("active");
                targetElement.style.display = "block";

                if (targetId === "all-products") {
                } else if (targetId === "product-catalog") {
                    resetCatalog();
                    const catalogGrid = document.getElementById("catalogGrid");
                    catalogGrid.innerHTML = "";
                    fetchCatalog(currentPage);
                    setTimeout(() => {
                        initIntersectionObserver();
                        initScrollListener();
                    }, 200);
                } else if (targetId === "admin-tab") {
                    setupAdminTab();
                }
            } else {
                console.error(`Element with id "${targetId}" not found.`);
            }
        });
    });
});
