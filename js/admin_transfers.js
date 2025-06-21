// js/admin_transfers.js

async function loadTransfers() {
  const tbody = document.querySelector("#adminTransferTable tbody");
  const res   = await fetch("get_all_transfers.php");
  const data  = await res.json();
  if (!data.success) {
    tbody.innerHTML = `<tr><td colspan="8" class="error">` +
                      `Error: ${data.message}</td></tr>`;
    return;
  }
  tbody.innerHTML = data.transfers.map(tr => `
    <tr>
      <td>${tr.id}</td>
      <td>${tr.product_title} (${tr.productIdentifier})</td>
      <td>${tr.source_name || tr.source_store_id}</td>
      <td>${tr.dest_name   || tr.dest_store_id}</td>
      <td>${tr.quantity}</td>
      <td>${tr.status}</td>
      <td>${tr.created_at}</td>
      <td>${tr.updated_at}</td>
    </tr>
  `).join("");
}

// expose to tabs.js
window.initTransferMonitorTab = function() {
  loadTransfers();
  // optional auto‐refresh:
  // setInterval(loadTransfers, 60_000);
};
