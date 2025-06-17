<?php
// get_pending_receipts.php
session_start();
include 'cors.php';
include 'db.php';

$store_manager = !empty($_SESSION['is_store_manager']) && $_SESSION['is_store_manager'];
$admin         = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (empty($_SESSION['logged_in']) || (!$store_manager && !$admin)) {
    http_response_code(403);
    echo json_encode([ 'success' => false, 'requests' => [] ]);
    exit();
}

$dest = $_SESSION['store_id'];

$stmt = $conn->prepare(
    "SELECT
       tr.id,
       tr.productIdentifier,
       i.title,
       i.image_link,
       tr.quantity,
       s.shop_name AS source_name
     FROM transfer_requests tr
     JOIN items i ON tr.productIdentifier = i.Id
     JOIN shops s ON tr.source_store_id = s.shop_id
     WHERE tr.dest_store_id = ? AND tr.status = 'pending_dest'
     ORDER BY tr.created_at ASC"
);
$stmt->bind_param("s", $dest);
$stmt->execute();
$res = $stmt->get_result();

$requests = [];
while ($row = $res->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode([
    'success'  => true,
    'requests' => $requests
]);
