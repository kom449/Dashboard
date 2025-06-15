// js/store_catalog.js

window.initStoreCatalogTab = function() {
  const container      = document.getElementById("store-catalog");
  if (!container) return;

  // Dropdowns & rows
  const storeSelect    = container.querySelector("#storeDropdownCatalog");
  const categorySelect = container.querySelector("#categoryDropdownCatalog");
  const brandSelect    = container.querySelector("#brandDropdownCatalog");
  const rows           = Array.from(container.querySelectorAll("#storeCatalogBody tr"));

  if (!storeSelect || !categorySelect || !brandSelect || rows.length === 0) {
    console.warn("Store Catalog: missing selects or no rows");
    return;
  }

  function filterRows() {
    const storeVal = storeSelect.value;
    const catVal   = categorySelect.value;
    const brandVal = brandSelect.value.toLowerCase();

    rows.forEach(row => {
      const rowShop    = row.dataset.shop     || "all";
      const rowCat     = row.dataset.category || "all";
      const rowBrand   = (row.dataset.brand   || "").toLowerCase();

      const matchesStore    = (storeVal === "all") || (rowShop  === storeVal);
      const matchesCategory = (catVal   === "all") || (rowCat   === catVal);
      const matchesBrand    = (brandVal === "all") || (rowBrand === brandVal);

      row.style.display = (matchesStore && matchesCategory && matchesBrand) ? "" : "none";
    });
  }

  // Attach filter listeners
  [storeSelect, categorySelect, brandSelect].forEach(sel =>
    sel.addEventListener("change", filterRows)
  );
  filterRows(); // initial

  // ——— Modal logic ———
  const modal    = document.getElementById("imgModal");
  const modalImg = document.getElementById("modalImg");
  const caption  = document.getElementById("caption");
  const closeBtn = modal.querySelector(".close");

  container.querySelectorAll(".thumbnail").forEach(img => {
    img.addEventListener("click", () => {
      modal.style.display = "block";
      modalImg.src        = img.dataset.large || img.src;
      caption.textContent = img.alt || "";
    });
  });

  closeBtn.addEventListener("click", () => {
    modal.style.display = "none";
  });

  // clicking outside the image closes
  modal.addEventListener("click", e => {
    if (e.target === modal) modal.style.display = "none";
  });
};
