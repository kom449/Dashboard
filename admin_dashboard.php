<?php
include 'cors.php';
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo "Access denied. Admins only.";
    exit();
}

echo '
<h2>Create a New User</h2>
<form method="POST" action="create_user.php">
    <input type="text" name="username" placeholder="Enter Username" required>
    <input type="password" name="password" placeholder="Enter Password" required>
    <button type="submit">Create User</button>
</form>
';

$stmt = $conn->prepare("SELECT username, is_admin FROM admin_auth");
$stmt->execute();
$stmt->bind_result($username, $isAdmin);

echo '<h3>Existing Users</h3><ul>';
while ($stmt->fetch()) {
    echo '<li>' . htmlspecialchars($username) . ($isAdmin ? ' (Admin)' : '') . '</li>';
}
echo '</ul>';

$stmt->close();
$conn->close();
