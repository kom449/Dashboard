<?php
header('Content-Type: application/json');
include '../db.php';

$stmt = $conn->prepare("SELECT shop_id, shop_name FROM shops ORDER BY shop_name");
$stmt->execute();
$res = $stmt->get_result();

$shops = [];
while($row = $res->fetch_assoc()) {
  $shops[] = $row;
}
echo json_encode($shops);
