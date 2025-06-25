<?php
// store_transfer.php
session_start();
include 'cors.php';
include 'db.php';

// session flags
$store_manager = !empty($_SESSION['is_store_manager']);
$admin         = !empty($_SESSION['is_admin']);
$is_web_user   = !empty($_SESSION['email'])
  && $_SESSION['email'] === 'web@designcykler.dk';

// only store‐manager, admin or our special web user
if (
  empty($_SESSION['logged_in'])
  || (! $store_manager && ! $admin && ! $is_web_user)
) {
  echo "<p>Adgang nægtet.</p>";
  exit();
}

// fetch all physical shops (exclude online shop)
$stmt = $conn->prepare("
  SELECT shop_id, shop_name
    FROM shops
   WHERE shop_id <> '4582108'
   ORDER BY shop_name
");
$stmt->execute();
$allShops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// managers without admin/web can only ship *from* their own store
$currentStore      = $_SESSION['store_id'] ?? '';
$can_pick_any_src  = $admin || $is_web_user;
$sourceShops       = $can_pick_any_src
  ? $allShops
  : array_filter($allShops, fn($s) => $s['shop_id'] != $currentStore);

// only admin/web see the “to” column
$showDestSelect = $admin || $is_web_user;
$destShops      = $allShops;
?>

<div id="store-transfer" class="tab" style="display:none;">
  <h1>Overfør Lager</h1>

  <section id="new-request">
    <h2>Ny Anmodning</h2>
    <form id="transferRequestForm">
      <table id="transferTable">
        <thead>
          <tr>
            <th>Fra Butik</th>
            <?php if ($showDestSelect): ?><th>Til Butik</th><?php endif; ?>
            <th>Vare-ID</th>
            <th>Produkt</th>
            <th>Antal</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr class="transfer-row">
            <!-- SOURCE -->
            <td>
              <select class="sourceStoreSelect" required>
                <option value="">Vælg kilde</option>
                <?php foreach ($sourceShops as $s): ?>
                  <option value="<?= htmlspecialchars($s['shop_id']) ?>">
                    <?= htmlspecialchars($s['shop_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>

            <!-- DESTINATION (admin + web user) -->
            <?php if ($showDestSelect): ?>
              <td>
                <select class="destStoreSelect" required>
                  <option value="">Vælg destination</option>
                  <?php foreach ($destShops as $d): ?>
                    <option value="<?= htmlspecialchars($d['shop_id']) ?>">
                      <?= htmlspecialchars($d['shop_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            <?php endif; ?>

            <!-- PRODUCT & QTY -->
            <td>
              <input
                type="text"
                class="productIdExact"
                placeholder="Indtast præcis Vare-ID"
                disabled
                required
                pattern="[A-Za-z0-9]+"
                title="Tal og bogstaver – indtast det nøjagtige Vare-ID" />
            </td>
            <td>
              <input
                type="text"
                class="productNameDisplay"
                placeholder="Produktnavn"
                disabled />
            </td>
            <td>
              <input type="number" class="transferQty" min="1" required />
            </td>
            <td>
              <button type="button" class="remove-row">&minus;</button>
            </td>
          </tr>
        </tbody>
      </table>

      <button type="button" id="addRowBtn" class="action-btn">
        + Tilføj Vare
      </button>
      <button type="submit" class="action-btn primary">
        Afgiv Anmodning
      </button>
      <div id="transferRequestMessage" class="no-data"></div>
    </form>
  </section>

  <section id="pending-shipments">
    <h2>Afventende Afsendelser</h2>
    <div id="outboundGrid" class="catalog-grid"></div>
  </section>

  <section id="pending-receipts">
    <h2>Afventende Modtagelser</h2>
    <div id="inboundGrid" class="catalog-grid"></div>
  </section>
</div>

<script>
  window.currentUserEmail = <?= json_encode($_SESSION['email'] ?? '') ?>;
</script>
<script src="js/store_transfer.js"></script>