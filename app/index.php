<?php
// index.php - Landing page redirect
// If user is logged in, go to dashboard. Otherwise, go to login.
require_once 'includes/session.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
}
else {
    header("Location: login.php");
}
exit();
?>