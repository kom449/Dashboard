<?php
declare(strict_types=1);

// 1) Turn on errors for debugging (disable in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 2) Always return JSON
header('Content-Type: application/json; charset=utf-8');

// 3) Include your mysqli connection
require_once __DIR__ . '/../db.php';

// 4) Validate connection
if (! isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'DB connection failed',
        'details' => $conn->connect_error ?? 'No $conn object'
    ]);
    exit;
}

// 5) Build dynamic filters
$where  = [];
$params = [];
$types  = '';

// shop filter
if (isset($_GET['shop_id']) && $_GET['shop_id'] !== 'all') {
    $where[]    = 'shop_id = ?';
    $params[]   = (int) $_GET['shop_id'];
    $types     .= 'i';
}

// date filter
if (!empty($_GET['date'])) {
    $where[]    = 'DATE(completed_at) = ?';
    $params[]   = $_GET['date'];  // expected format YYYY-MM-DD
    $types     .= 's';
}

// 6) Assemble SQL — now querying the raw workcards table
$sql = "
    SELECT
      HOUR(completed_at) AS hr,
      COUNT(*)           AS cnt
    FROM `workcards`
";
if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= "
    GROUP BY hr
    ORDER BY hr
";

$stmt = $conn->prepare($sql);
if (! $stmt) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Prepare failed',
        'details' => $conn->error
    ]);
    exit;
}

// 7) Bind params if needed
if ($types !== '') {
    $bindNames = [];
    $bindNames[] = $types;
    foreach ($params as $idx => &$param) {
        $bindNames[] = & $param;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindNames);
}

// 8) Execute and fetch
if (! $stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Execute failed',
        'details' => $stmt->error
    ]);
    exit;
}

$res = $stmt->get_result();
$countsMap = [];
while ($row = $res->fetch_assoc()) {
    $countsMap[(int)$row['hr']] = (int)$row['cnt'];
}
$stmt->close();

// 9) Ensure hours 0–23 are present
$labels = range(0, 23);
$counts = array_map(fn($h) => $countsMap[$h] ?? 0, $labels);

// 10) Output JSON
echo json_encode([
    'labels' => $labels,
    'counts' => $counts,
]);
