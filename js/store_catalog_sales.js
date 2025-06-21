window.initStoreCatalogSales = function () {
  const table = document.getElementById('salesGrid');
  const yearSel = document.getElementById('yearSalesDropdown');
  const monthSel = document.getElementById('monthSalesDropdown');
  const searchEl = document.getElementById('productSearchSales');
  const supplierEl = document.getElementById('supplierDropdownSales');
  const exportBtn = document.getElementById('exportSalesButton');  // <— grab it here


  function fetchAndBuild() {
    const year = yearSel.value;
    const month = monthSel.value;
    const searchVal = searchEl.value.trim().toLowerCase();
    const supVal = supplierEl.value;

    fetch(`api/get_store_catalog_sales_grid.php?year=${year}&month=${month}`)
      .then(r => r.json())
      .then(({ stores, items }) => {
        // compute total sales for each item
        items.forEach(item => {
          item.totalSales = stores.reduce((sum, s) =>
            sum + (item.counts[s.shop_id] || 0)
            , 0);
        });

        // apply search + supplier filters
        let out = items.filter(item => {
          // supplier filter
          if (supVal !== 'all' && item.supplierActorId !== +supVal) {
            return false;
          }
          // search filter: match id OR title
          if (searchVal) {
            const idStr = String(item.productIdentifier).toLowerCase();
            const titleStr = String(item.title).toLowerCase();
            const idMatch = idStr.includes(searchVal);
            const titleMatch = titleStr.includes(searchVal);
            if (!idMatch && !titleMatch) return false;
          }
          return true;
        });

        // sort by totalSales descending
        out.sort((a, b) => b.totalSales - a.totalSales);

        // build the table
        let html = `<thead><tr>
          <th>Produkt-ID</th>
          <th>Titel</th>
          <th>Total</th>
          ${stores.map(s => `<th>${s.shop_name}</th>`).join('')}
        </tr></thead><tbody>`;

        out.forEach(item => {
          html += `<tr>
            <td>${item.productIdentifier}</td>
            <td>${item.title}</td>
            <td>${item.totalSales}</td>
            ${stores.map(s => `<td>${item.counts[s.shop_id] || 0}</td>`).join('')}
          </tr>`;
        });

        html += `</tbody>`;
        table.innerHTML = html;
      })
      .catch(err => console.error('Kunne ikke bygge grid:', err));
  }

  // re-run on year/month/supplier change
  [yearSel, monthSel, supplierEl].forEach(el =>
    el.addEventListener('change', fetchAndBuild)
  );

  // re-run as you type, and on Enter
  searchEl.addEventListener('input', fetchAndBuild);
  searchEl.addEventListener('keyup', e => {
    if (e.key === 'Enter') fetchAndBuild();
  });

  // initial load
  fetchAndBuild();

  // export
  exportBtn.addEventListener('click', () => {
    const wb = XLSX.utils.table_to_book(table, { sheet: 'Sales' });
    XLSX.writeFile(wb, `store-catalog-sales_${yearSel.value}.xlsx`);
  });

};
