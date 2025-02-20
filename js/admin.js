function setupAdminTab() {
    const userListContainer = document.getElementById("user-list");

    fetch("create_user.php")
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                const users = data.users;
                const userTable = `
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Admin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${users
                        .map(
                            (user) => `
                                    <tr>
                                        <td>${user.id}</td>
                                        <td>${user.username}</td>
                                        <td>${user.is_admin ? "Yes" : "No"}</td>
                                        <td>
                                            <button class="toggle-admin-btn" data-id="${user.id}" data-admin="${user.is_admin}">
                                                ${user.is_admin ? "Revoke Admin" : "Make Admin"}
                                            </button>
                                            <button class="reset-password-btn" data-id="${user.id}">Reset Password</button>
                                            <button class="delete-user-btn" data-id="${user.id}">Delete User</button>
                                        </td>
                                    </tr>
                                `
                        )
                        .join("")}
                        </tbody>
                    </table>
                `;
                userListContainer.innerHTML = userTable;


                document.querySelectorAll(".toggle-admin-btn").forEach((button) => {
                    button.addEventListener("click", (e) => {
                        const userId = e.target.getAttribute("data-id");
                        const isAdmin = e.target.getAttribute("data-admin") === "1" || e.target.getAttribute("data-admin") === "true";
                        toggleAdminStatus(userId, !isAdmin);
                    });
                });

                document.querySelectorAll(".reset-password-btn").forEach((button) => {
                    button.addEventListener("click", (e) => {
                        const userId = e.target.getAttribute("data-id");
                        resetPassword(userId);
                    });
                });

                document.querySelectorAll(".delete-user-btn").forEach((button) => {
                    button.addEventListener("click", (e) => {
                        const userId = e.target.getAttribute("data-id");
                        deleteUser(userId);
                    });
                });
            } else {
                userListContainer.innerHTML = `<p>Error loading users: ${data.message}</p>`;
            }
        })
        .catch((error) => {
            console.error("Error fetching users:", error);
            userListContainer.innerHTML = "<p>Error loading users. Please try again.</p>";
        });
}

function deleteUser(userId) {
    const confirmation = confirm("Are you sure you want to delete this user?");

    if (!confirmation) return;

    fetch("create_user.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete_user", user_id: userId }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("User deleted successfully.");
                setupAdminTab();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => {
            console.error("Error deleting user:", error);
            alert("An error occurred. Please try again.");
        });
}

function resetPassword(userId) {
    const confirmation = confirm("Are you sure you want to reset this user's password?");
    if (!confirmation) return;

    fetch("create_user.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "reset_password", user_id: userId }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Password reset successfully. Temporary password: ${data.temporary_password}`);
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => {
            console.error("Error resetting password:", error);
            alert("An error occurred. Please try again.");
        });
}

function toggleAdminStatus(userId, makeAdmin) {
    const confirmation = confirm(
        makeAdmin
            ? "Are you sure you want to grant admin privileges to this user?"
            : "Are you sure you want to revoke admin privileges for this user?"
    );

    if (!confirmation) return;

    fetch("create_user.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "toggle_admin", user_id: userId, is_admin: makeAdmin ? 1 : 0 }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert("User admin status updated successfully.");
                setupAdminTab();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch((error) => {
            console.error("Error updating admin status:", error);
            alert("An error occurred. Please try again.");
        });
}

function fetchLastUpdate() {

    fetch("get_last_update.php?nocache=" + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("last-update-time").textContent = `Last Update: ${data.last_update}`;
            } else {
                document.getElementById("last-update-time").textContent = `Error: ${data.message}`;
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            document.getElementById("last-update-time").textContent = "Error fetching last update.";
        });
}

document.addEventListener("DOMContentLoaded", () => {
    setupAdminTab();
    fetchLastUpdate();

    document.getElementById("createUserForm").addEventListener("submit", (event) => {
        event.preventDefault();

        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value.trim();

        if (!username || !password) {
            alert("Please enter both username and password.");
            return;
        }

        fetch("create_user.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "create_user", username, password }),
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("User created successfully.");
                    document.getElementById("createUserForm").reset();
                    setupAdminTab();
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                console.error("Error creating user:", error);
                alert("An error occurred. Please try again.");
            });
    });
});

