<?php
include 'cors.php';
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admins only.']);
    exit();
}

function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomPassword = '';

    for ($i = 0; $i < $length; $i++) {
        $randomPassword .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomPassword;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT id, username, is_admin FROM admin_auth");
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create_user') {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_auth WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            exit();
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO admin_auth (username, password_hash, is_admin) VALUES (?, ?, 0)");
        $stmt->bind_param("ss", $username, $hashedPassword);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User created successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create user.']);
        }

        $stmt->close();
        exit();
    }

    if ($action === 'reset_password') {
        $userId = $input['user_id'] ?? null;
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'User ID is required.']);
            exit();
        }

        $temporaryPassword = generateRandomPassword();
        $hashedPassword = password_hash($temporaryPassword, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE admin_auth SET password_hash = ?, must_change_password = 1 WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Password reset successfully.',
                'temporary_password' => $temporaryPassword
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password.']);
        }

        $stmt->close();
        exit();
    }

    if ($action === 'delete_user') {
        $userId = $input['user_id'] ?? null;
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'User ID is required.']);
            exit();
        }

        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM admin_auth WHERE id = ?");
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
        }

        $stmt->close();
        exit();
    }

    if ($action === 'toggle_admin') {
        $userId = $input['user_id'] ?? null;
        $isAdmin = $input['is_admin'] ? 1 : 0;

        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'User ID is required.']);
            exit();
        }

        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot change your own admin status.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE admin_auth SET is_admin = ? WHERE id = ?");
        $stmt->bind_param("ii", $isAdmin, $userId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin status updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update admin status.']);
        }

        $stmt->close();
        exit();
    }
}
?>
