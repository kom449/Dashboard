<?php
// create_user.php
header('Content-Type: application/json; charset=utf-8');
include 'cors.php';
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admins only.']);
    exit();
}

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len   = strlen($chars);
    $pw    = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $chars[random_int(0, $len - 1)];
    }
    return $pw;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("
        SELECT id, username, is_admin, is_store_manager
        FROM admin_auth
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $row['is_admin']         = (int)$row['is_admin'];
        $row['is_store_manager'] = (int)$row['is_store_manager'];
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in     = json_decode(file_get_contents('php://input'), true);
    $action = $in['action'] ?? '';

    // Create
    if ($action === 'create_user') {
        $u = trim($in['username'] ?? '');
        $p = trim($in['password'] ?? '');
        if ($u === '' || $p === '') {
            echo json_encode(['success' => false, 'message' => 'Username and password required.']);
            exit();
        }
        // duplicate check
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_auth WHERE username = ?");
        $stmt->bind_param("s", $u);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();
        if ($cnt > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            exit();
        }
        $hash = password_hash($p, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            INSERT INTO admin_auth
                (username, password_hash, is_admin, is_store_manager)
            VALUES (?, ?, 0, 0)
        ");
        $stmt->bind_param("ss", $u, $hash);
        $ok = $stmt->execute();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'User created.' : 'Failed to create user.'
        ]);
        exit();
    }

    // Reset password
    if ($action === 'reset_password') {
        $id = $in['user_id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'User ID required.']);
            exit();
        }
        $temp = generateRandomPassword();
        $h    = password_hash($temp, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            UPDATE admin_auth
            SET password_hash = ?, must_change_password = 1
            WHERE id = ?
        ");
        $stmt->bind_param("si", $h, $id);
        $ok = $stmt->execute();
        echo json_encode([
            'success'            => $ok,
            'message'            => $ok ? 'Password reset.' : 'Failed to reset.',
            'temporary_password' => $ok ? $temp : null
        ]);
        exit();
    }

    // Delete
    if ($action === 'delete_user') {
        $id = $in['user_id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'User ID required.']);
            exit();
        }
        if ($id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete yourself.']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM admin_auth WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'User deleted.' : 'Failed to delete.'
        ]);
        exit();
    }

    // Toggle admin
    if ($action === 'toggle_admin') {
        $id     = $in['user_id'] ?? null;
        $flag   = !empty($in['is_admin']) ? 1 : 0;
        if (!$id || $id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Invalid operation.']);
            exit();
        }
        $stmt = $conn->prepare("UPDATE admin_auth SET is_admin = ? WHERE id = ?");
        $stmt->bind_param("ii", $flag, $id);
        $ok = $stmt->execute();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Admin status updated.' : 'Failed to update.'
        ]);
        exit();
    }

    // Toggle store-manager
    if ($action === 'toggle_store_manager') {
        $id   = $in['user_id'] ?? null;
        $flag = !empty($in['is_store_manager']) ? 1 : 0;
        if (!$id || $id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Invalid operation.']);
            exit();
        }
        $stmt = $conn->prepare("UPDATE admin_auth SET is_store_manager = ? WHERE id = ?");
        $stmt->bind_param("ii", $flag, $id);
        $ok = $stmt->execute();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Store-manager status updated.' : 'Failed to update.'
        ]);
        exit();
    }

    // Fallback
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}
?>
