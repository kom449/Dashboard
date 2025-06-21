// js/store_pickups.js
(function () {
    let pickupsChart = null;

    window.initStorePickupsTab = function () {
        const storeSel = document.getElementById("storeDropdownPickups");
        const yearSel = document.getElementById("yearDropdownPickups");
        const monthSel = document.getElementById("monthDropdownPickups");
        const ctx = document.getElementById("pickupsChart").getContext("2d");

        // 1) Wire change‐events
        storeSel.addEventListener("change", loadFilters);
        yearSel.addEventListener("change", renderData);
        monthSel.addEventListener("change", renderData);

        // 2) Fetch shops → populate store dropdown → then load year/month
        fetch("/api/shops.php")
            .then(res => res.json())
            .then(shops => {
                // build the <option>s
                storeSel.innerHTML = `
          <option value="all">All Stores</option>
          <option value="excludeOnline">All Stores except Online</option>
          ${shops.map(s => `<option value="${s.shop_id}">${s.shop_name}</option>`).join("")}
        `;
                // now that store list is in place, load the filters
                loadFilters();
            })
            .catch(err => {
                console.error("Failed to load shops:", err);
            });

        // 3) Load year + month dropdowns based on current store
        function loadFilters() {
            const store = storeSel.value;
            fetch(`/api/store_pickups_filters.php?store=${encodeURIComponent(store)}`)
                .then(res => res.json())
                .then(({ years, months }) => {
                    yearSel.innerHTML = `<option value="all">All Years</option>`
                        + years.map(y => `<option value="${y}">${y}</option>`).join("");
                    monthSel.innerHTML = `<option value="all">All Months</option>`
                        + months.map(m => {
                            const name = new Date(0, m - 1)
                                .toLocaleString("default", { month: "long" });
                            return `<option value="${m}">${name}</option>`;
                        }).join("");
                    // after filters change, draw chart
                    renderData();
                })
                .catch(err => {
                    console.error("Failed to load year/month filters:", err);
                });
        }

        // 4) Fetch and draw the chart
        function renderData() {
            const params = new URLSearchParams({
                store: storeSel.value,
                year: yearSel.value,
                month: monthSel.value
            });
            fetch("/api/store_pickups.php?" + params)
                .then(res => res.json())
                .then(data => {
                    if (pickupsChart) pickupsChart.destroy();

                    // ← Insert this mapping:
                    let labels = data.labels;
                    if (monthSel.value === "all") {
                        labels = labels.map(m =>
                            new Date(0, m - 1)
                                .toLocaleString("default", { month: "short" })
                        );
                    }

                    pickupsChart = new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: labels,       // ← use your mapped labels here
                            datasets: [{
                                label: "Pickups",
                                data: data.values
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: monthSel.value === "all"
                                        ? `Pickups in ${yearSel.value}`
                                        : `Pickups in ${new Date(0, monthSel.value - 1)
                                            .toLocaleString("default", { month: "long" })} ${yearSel.value}`
                                },
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    // in case you ever want to keep the numeric values internally
                                    // but still transform them on-the-fly, you could also use:
                                    // ticks: {
                                    //   callback: t => new Date(0, t-1).toLocaleString('default',{month:'short'})
                                    // }
                                },
                                y: { beginAtZero: true }
                            }
                        }
                    });
                })
                .catch(err => {
                    console.error("Failed to load pickup data:", err);
                });
        }
    };
})();
