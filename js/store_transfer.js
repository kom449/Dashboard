// js/store_transfer.js

window.initStoreTransferTab = function () {
    const container = document.getElementById("store-transfer");
    if (!container) return;

    const form = container.querySelector("#transferRequestForm");
    const tableBody = container.querySelector("#transferTable tbody");
    const addRowBtn = document.getElementById("addRowBtn");
    const msgBox = document.getElementById("transferRequestMessage");
    const userEmail = window.currentUserEmail || "";

    function debounce(fn, delay = 300) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), delay);
        };
    }

    // save a clean template row
    const template = tableBody.querySelector(".transfer-row");
    const protoRow = template.cloneNode(true);
    tableBody.innerHTML = "";

    function wireRow(row) {
        const idInp = row.querySelector(".productIdExact");
        const nameDp = row.querySelector(".productNameDisplay");

        const lookup = debounce(async () => {
            const term = idInp.value.trim();
            nameDp.value = "";
            if (!row._shopId || !/^[A-Za-z0-9]+$/.test(term)) return;

            try {
                const url = new URL("get_stock.php", window.location);
                url.searchParams.set("shop_id", row._shopId);
                url.searchParams.set("search", term);

                const resp = await fetch(url);
                const json = await resp.json();
                if (!json.success) throw new Error(json.message || "API-fejl");

                // ← case-insensitive match here
                const match = json.items.find(i =>
                    i.productIdentifier.toLowerCase() === term.toLowerCase()
                );
                if (match) {
                    nameDp.value = match.title;
                }
            } catch (err) {
                console.error("Lookup error:", err);
            }
        }, 300);

        idInp.addEventListener("input", lookup);
    }


    function addRow() {
        const row = protoRow.cloneNode(true);
        row._shopId = null;

        row.querySelectorAll("select, input").forEach(el => {
            if (el.classList.contains("sourceStoreSelect") ||
                el.classList.contains("destStoreSelect")) {
                el.selectedIndex = 0;
            } else if (el.classList.contains("transferQty")) {
                el.value = "";
            } else if (el.classList.contains("productIdExact")) {
                el.value = "";
                el.disabled = true;
            } else if (el.classList.contains("productNameDisplay")) {
                el.value = "";
            }
        });

        // when source changes
        row.querySelector(".sourceStoreSelect")
            .addEventListener("change", e => {
                const shopId = e.target.value;
                const idInp = row.querySelector(".productIdExact");
                const nameDp = row.querySelector(".productNameDisplay");
                row._shopId = shopId;
                idInp.disabled = !shopId;
                idInp.value = "";
                nameDp.value = "";
            });

        wireRow(row);
        tableBody.appendChild(row);
    }

    // remove rows
    tableBody.addEventListener("click", e => {
        if (e.target.classList.contains("remove-row")) {
            const rows = tableBody.querySelectorAll(".transfer-row");
            if (rows.length > 1) e.target.closest(".transfer-row").remove();
        }
    });

    addRowBtn.addEventListener("click", addRow);
    addRow();  // start with one

    form.addEventListener("submit", async e => {
        e.preventDefault();
        msgBox.textContent = "";
        msgBox.className = "";

        const requests = [];
        for (const row of tableBody.querySelectorAll(".transfer-row")) {
            const source = row.querySelector(".sourceStoreSelect").value;
            const destEl = row.querySelector(".destStoreSelect");
            const destination = destEl ? destEl.value : null;
            const productId = row.querySelector(".productIdExact").value.trim();
            const productName = row.querySelector(".productNameDisplay").value.trim();
            const qty = row.querySelector(".transferQty").value;

            if (!source || !productId || !productName || !qty || (destEl && !destination)) {
                msgBox.textContent = "Udfyld alle felter korrekt, inkl. et gyldigt produkt.";
                msgBox.className = "error";
                return;
            }

            requests.push({
                source,
                product: productId,
                quantity: parseInt(qty, 10),
                email: userEmail,
                ...(destination && { destination })
            });
        }

        try {
            const resp = await fetch("place_transfer_request.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ requests })
            });
            const res = await resp.json();
            msgBox.textContent = res.message;
            msgBox.className = res.success ? "success" : "error";

            if (res.success) {
                tableBody.innerHTML = "";
                addRow();
                loadAll();
            }
        } catch (err) {
            console.error(err);
            msgBox.textContent = "Uventet fejl.";
            msgBox.className = "error";
        }
    });

    // … your existing loadOutbound(), loadInbound(), loadAll() …
    async function loadOutbound() { /* … */ }
    async function loadInbound() { /* … */ }
    function loadAll() {
        loadOutbound();
        loadInbound();
    }
    loadAll();
};
