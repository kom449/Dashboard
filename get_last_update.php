<?php
session_start();
require_once "db.php"; // Ensure this file connects to your database

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$query = "SELECT last_updated FROM update_log ORDER BY last_updated DESC LIMIT 1"; // Using correct column name
$result = $conn->query($query);

if (!$result) {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
    exit;
}

if ($row = $result->fetch_assoc()) {
    echo json_encode(["success" => true, "last_update" => $row['last_updated']]);
} else {
    echo json_encode(["success" => false, "message" => "No updates found"]);
}

$conn->close();
?>
