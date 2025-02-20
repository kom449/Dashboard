function setupOrderSearch() {
    const orderSearchForm = document.getElementById("orderSearchForm");
    if (orderSearchForm) {
        orderSearchForm.addEventListener("submit", (event) => {
            event.preventDefault();
            const customerId = document.getElementById("customerId").value.trim();
            if (customerId) {
                fetchCustomerOrders(customerId);
            } else {
                alert("Please enter a valid customer ID.");
            }
        });
    }
}

function fetchCustomerOrders(customerId) {
    const orderDetails = document.getElementById("order-details");
    orderDetails.innerHTML = "<p>Loading orders...</p>";

    fetch(`fetch_customer_orders.php?customer_id=${encodeURIComponent(customerId)}`)
        .then((response) => {
            if (!response.ok) throw new Error("Failed to fetch orders");
            return response.json();
        })
        .then((orders) => {
            if (orders.length === 0) {
                orderDetails.innerHTML = "<p>No orders found for this customer.</p>";
                return;
            }

            const table = `
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Order Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${orders.map(order => `
                            <tr>
                                <td>${order.order_id}</td>
                                <td>${order.product_name}</td>
                                <td>${order.quantity}</td>
                                <td>${order.price}</td>
                                <td>${order.order_date}</td>
                                <td>${order.status}</td>
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
            `;
            orderDetails.innerHTML = table;
        })
        .catch((error) => {
            console.error("Error fetching orders:", error);
            orderDetails.innerHTML = "<p>Error loading orders. Please try again.</p>";
        });
}
