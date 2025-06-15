// admin.js

// 1) Render the user table
function setupAdminTab() {
  const container = document.getElementById("user-list");
  fetch("create_user.php")
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = `<p>Error: ${data.message}</p>`;
        return;
      }
      const users = data.users;
      let html = `
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Admin</th>
              <th>Store Manager</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
      `;
      users.forEach(u => {
        html += `
          <tr>
            <td>${u.id}</td>
            <td>${u.username}</td>
            <td>${u.is_admin ? "Yes" : "No"}</td>
            <td>${u.is_store_manager ? "Yes" : "No"}</td>
            <td>
              <button class="toggle-admin-btn"
                      data-id="${u.id}"
                      data-admin="${u.is_admin}">
                ${u.is_admin ? "Revoke Admin" : "Make Admin"}
              </button>
              <button class="toggle-store-manager-btn"
                      data-id="${u.id}"
                      data-storemanager="${u.is_store_manager}">
                ${u.is_store_manager ? "Revoke Manager" : "Make Manager"}
              </button>
              <button class="reset-password-btn" data-id="${u.id}">
                Reset Password
              </button>
              <button class="delete-user-btn" data-id="${u.id}">
                Delete User
              </button>
            </td>
          </tr>
        `;
      });
      html += `</tbody></table>`;
      container.innerHTML = html;

      // Wire up buttons
      container.querySelectorAll(".toggle-admin-btn")
        .forEach(btn => btn.addEventListener("click", () => {
          const id   = btn.dataset.id;
          const make = !(btn.dataset.admin === "1" || btn.dataset.admin === "true");
          toggleRole(id, make, 'toggle_admin');
        }));
      container.querySelectorAll(".toggle-store-manager-btn")
        .forEach(btn => btn.addEventListener("click", () => {
          const id   = btn.dataset.id;
          const make = !(btn.dataset.storemanager === "1" || btn.dataset.storemanager === "true");
          toggleRole(id, make, 'toggle_store_manager');
        }));
      container.querySelectorAll(".reset-password-btn")
        .forEach(btn => btn.addEventListener("click", () => resetPassword(btn.dataset.id)));
      container.querySelectorAll(".delete-user-btn")
        .forEach(btn => btn.addEventListener("click", () => deleteUser(btn.dataset.id)));
    })
    .catch(err => {
      console.error("Error loading users:", err);
      document.getElementById("user-list").innerHTML =
        "<p>Error loading users. Try again.</p>";
    });
}

// 2) Common helpers
function toggleRole(userId, flag, action) {
  const msg = action === 'toggle_admin'
    ? (flag ? "Grant" : "Revoke") + " admin?"
    : (flag ? "Grant" : "Revoke") + " store-manager?";
  if (!confirm(msg)) return;
  const payload = { action, user_id: userId };
  payload[action === 'toggle_admin' ? 'is_admin' : 'is_store_manager'] = flag ? 1 : 0;

  fetch("create_user.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
    .then(r => r.json())
    .then(d => {
      if (!d.success) throw new Error(d.message);
      alert("Updated.");
      setupAdminTab();
    })
    .catch(err => {
      console.error(err);
      alert(`Error: ${err.message}`);
    });
}

function resetPassword(userId) {
  if (!confirm("Reset password for user?")) return;
  fetch("create_user.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "reset_password", user_id: userId })
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        alert(`Temp password: ${d.temporary_password}`);
      } else {
        alert(`Error: ${d.message}`);
      }
    })
    .catch(err => {
      console.error(err);
      alert("Error resetting password.");
    });
}

function deleteUser(userId) {
  if (!confirm("Delete this user?")) return;
  fetch("create_user.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "delete_user", user_id: userId })
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        alert("Deleted.");
        setupAdminTab();
      } else {
        alert(`Error: ${d.message}`);
      }
    })
    .catch(err => {
      console.error(err);
      alert("Error deleting user.");
    });
}

function fetchLastUpdate() {
  fetch("get_last_update.php?nocache=" + Date.now())
    .then(r => r.json())
    .then(d => {
      const el = document.getElementById("last-update-time");
      el.textContent = d.success
        ? `Last Update: ${d.last_update}`
        : `Error: ${d.message}`;
    })
    .catch(err => {
      console.error(err);
      document.getElementById("last-update-time")
        .textContent = "Error fetching last update.";
    });
}

// 3) Immediately initialize (script is loaded at the bottom of the page)
setupAdminTab();
fetchLastUpdate();

// Attach the create-user handler directly (no more silent misses)
const form = document.getElementById("createUserForm");
if (!form) {
  console.error("createUserForm not found – check your index.php markup");
} else {
  form.addEventListener("submit", e => {
    e.preventDefault();
    const u = document.getElementById("username").value.trim();
    const p = document.getElementById("password").value.trim();
    if (!u || !p) return alert("Both fields are required.");

    fetch("create_user.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "create_user", username: u, password: p })
    })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          alert("User created.");
          form.reset();
          setupAdminTab();
        } else {
          alert(`Error: ${d.message}`);
        }
      })
      .catch(err => {
        console.error(err);
        alert("Error creating user.");
      });
  });
}
