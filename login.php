<?php
include 'cors.php';
session_start();
include 'db.php';

$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png">
    <title>Login</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div id="login-container">
        <h2>Login to Dashboard</h2>
        <form method="POST" action="login_action.php" id="login-form">
            <input type="text" name="username" placeholder="Enter Username" required>
            <input type="password" name="password" placeholder="Enter Password" required>
            <button type="submit" id="login-button">Login</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div id="error-modal" class="modal">
            <div class="modal-content">
                <h3>Error</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <button id="close-modal">Close</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const modal = document.getElementById('error-modal');
        const closeModal = document.getElementById('close-modal');
        if (modal) {
            modal.style.display = 'flex';
            closeModal.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>