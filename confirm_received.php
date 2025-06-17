<?php
// confirm_received.php
include 'cors.php';
session_start();
include 'db.php';
header('Content-Type: application/json');

$store_manager = !empty($_SESSION['is_store_manager']) && $_SESSION['is_store_manager'];
$admin         = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (empty($_SESSION['logged_in']) || (!$store_manager && !$admin)) {
    http_response_code(403);
    echo json_encode([ 'success' => false, 'requests' => [] ]);
    exit();
}

$me = $_SESSION['store_id'];
$id = intval($_POST['id'] ?? 0);

// Fetch request
$stmt = $conn->prepare("
  SELECT productIdentifier, source_store_id, quantity
    FROM transfer_requests
   WHERE id = ? AND dest_store_id = ? AND status = 'pending_dest'
");
$stmt->bind_param("is", $id, $me);
$stmt->execute();
$stmt->bind_result($prod, $src, $qty);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit();
}
$stmt->close();

// Move stock in a transaction
$conn->begin_transaction();

// 1) decrement source
$stmt = $conn->prepare("
  UPDATE stock
     SET stockCount = stockCount - ?
   WHERE shopId = ? AND productIdentifier = ?
");
$stmt->bind_param("iss", $qty, $src, $prod);
$stmt->execute();
$stmt->close();

// 2) increment dest
$stmt = $conn->prepare("
  INSERT INTO stock (shopId, productIdentifier, stockCount)
  VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE stockCount = stockCount + VALUES(stockCount)
");
$stmt->bind_param("isi", $me, $prod, $qty);
$stmt->execute();
$stmt->close();

// 3) mark completed
$stmt = $conn->prepare("
  UPDATE transfer_requests
     SET status = 'completed'
   WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$conn->commit();
echo json_encode(['success' => true]);
