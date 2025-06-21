<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// read filters
$store = $_GET['store'] ?? 'all';
$year  = (int) ($_GET['year']  ?? date('Y'));
$month = $_GET['month'] ?? 'all';

// build date range for the whole year
$start = "$year-01-01";
$end   = "$year-12-31";

// optional store filter
$storeSQL = "";
if ($store !== 'all') {
    $storeSQL = "AND ps.shop_id = ?";
}

// optional month filter
$monthSQL = "";
if ($month !== 'all') {
    $monthSQL = "AND MONTH(ps.Completed_at) = ?";
}

// SQL: count every sale_item whose parent sale falls in the date/window
$sql = "
  SELECT
    MONTH(ps.Completed_at) AS m,
    COUNT(*) AS cnt_sales
  FROM product_sales_items psi
  JOIN product_sales ps
    ON psi.sale_id = ps.sale_id
  JOIN products p
    ON psi.product_identifier = p.productIdentifier
  WHERE ps.Completed_at BETWEEN ? AND ?
    $storeSQL
    $monthSQL
  GROUP BY m
  ORDER BY m
";

$stmt = $conn->prepare($sql);

// normalize params into real variables
$startDate = $start;
$endDate   = $end;
$storeId   = ($store !== 'all') ? (int)$store : null;
$monthNum  = ($month !== 'all') ? (int)$month : null;

// bind parameters
if ($store !== 'all' && $month !== 'all') {
    $stmt->bind_param(
        'ssii',
        $startDate,
        $endDate,
        $storeId,
        $monthNum
    );
}
elseif ($store !== 'all') {
    $stmt->bind_param(
        'ssi',
        $startDate,
        $endDate,
        $storeId
    );
}
elseif ($month !== 'all') {
    $stmt->bind_param(
        'ssi',
        $startDate,
        $endDate,
        $monthNum
    );
}
else {
    $stmt->bind_param(
        'ss',
        $startDate,
        $endDate
    );
}

$stmt->execute();
$res = $stmt->get_result();

// prepare 12-month structure
$labels    = [];
$salesData = [];
for ($i = 1; $i <= 12; $i++) {
    $labels[]    = DateTime::createFromFormat('!m', $i)->format('M');
    $salesData[] = 0;
}

// fill in actual counts
while ($row = $res->fetch_assoc()) {
    $idx = (int)$row['m'] - 1;
    $salesData[$idx] = (int)$row['cnt_sales'];
}

echo json_encode([
    'labels' => $labels,
    'sales'  => $salesData
]);
