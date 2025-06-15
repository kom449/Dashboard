<?php
// store_catalog.php
// prereqs: session started, db.php included, $is_store_manager / $is_admin set

// 1) pull dropdown data
$shopsStmt = $conn->prepare("SELECT shop_id, shop_name FROM shops ORDER BY shop_name");
$shopsStmt->execute();
$shops = $shopsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$shopsStmt->close();

$catsStmt = $conn->prepare("SELECT Identifier, display_name FROM product_categories ORDER BY display_name");
$catsStmt->execute();
$cats = $catsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catsStmt->close();

$brandsRes = $conn->query("SELECT DISTINCT brand FROM items ORDER BY brand");
$brands = $brandsRes->fetch_all(MYSQLI_ASSOC);

// 2) pull catalog rows (now including image_link)
$sql = "
  SELECT
    p.productIdentifier,
    p.categoryIdentifier,
    i.title,
    i.brand,
    i.image_link,
    pc.display_name AS category,
    s.shop_id,
    s.shop_name,
    st.stockCount,
    p.minQuantity,
    p.maxQuantity
  FROM products p
  JOIN items i ON p.productIdentifier = i.Id
  LEFT JOIN product_categories pc ON p.categoryIdentifier = pc.Identifier
  LEFT JOIN stock st ON p.productIdentifier = st.productIdentifier
  LEFT JOIN shops s ON st.shopId = s.shop_id
  ORDER BY i.title, s.shop_name
";
$rows = $conn->query($sql);
?>

<div id="store-catalog" class="tab" style="display: none;">
  <h1>Store Catalog</h1>

  <div class="catalog-controls">
    <div class="filter-row">
      <div>
        <label for="storeDropdownCatalog">Select Store:</label>
        <select id="storeDropdownCatalog">
          <option value="all">All Stores</option>
          <?php foreach ($shops as $shop): ?>
            <option value="<?= htmlspecialchars($shop['shop_id']) ?>">
              <?= htmlspecialchars($shop['shop_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="categoryDropdownCatalog">Category:</label>
        <select id="categoryDropdownCatalog">
          <option value="all">All Categories</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= htmlspecialchars($c['Identifier']) ?>">
              <?= htmlspecialchars($c['display_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="brandDropdownCatalog">Brand:</label>
        <select id="brandDropdownCatalog">
          <option value="all">All Brands</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= htmlspecialchars($b['brand']) ?>">
              <?= htmlspecialchars($b['brand']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <table class="store-catalog-table">
    <thead>
      <tr>
        <th>Image</th>
        <th>Product ID</th>
        <th>Title</th>
        <th>Brand</th>
        <th>Category</th>
        <th>Shop</th>
        <th>Stock</th>
        <th>Min Qty</th>
        <th>Max Qty</th>
      </tr>
    </thead>
    <tbody id="storeCatalogBody">
      <?php if ($rows && $rows->num_rows): ?>
        <?php while ($r = $rows->fetch_assoc()): ?>
          <tr
            data-shop="<?= htmlspecialchars($r['shop_id'] ?? 'all') ?>"
            data-category="<?= htmlspecialchars($r['categoryIdentifier'] ?? 'all') ?>"
            data-brand="<?= htmlspecialchars(strtolower($r['brand'])) ?>">
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
            <td><?= htmlspecialchars($r['brand']) ?></td>
            <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['shop_name'] ?? '—') ?></td>
            <td><?= (int)($r['stockCount'] ?? 0) ?></td>
            <td><?= htmlspecialchars($r['minQuantity'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['maxQuantity'] ?? '—') ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" style="text-align:center">No products found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Image Modal -->
<div id="imgModal" class="modal">
  <span class="close">&times;</span>
  <img class="modal-content" id="modalImg">
  <div id="caption"></div>
</div>

<style>
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    padding-top: 60px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.8);
  }

  .modal-content {
    margin: auto;
    display: block;
    max-width: 80%;
    max-height: 80%;
  }

  .close {
    position: absolute;
    top: 20px;
    right: 35px;
    color: #fff;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
  }

  .thumbnail {
    max-width: 50px;
    max-height: 50px;
    cursor: pointer;
    border: 1px solid #ccc;
  }

  .thumbnail:hover {
    border-color: #888;
  }
</style>