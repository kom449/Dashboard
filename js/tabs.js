document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("#tabs ul li a").forEach((tabLink) => {
        tabLink.addEventListener("click", (e) => {
            e.preventDefault();
            document.querySelectorAll("#tabs ul li a").forEach((link) => link.classList.remove("active"));
            document.querySelectorAll(".tab").forEach((tab) => tab.classList.remove("active"));
            tabLink.classList.add("active");
            const target = tabLink.getAttribute("href").substring(1); // Get tab ID
            const targetElement = document.getElementById(target);

            if (targetElement) {
                targetElement.classList.add("active");

                if (target === "all-products") {
                    fetchAllProducts(); 
                } else if (target === "customer-orders") {
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
