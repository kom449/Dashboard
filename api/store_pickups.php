<?php
header('Content-Type: application/json');
include '../db.php';

$store  = $_GET['store']  ?? 'all';
$year   = $_GET['year']   ?? 'all';
$month  = $_GET['month']  ?? 'all';

$where = [];
$params = [];
$types  = "";

// scope by store?
if ($store !== 'all' && $store!=='excludeOnline') {
  $where[]  = "shop_id = ?";
  $params[] = intval($store);
  $types   .= "i";
}

// scope by year?
if ($year !== 'all') {
  $where[]  = "YEAR(created_at) = ?";
  $params[] = intval($year);
  $types   .= "i";
}

// choose grouping: by day if a month’s picked, else by month
if ($month !== 'all') {
  $where[]  = "MONTH(created_at) = ?";
  $params[] = intval($month);
  $types   .= "i";

  $groupBy  = "DAY(created_at)";
  $labelExpr = "DAY(created_at)";
  $orderExpr = "DAY(created_at)";
} else {
  $groupBy   = "MONTH(created_at)";
  $labelExpr = "MONTH(created_at)";
  $orderExpr = "MONTH(created_at)";
}

$sql = "SELECT {$labelExpr} AS label, COUNT(*) AS value
          FROM sales_pickups"
      . (count($where)? " WHERE ".implode(" AND ", $where):"")
      . " GROUP BY {$groupBy}
         ORDER BY {$orderExpr}";

$stmt = $conn->prepare($sql);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$labels = [];
$values = [];
while($r = $res->fetch_assoc()){
  $labels[] = $r['label'];
  $values[] = (int)$r['value'];
}

echo json_encode(['labels'=>$labels,'values'=>$values]);
