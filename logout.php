<?php
require_once 'includes/session.php';

// Log out user
if (is_logged_in()) {
    require_once 'config/db.php';
    require_once 'includes/security.php';
    log_activity($pdo, $_SESSION['user_id'], 'LOGOUT', 'User logged out');
}

// Unset all session values
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?>
