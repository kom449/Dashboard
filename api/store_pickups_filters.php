<?php
header('Content-Type: application/json');
include '../db.php';

$store = $_GET['store'] ?? 'all';

// build base WHERE
$where = [];
$params = [];
if ($store !== 'all' && $store !== 'excludeOnline') {
  $where[]    = "shop_id = ?";
  $params[]   = intval($store);
}

// YEARS
$sqlY = "SELECT DISTINCT YEAR(created_at) AS year
           FROM sales_pickups"
       . (count($where)? " WHERE ".implode(" AND ", $where):"")
       . " ORDER BY year DESC";
$stmtY = $conn->prepare($sqlY);
if ($params) { $stmtY->bind_param(str_repeat("i",count($params)), ...$params); }
$stmtY->execute();
$resY = $stmtY->get_result();

$years = [];
while($r = $resY->fetch_assoc()) {
  $years[] = (int)$r['year'];
}

// MONTHS (across all years for now; could filter by year if you like)
$sqlM = "SELECT DISTINCT MONTH(created_at) AS month
           FROM sales_pickups"
       . (count($where)? " WHERE ".implode(" AND ", $where):"")
       . " ORDER BY month";
$stmtM = $conn->prepare($sqlM);
if ($params) { $stmtM->bind_param(str_repeat("i",count($params)), ...$params); }
$stmtM->execute();
$resM = $stmtM->get_result();

$months = [];
while($r = $resM->fetch_assoc()) {
  $months[] = (int)$r['month'];
}

echo json_encode([
  'years'  => $years,
  'months' => $months
]);
