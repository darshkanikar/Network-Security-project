<?php
// config/db.php

$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_NAME') ?: 'transactiwar';
$username = getenv('DB_USER') ?: 'user';
$password = getenv('DB_PASSWORD') ?: 'password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Disable emulated prepared statements for security
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}
catch (PDOException $e) {
    // In production, log this error instead of showing it
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>
