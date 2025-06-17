<?php
// confirm_shipped.php
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

$stmt = $conn->prepare("
  UPDATE transfer_requests
     SET status = 'pending_dest'
   WHERE id = ? AND source_store_id = ? AND status = 'pending_source'
");
$stmt->bind_param("is", $id, $me);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows > 0]);
$stmt->close();
