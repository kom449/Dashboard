// js/store_catalog.js

window.initStoreCatalogTab = function() {
  const container      = document.getElementById("store-catalog");
  if (!container) return;

  // grab controls & rows
  const storeSelect    = container.querySelector("#storeDropdownCatalog");
  const categorySelect = container.querySelector("#categoryDropdownCatalog");
  const actorSelect    = container.querySelector("#actorDropdownCatalog");  // renamed
  const outToggle      = container.querySelector("#outOfStockToggle");
  const rows           = Array.from(container.querySelectorAll("#storeCatalogBody tr"));

  if (!storeSelect || !categorySelect || !actorSelect || !outToggle || rows.length === 0) {
    console.warn("Store Catalog: missing elements or no rows");
    return;
  }

    // apply default shop from data- attribute
  const defaultShop = storeSelect.dataset.defaultShop;
  if (defaultShop && 
      Array.from(storeSelect.options).some(opt => opt.value === defaultShop)
  ) {
    storeSelect.value = defaultShop;
  }

  function filterRows() {
    const storeVal = storeSelect.value;
    const catVal   = categorySelect.value;
    const actorVal = actorSelect.value.toLowerCase();
    const onlyOut  = outToggle.checked;

    rows.forEach(row => {
      const rowShop    = row.dataset.shop     || "all";
      const rowCat     = row.dataset.category || "all";
      const rowActor   = (row.dataset.actor   || "").toLowerCase();
      const stockCount = parseInt(row.dataset.stockCount, 10)   || 0;

      const matchesStore    = storeVal === "all"  || rowShop  === storeVal;
      const matchesCategory = catVal   === "all"  || rowCat   === catVal;
      const matchesActor    = actorVal === "all"  || rowActor === actorVal;
      const matchesOut      = !onlyOut           || stockCount === 0;

      row.style.display = (matchesStore && matchesCategory && matchesActor && matchesOut)
        ? "" : "none";
    });

    highlightRows();
  }

  function highlightRows() {
    rows.forEach(row => {
      const stock  = parseInt(row.dataset.stockCount, 10)   || 0;
      const minQty = parseInt(row.dataset.minQuantity, 10) || 0;
      row.classList.toggle("low-stock", stock < minQty);
    });
  }

  // attach listeners
  [storeSelect, categorySelect, actorSelect, outToggle].forEach(el =>
    el.addEventListener("change", filterRows)
  );

  // initial pass
  filterRows();

  // lightbox modal
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

  closeBtn.addEventListener("click", () => modal.style.display = "none");
  modal.addEventListener("click", e => {
    if (e.target === modal) modal.style.display = "none";
  });
};
