function fetchAllProducts() {
    const productTable = document.getElementById("product-table");
    productTable.innerHTML = "<p>Loading products...</p>";

    fetch("fetch_products.php")
        .then((response) => response.json())
        .then((data) => {
            if (data.length > 0) {
                const tableHTML = `
                    <div style="margin-bottom: 10px;">
                        <button id="filterButton">Hide Items with Price 0</button>
                    </div>
                    <table id="productTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Group ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(product => `
                                <tr class="${parseFloat(product.price) === 0 ? 'hideable' : ''}">
                                    <td>${product.id}</td>
                                    <td>${product.name}</td>
                                    <td>${product.price} DKK</td>
                                    <td>${product.group_id}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                `;
                productTable.innerHTML = tableHTML;

                const filterButton = document.getElementById("filterButton");
                filterButton.addEventListener("click", () => {
                    const rows = document.querySelectorAll("#productTable .hideable");

                    if (rows.length === 0) {
                        alert("No rows with price 0 to hide.");
                        return;
                    }

                    const isHiding = filterButton.textContent === "Hide Items with Price 0";
                    rows.forEach(row => {
                        row.style.display = isHiding ? "none" : "";
                    });

                    filterButton.textContent = isHiding ? "Show Items with Price 0" : "Hide Items with Price 0";
                });
            } else {
                productTable.innerHTML = "<p>No products found.</p>";
            }
        })
        .catch((error) => {
            console.error("Error fetching products:", error);
            productTable.innerHTML = "<p>Error loading products. Please try again.</p>";
        });
}

fetchAllProducts();