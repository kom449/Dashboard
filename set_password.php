<?php
include 'cors.php';
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $newPassword = $_POST['new_password'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin_auth (password_hash) VALUES (?)");
    $stmt->bind_param("s", $hashedPassword);
    $stmt->execute();
    header("Location: login.php");
    exit();
}
?>
