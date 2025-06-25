<?php
// store_catalog_sales.php
// prereqs: session started, db.php included, $is_store_manager / $is_admin set

// get shops for dropdown
$shopsStmt = $conn->prepare("SELECT shop_id, shop_name FROM shops ORDER BY shop_name");
$shopsStmt->execute();
$shops = $shopsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$shopsStmt->close();

// get suppliers for dropdown
$actorsStmt = $conn->prepare("SELECT actor_id, identifier_search FROM actors ORDER BY identifier_search");
$actorsStmt->execute();
$actors = $actorsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$actorsStmt->close();

// helper to get years for dropdown (last 5 years + this year)
$currentYear = (int)date("Y");
$years = range($currentYear, $currentYear - 5);
?>

<div id="store-catalog-sales" class="tab" style="display:none; padding: 1em;">
  <h1>Butikskatalog-Salg</h1>

  <section id="storeSelection" class="selection-container">
    <div>
      <label for="productSearchSales">Søg Produkt-ID:</label>
      <input type="text" id="productSearchSales" placeholder="Indtast Produkt-ID" />
    </div>
    <div>
      <label for="supplierDropdownSales">Vælg Leverandør:</label>
      <select id="supplierDropdownSales">
        <option value="all">Alle leverandører</option>
        <?php foreach ($actors as $a): ?>
          <option value="<?= htmlspecialchars($a['actor_id']) ?>"><?= htmlspecialchars($a['identifier_search']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="yearSalesDropdown">Vælg år:</label>
      <select id="yearSalesDropdown">
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="monthSalesDropdown">Vælg måned:</label>
      <select id="monthSalesDropdown">
        <option value="all">Alle måneder</option>
        <?php
        $months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Maj', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dec'];
        foreach ($months as $num => $name): ?>
          <option value="<?= $num ?>" <?= $num === (int)date('n') ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <!-- Export Button -->
    <div>
      <button id="exportSalesButton" class="export-btn">Eksporter til Excel</button>
    </div>
  </section>

  <!-- Grid view: stores as columns, products as rows -->
  <div id="salesGridContainer" style="overflow-x:auto; margin-top:1em;">
    <table id="salesGrid" class="sales-grid-table">
      <!-- JS will inject <thead> and <tbody> here -->
    </table>
  </div>
</div>

