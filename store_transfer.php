<?php
// store_transfer.php
session_start();
include 'cors.php';
include 'db.php';

if (
    empty($_SESSION['logged_in']) ||
    (!(!empty($_SESSION['is_store_manager']) && $_SESSION['is_store_manager']) && // not store manager
        !(!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])) // not admin
) {
    echo "<p>Adgang nægtet.</p>";
    exit();
}


$currentStore = $_SESSION['store_id'] ?? '';

//  ––– fetch other stores (excl. online + current) –––
$stmt = $conn->prepare("
  SELECT shop_id, shop_name
    FROM shops
   WHERE shop_id <> '4582108'
     AND shop_id <> ?
   ORDER BY shop_name
");
$stmt->bind_param("s", $currentStore);
$stmt->execute();
$otherShops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div id="store-transfer" class="tab" style="display:none;">
    <h1>Overfør Lager</h1>

    <!-- Ny Anmodning -->
    <section id="new-request">
        <h2>Ny Anmodning</h2>
        <form id="transferRequestForm">
            <table id="transferTable">
                <thead>
                    <tr>
                        <th>Fra Butik</th>
                        <th>Vare-ID</th>
                        <th>Produkt</th>
                        <th>Antal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="transfer-row">
                        <td>
                            <select class="sourceStoreSelect" required>
                                <option value="">Vælg kilde</option>
                                <?php foreach ($otherShops as $shop): ?>
                                    <option value="<?= htmlspecialchars($shop['shop_id']) ?>">
                                        <?= htmlspecialchars($shop['shop_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                type="text"
                                class="productIdSearch"
                                placeholder="Søg Vare-ID"
                                disabled />
                        </td>
                        <td>
                            <select class="productSelect" required disabled>
                                <option value="">Vælg butik først</option>
                            </select>
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

            <button type="button" id="addRowBtn" class="action-btn">+ Tilføj Vare</button>
            <button type="submit" class="action-btn primary">Afgiv Anmodning</button>
            <div id="transferRequestMessage" class="no-data"></div>
        </form>
    </section>

    <!-- Afventende Afsendelser -->
    <section id="pending-shipments">
        <h2>Afventende Afsendelser</h2>
        <div id="outboundGrid" class="catalog-grid"></div>
    </section>

    <!-- Afventende Modtagelser -->
    <section id="pending-receipts">
        <h2>Afventende Modtagelser</h2>
        <div id="inboundGrid" class="catalog-grid"></div>
    </section>
</div>