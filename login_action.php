<?php
include 'cors.php';
session_start();
include 'db.php';

$maxAttempts = 5;  // Max attempts before cooldown
$lockoutTime = 900; // 15 minutes (in seconds)
$cooldownTime = 60; // 1 minute (in seconds)

$ipAddress = $_SERVER['REMOTE_ADDR']; // Track by IP
$username = isset($_POST['username']) ? trim($_POST['username']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$username) {
        $_SESSION['login_error'] = "Username is required.";
        header('Location: login.php');
        exit();
    }

    // Check if the user or IP is locked out
    $stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ? OR username = ?");
    $stmt->bind_param("ss", $ipAddress, $username);
    $stmt->execute();
    $stmt->bind_result($attempts, $lastAttempt);
    $stmt->fetch();
    $stmt->close();

    $currentTime = time();

    if ($attempts >= $maxAttempts && ($currentTime - $lastAttempt) < $lockoutTime) {
        $_SESSION['login_error'] = "Too many failed attempts. Try again later.";
        header('Location: login.php');
        exit();
    }

    // Fetch user credentials
    $stmt = $conn->prepare("SELECT id, password_hash, is_admin, must_change_password FROM admin_auth WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($id, $hashedPassword, $isAdmin, $mustChangePassword);
    $stmt->fetch();
    $stmt->close();

    if ($hashedPassword && password_verify($_POST['password'], $hashedPassword)) {
        // Successful login - reset attempts
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?");
        $stmt->bind_param("ss", $username, $ipAddress);
        $stmt->execute();
        $stmt->close();

        $_SESSION['user_id'] = $id;
        $_SESSION['is_admin'] = $isAdmin;
        $_SESSION['username'] = $username;
        $_SESSION['logged_in'] = true;

        if ($mustChangePassword) {
            header('Location: reset_password.php');
            exit();
        }

        header('Location: index.php');
        exit();
    } else {
        // Failed login attempt - update attempts count
        if ($attempts) {
            $stmt = $conn->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = ? WHERE username = ? OR ip_address = ?");
            $stmt->bind_param("iss", $currentTime, $username, $ipAddress);
        } else {
            $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempts, last_attempt) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("ssi", $username, $ipAddress, $currentTime);
        }
        $stmt->execute();
        $stmt->close();

        $_SESSION['login_error'] = "Invalid username or password.";
        header('Location: login.php');
        exit();
    }
} else {
    http_response_code(405); // Method not allowed
    echo "Invalid request method.";
    exit();
}
?>
