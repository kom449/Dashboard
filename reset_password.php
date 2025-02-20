<?php
include 'cors.php';
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $userId = $_SESSION['user_id'];

    if (empty($newPassword) || strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE admin_auth SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['password_changed'] = true;
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div id="resetPasswordContainer">
        <form id="resetPasswordForm" method="POST" action="">
            <h2>Reset Your Password</h2>
            <?php if (isset($error)): ?>
                <p class="message error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <input type="password" id="new_password" name="new_password" placeholder="Enter your new password" required minlength="8">
            <button type="submit">Set New Password</button>
        </form>
    </div>
</body>
</html>
