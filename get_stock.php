<?php
// get_stock.php
include 'cors.php';
session_start();
include 'db.php';
header('Content-Type: application/json');

// only store managers can fetch stock for transfers
$store_manager = !empty($_SESSION['is_store_manager']) && $_SESSION['is_store_manager'];
$admin         = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (empty($_SESSION['logged_in']) || (!$store_manager && !$admin)) {
    http_response_code(403);
    echo json_encode([ 'success' => false, 'requests' => [] ]);
    exit();
}

$shopId = $_GET['shop_id'] ?? '';
if (!$shopId) {
  echo json_encode(['success'=>false,'message'=>'shop_id required']);
  exit();
}

// optional: ensure they cannot fetch their own store if you want
// if ($shopId === $_SESSION['store_id']) { ... }

$stmt = $conn->prepare("
  SELECT st.productIdentifier, st.stockCount, i.title
    FROM stock st
    JOIN items i ON st.productIdentifier = i.Id
   WHERE st.shopId = ?
     AND st.stockCount > 0
   ORDER BY i.title
");
$stmt->bind_param("s", $shopId);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
  'success' => true,
  'items'   => $results
]);
