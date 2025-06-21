<?php
// api/get_store_catalog_sales_grid.php

// 1) Enable error reporting (remove or tone down in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2) JSON header
header('Content-Type: application/json');

// 3) Require DB + session check
session_start();
if (!isset($_SESSION['user_id'], $_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
require_once __DIR__ . '/../db.php';

// 4) Read & sanitize filters
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = ($_GET['month'] ?? 'all');

// 5) Date clause & params
$dateCond = 'ps.Completed_at BETWEEN ? AND ?';
$params   = ["{$year}-01-01", "{$year}-12-31"];
$types    = 'ss';
if ($month !== 'all') {
    $dateCond .= ' AND MONTH(ps.Completed_at) = ?';
    $types   .= 'i';
    $params[] = (int)$month;
}

// 6) Load shops (columns)
$shopsStmt = $conn->prepare("SELECT shop_id, shop_name FROM shops ORDER BY shop_name");
$shopsStmt->execute();
$shops = $shopsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$shopsStmt->close();

// 7) Build & prepare main query; now also grab supplierActorId
$sql = "
  SELECT
    p.productIdentifier,
    p.supplierActorId,
    i.title,
    s.shop_id,
    COUNT(*) AS cnt
  FROM product_sales_items psi
  JOIN product_sales ps       ON psi.sale_id = ps.sale_id
  JOIN products p             ON psi.product_identifier = p.productIdentifier
  JOIN items i                ON p.productIdentifier    = i.Id
  JOIN shops s                ON ps.shop_id             = s.shop_id
  WHERE $dateCond
  GROUP BY p.productIdentifier, s.shop_id
  ORDER BY i.title, s.shop_id
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

// 8) Bind params by reference
$refs = [ & $types ];
foreach ($params as $i => $v) {
    ${"p{$i}"} = $v;
    $refs[]    = &${"p{$i}"};
}
call_user_func_array([$stmt, 'bind_param'], $refs);

// 9) Execute + fetch
$stmt->execute();
$res = $stmt->get_result();

// 10) Assemble into grid structure, preserving supplierActorId
$itemsMap = [];
while ($row = $res->fetch_assoc()) {
    $pid = $row['productIdentifier'];
    if (!isset($itemsMap[$pid])) {
        $itemsMap[$pid] = [
            'productIdentifier' => $pid,
            'supplierActorId'   => (int)$row['supplierActorId'],
            'title'             => $row['title'],
            'counts'            => []
        ];
    }
    $itemsMap[$pid]['counts'][(int)$row['shop_id']] = (int)$row['cnt'];
}

// 11) Output JSON
echo json_encode([
    'stores' => $shops,
    'items'  => array_values($itemsMap),
], JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK);
