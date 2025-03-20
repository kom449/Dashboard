document.addEventListener("DOMContentLoaded", () => {
    const tabLinks = document.querySelectorAll("#tabs ul li a");
    const tabs = document.querySelectorAll(".tab");

    tabLinks.forEach((tabLink) => {
        tabLink.addEventListener("click", (e) => {
            e.preventDefault();
            // Remove active classes and hide all tabs.
            tabLinks.forEach((link) => link.classList.remove("active"));
            tabs.forEach((tab) => {
                tab.classList.remove("active");
                tab.style.display = "none";
            });
            // Add active class to clicked tab.
            tabLink.classList.add("active");
            const target = tabLink.getAttribute("href").substring(1); // Get target id.
            const targetElement = document.getElementById(target);
            if (targetElement) {
                targetElement.classList.add("active");
                targetElement.style.display = "block";
                if (target === "all-products") {
                    fetchAllProducts();
                } else if (target === "product-catalog") {
                    setupOrderSearch();
                } else if (target === "admin-tab") {
                    setupAdminTab();
                }
            } else {
                console.error(`Element with id "${target}" not found.`);
            }
        });
    });
});
