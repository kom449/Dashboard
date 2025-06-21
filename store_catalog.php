<?php
// store_catalog.php
// prereqs: session started, db.php included, $is_store_manager / $is_admin set

// 1) pull dropdown data
$shopsStmt = $conn->prepare("
  SELECT shop_id, shop_name
    FROM shops
   WHERE shop_id <> '4582108'
   ORDER BY shop_name
");
$shopsStmt->execute();
$shops = $shopsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$shopsStmt->close();

$catsStmt = $conn->prepare("
  SELECT Identifier, display_name 
    FROM product_categories 
   ORDER BY display_name
");
$catsStmt->execute();
$cats = $catsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catsStmt->close();

// now pull actors instead of brands
$actorsStmt = $conn->prepare("
  SELECT actor_id, identifier_search 
    FROM actors 
   ORDER BY identifier_search
");
$actorsStmt->execute();
$actors = $actorsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$actorsStmt->close();

// 2) pull catalog rows (join actors)
$sql = "
  SELECT
    p.productIdentifier,
    p.categoryIdentifier,
    i.title,
    a.identifier_search AS actor,
    i.image_link,
    pc.display_name AS category,
    s.shop_id,
    s.shop_name,
    st.stockCount,
    p.minQuantity,
    p.maxQuantity,
    p.attributeIntValue 
  FROM products p
  JOIN items i 
    ON p.productIdentifier = i.Id
  LEFT JOIN actors a 
    ON p.supplierActorId = a.actor_id
  LEFT JOIN product_categories pc 
    ON p.categoryIdentifier = pc.Identifier
  LEFT JOIN stock st 
    ON p.productIdentifier = st.productIdentifier
  LEFT JOIN shops s 
    ON st.shopId = s.shop_id
  ORDER BY i.title, s.shop_name
";
$rows = $conn->query($sql);
?>

<div id="store-catalog" class="tab" style="display: none;">
    <h1>Butikskatalog</h1>

    <div class="catalog-controls">
        <div class="filter-row">
            <div class="filter-group">
                <label for="storeDropdownCatalog">Vælg butik:</label>
                <select id="storeDropdownCatalog">
                    <option value="all">Alle butikker</option>
                    <?php foreach ($shops as $shop): ?>
                        <option value="<?= htmlspecialchars($shop['shop_id']) ?>">
                            <?= htmlspecialchars($shop['shop_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="categoryDropdownCatalog">Kategori:</label>
                <select id="categoryDropdownCatalog">
                    <option value="all">Alle kategorier</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= htmlspecialchars($c['Identifier']) ?>">
                            <?= htmlspecialchars($c['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="actorDropdownCatalog">Leverandører:</label>
                <select id="actorDropdownCatalog">
                    <option value="all">Alle Leverandørere</option>
                    <?php foreach ($actors as $act): ?>
                        <option value="<?= htmlspecialchars(strtolower($act['identifier_search'])) ?>">
                            <?= htmlspecialchars($act['identifier_search']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group filter-out">
                <input type="checkbox" id="outOfStockToggle" />
                <label for="outOfStockToggle">Vis kun udsolgte</label>
            </div>
        </div>
    </div>

    <table class="store-catalog-table">
        <thead>
            <tr>
                <th>Billede</th>
                <th>Produkt-ID</th>
                <th>Titel</th>
                <th>Leverandører</th>
                <th>Kategori</th>
                <th>Butik</th>
                <th>Lager</th>
                <th>Min antal</th>
                <th>Max antal</th>
                <th>Leverandørlager</th>
            </tr>
        </thead>
        <tbody id="storeCatalogBody">
            <?php if ($rows && $rows->num_rows): ?>
                <?php while ($r = $rows->fetch_assoc()): ?>
                    <tr
                        data-shop="<?= htmlspecialchars($r['shop_id']               ?? 'all') ?>"
                        data-category="<?= htmlspecialchars($r['categoryIdentifier'] ?? 'all') ?>"
                        data-actor="<?= htmlspecialchars(strtolower($r['actor']      ?? '')) ?>"
                        data-stock-count="<?= (int) ($r['stockCount']              ?? 0) ?>"
                        data-min-quantity="<?= (int) ($r['minQuantity']            ?? 0) ?>"
                        data-attribute-int-value="<?= (int) ($r['attributeIntValue'] ?? 0) ?>"
                        >
                        <td>
                            <?php if (!empty($r['image_link'])): ?>
                                <img
                                    src="<?= htmlspecialchars($r['image_link']) ?>"
                                    data-large="<?= htmlspecialchars($r['image_link']) ?>"
                                    alt="<?= htmlspecialchars($r['title']) ?>"
                                    class="thumbnail" />
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['productIdentifier']) ?></td>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= htmlspecialchars($r['actor']         ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['category']      ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['shop_name']     ?? '—') ?></td>
                        <td><?= (int) ($r['stockCount']   ?? 0) ?></td>
                        <td><?= (int) ($r['minQuantity']  ?? 0) ?></td>
                        <td><?= (int) ($r['maxQuantity']  ?? 0) ?></td>
                        <td><?= (int) ($r['attributeIntValue'] ?? 0) ?></td> <!-- new -->
                    </tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="no-data">Ingen produkter fundet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="imgModal" class="modal">
    <span class="close">&times;</span>
    <img class="modal-content" id="modalImg">
    <div id="caption"></div>
</div>