<?php
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(["error" => "Access denied. Please log in."]);
    exit();
}
?>

<?php
require 'db.php';

$query = "SELECT id, title AS name, IFNULL(sale_price, 0) AS price, group_id FROM items ORDER BY group_id ASC";
$result = $conn->query($query);

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($products);
?>
