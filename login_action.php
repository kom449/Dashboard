<?php
// login_action.php
include 'cors.php';
session_start();
include 'db.php';

// --- configuration ---
$maxAttempts  = 5;    // max failed tries before full lockout
$lockoutTime  = 900;  // 15 minutes in seconds
$cooldownTime = 60;   //  1 minute in seconds

$ipAddress = $_SERVER['REMOTE_ADDR'];
$username  = trim($_POST['username']  ?? '');
$password  =       $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Invalid request method.";
    exit();
}

if (! $username) {
    $_SESSION['login_error'] = "Username is required.";
    header('Location: login.php');
    exit();
}

// --- 1) Check lockout status ---
$stmt = $conn->prepare("
  SELECT attempts, last_attempt
    FROM login_attempts
   WHERE ip_address = ?
      OR username   = ?
");
$stmt->bind_param("ss", $ipAddress, $username);
$stmt->execute();
$stmt->bind_result($attempts, $lastAttempt);
$stmt->fetch();
$stmt->close();

$currentTime = time();

// If over maxAttempts *and* still in lockout window → deny
if ($attempts >= $maxAttempts
    && ($currentTime - $lastAttempt) < $lockoutTime) {
    $_SESSION['login_error'] = "Too many failed attempts. Try again later.";
    header('Location: login.php');
    exit();
}

// --- 2) Fetch user record (now including email, is_store_manager & store_id) ---
$stmt = $conn->prepare("
  SELECT
    id,
    email,
    password_hash,
    is_admin,
    is_store_manager,
    store_id,
    must_change_password
  FROM admin_auth
  WHERE username = ?
  LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result(
  $userId,
  $email,
  $hashedPassword,
  $isAdmin,
  $isStoreManager,
  $storeId,
  $mustChangePassword
);
$stmt->fetch();
$stmt->close();

// --- 3) Verify password ---
if ($hashedPassword && password_verify($password, $hashedPassword)) {
    // -- Success: clear any prior failures
    $stmt = $conn->prepare("
      DELETE FROM login_attempts
       WHERE username   = ?
          OR ip_address = ?
    ");
    $stmt->bind_param("ss", $username, $ipAddress);
    $stmt->execute();
    $stmt->close();

    // -- Save everything needed in session
    $_SESSION['user_id']           = $userId;
    $_SESSION['email']             = $email;            // ← for web@designcykler.dk check
    $_SESSION['username']          = $username;
    $_SESSION['is_admin']          = (bool) $isAdmin;
    $_SESSION['is_store_manager']  = (bool) $isStoreManager;
    $_SESSION['store_id']          = $storeId;          // ← for manager’s own store
    $_SESSION['logged_in']         = true;

    // -- Force password reset?
    if ($mustChangePassword) {
        header('Location: reset_password.php');
        exit();
    }

    // -- All good: go to dashboard
    header('Location: index.php');
    exit();

} else {
    // --- 4) Failed login: record it ---
    if ($attempts) {
        $stmt = $conn->prepare("
          UPDATE login_attempts
             SET attempts     = attempts + 1,
                 last_attempt = ?
           WHERE username   = ?
              OR ip_address = ?
        ");
        $stmt->bind_param("iss", $currentTime, $username, $ipAddress);
    } else {
        $stmt = $conn->prepare("
          INSERT INTO login_attempts
            (username, ip_address, attempts, last_attempt)
          VALUES
            (?, ?, 1, ?)
        ");
        $stmt->bind_param("ssi", $username, $ipAddress, $currentTime);
    }
    $stmt->execute();
    $stmt->close();

    $_SESSION['login_error'] = "Invalid username or password.";
    header('Location: login.php');
    exit();
}
