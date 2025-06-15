// js/store_traffic.js

async function initStoreTrafficTab() {
    const dropdown = document.getElementById("storeTrafficDropdown");
    const dateInput = document.getElementById("trafficDateInput");
    const ctx = document.getElementById("trafficChart").getContext("2d");

    const existing = Chart.getChart(ctx.canvas.id);
    if (existing) existing.destroy();

    const stores = await fetch("/api/get_stores.php").then(r => r.json());
    const filtered = stores.filter(s =>
        s.shop_id !== 4582108 &&
        s.shop_name.toLowerCase() !== "designcykler.dk"
    );
    dropdown.innerHTML = `
    <option value="all">All Stores</option>
    ${filtered.map(s => `<option value="${s.shop_id}">${s.shop_name}</option>`).join("")}
  `;

    dropdown.addEventListener("change", loadTraffic);
    dateInput.addEventListener("change", loadTraffic);

    if (!dateInput.value) {
        dateInput.value = new Date().toISOString().substring(0, 10);
    }

    loadTraffic();

    async function loadTraffic() {
        const storeId = dropdown.value;
        const date = dateInput.value; // YYYY-MM-DD

        const params = new URLSearchParams();
        if (storeId !== "all") params.set("shop_id", storeId);
        if (date) params.set("date", date);

        // fetch both datasets in parallel
        const [rawTraffic, rawWorkcards] = await Promise.all([
            fetch(`/api/get_store_traffic.php?${params}`).then(r => r.json()),
            fetch(`/api/get_workcard_counts.php?${params}`).then(r => r.json())
        ]);

        // trim to open hours (09:00–18:00)
        const OPEN = 9, CLOSE = 18;
        const labels = rawTraffic.labels.slice(OPEN, CLOSE + 1).map(h => `${h}:00`);
        const trafficCounts = rawTraffic.counts.slice(OPEN, CLOSE + 1);
        const workcardCounts = rawWorkcards.counts.slice(OPEN, CLOSE + 1);

        // compute combined totals
        const combinedCounts = trafficCounts.map((v, i) => v + (workcardCounts[i] || 0));

        const old = Chart.getChart(ctx.canvas.id);
        if (old) old.destroy();

        // draw only one combined dataset
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Total (Sales + Workcards)',
                        data: combinedCounts,
                        yAxisID: 'y',
                        backgroundColor: 'rgba(54,162,235,0.6)',
                        borderColor: 'rgba(54,162,235,1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: { display: true, text: 'Hour of Day' }
                    },
                    y: {
                        title: { display: true, text: 'Total Count' },
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: { mode: 'index', intersect: false },
                    legend: { position: 'top' }
                }
            }
        });
    }
}

// Expose it globally so tabs.js can call it
window.initStoreTrafficTab = initStoreTrafficTab;
