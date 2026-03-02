<?php
// includes/session.php

// Secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS in production
ini_set('session.gc_maxlifetime', 7200); // 2 hours absolute max

session_start();

// Absolute session timeout: 2 hours
$absolute_timeout = 7200;
if (isset($_SESSION['session_created_at'])) {
    if (time() - $_SESSION['session_created_at'] >= $absolute_timeout) {
        // Session has exceeded absolute lifetime
        session_unset();
        session_destroy();
        session_start();
    }
}
else {
    $_SESSION['session_created_at'] = time();
}

// Idle timeout: 30 minutes of inactivity
$idle_timeout = 1800;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] >= $idle_timeout) {
        // User has been idle too long
        session_unset();
        session_destroy();
        session_start();
    }
}
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
else {
    $interval = 60 * 15; // 15 minutes
    if (time() - $_SESSION['last_regeneration'] >= $interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}
?>
