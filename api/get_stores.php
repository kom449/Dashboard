<?php
declare(strict_types=1);

// 1) Turn on error reporting (remove in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 2) Always return JSON
header('Content-Type: application/json; charset=utf-8');

// 3) Pull in your mysqli connection
require_once __DIR__ . '/../db.php';

// 4) Check the connection
if (! isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'DB connection failed',
        'details' => $conn->connect_error ?? 'No $conn object'
    ]);
    exit;
}

// 5) Run the query
$sql    = 'SELECT shop_id, shop_name FROM shops ORDER BY shop_name';
$result = $conn->query($sql);

if (! $result) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Query failed',
        'details' => $conn->error
    ]);
    exit;
}

// 6) Build the array of stores
$stores = [];
while ($row = $result->fetch_assoc()) {
    $stores[] = [
        'shop_id'   => (int) $row['shop_id'],
        'shop_name' => $row['shop_name'],
    ];
}
$result->free();

// 7) Output the JSON
echo json_encode($stores);
