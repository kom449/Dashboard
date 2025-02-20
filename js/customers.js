function fetchCustomers(query = "") {
    const url = query
        ? `fetch_customers.php?type=search&query=${encodeURIComponent(query)}`
        : "fetch_customers.php?type=customers";

    fetch(url)
        .then((response) => response.json())
        .then((customers) => {
            const tbody = document.querySelector("#customerTable tbody");
            tbody.innerHTML = "";

            if (customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">No customers found.</td></tr>';
                return;
            }

            customers.forEach((customer) => {
                tbody.innerHTML += `
                    <tr>
                        <td>${customer.id}</td>
                        <td>${customer.name}</td>
                        <td>${customer.email}</td>
                        <td>${customer.phone}</td>
                        <td>${customer.address}</td>
                        <td>
                            <button class="edit-button" data-id="${customer.id}">Edit</button>
                            <button class="delete-button" data-id="${customer.id}">Delete</button>
                        </td>
                    </tr>
                `;
            });

            document.querySelectorAll(".edit-button").forEach((button) => {
                button.addEventListener("click", (e) => {
                    const id = e.target.getAttribute("data-id");
                    editCustomer(id);
                });
            });

            document.querySelectorAll(".delete-button").forEach((button) => {
                button.addEventListener("click", (e) => {
                    const id = e.target.getAttribute("data-id");
                    deleteCustomer(id);
                });
            });
        })
        .catch((error) => console.error("Error fetching customers:", error));
}

function editCustomer(id) {
    const row = document.querySelector(`button[data-id="${id}"]`).closest("tr");
    const name = prompt("Edit Name:", row.children[1].innerText);
    const email = prompt("Edit Email:", row.children[2].innerText);
    const phone = prompt("Edit Phone:", row.children[3].innerText);
    const address = prompt("Edit Address:", row.children[4].innerText);

    if (name && email && phone && address) {
        fetch("update_customer.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id, name, email, phone, address }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    alert("Customer updated successfully.");
                    fetchCustomers();
                } else {
                    alert("Failed to update customer.");
                }
            })
            .catch((error) => console.error("Error updating customer:", error));
    }
}

function deleteCustomer(id) {
    if (confirm("Are you sure you want to delete this customer?")) {
        fetch("delete_customer.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    alert("Customer deleted successfully.");
                    fetchCustomers();
                } else {
                    alert("Failed to delete customer.");
                }
            })
            .catch((error) => console.error("Error deleting customer:", error));
    }
}
