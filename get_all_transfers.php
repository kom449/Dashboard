<?php
// get_all_transfers.php
include 'cors.php';
session_start();
include 'db.php';

if (!isset($_SESSION['logged_in'], $_SESSION['is_admin']) || !$_SESSION['is_admin']) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Access denied']);
  exit();
}

$sql = "
  SELECT
    tr.id,
    tr.productIdentifier,
    i.title AS product_title,
    tr.source_store_id,
    ss.shop_name  AS source_name,
    tr.dest_store_id,
    ds.shop_name  AS dest_name,
    tr.quantity,
    tr.status,
    tr.created_at,
    tr.updated_at
  FROM transfer_requests tr
  JOIN items i ON tr.productIdentifier = i.Id
  LEFT JOIN shops ss ON tr.source_store_id = ss.shop_id
  LEFT JOIN shops ds ON tr.dest_store_id   = ds.shop_id
  ORDER BY tr.created_at DESC
";

$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch_assoc()) {
  $rows[] = $r;
}

echo json_encode(['success' => true, 'transfers' => $rows]);
