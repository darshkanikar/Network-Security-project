<?php
// includes/security.php

// CSRF Protection
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF Token Validation Failed");
    }
    return true;
}

// Input Sanitization (XSS Prevention)
function sanitize_input($data)
{
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// Input Validation Helpers
function validate_username($username)
{
    if (strlen($username) < 4 || strlen($username) > 18) {
        return "Username must be 4-18 characters.";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return "Username can only contain letters, numbers, and underscores.";
    }
    return null; // valid
}

function validate_password($password)
{
    if (strlen($password) < 6 || strlen($password) > 14) {
        return "Password must be 6-14 characters.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one digit.";
    }
    return null; // valid
}

function validate_email($email)
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }
    return null;
}

// Rate Limiting: check login attempts
function check_login_rate_limit($pdo, $ip, $username)
{
    $window = 15; // minutes
    $max_attempts = 5;

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE (ip_address = ? OR username = ?) AND attempted_at > NOW() - INTERVAL '$window minutes'");
    $stmt->execute([$ip, $username]);
    $row = $stmt->fetch();

    return ($row['cnt'] >= $max_attempts);
}

function record_failed_login($pdo, $ip, $username)
{
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    $stmt->execute([$ip, $username]);
}

function clear_login_attempts($pdo, $ip, $username)
{
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
    $stmt->execute([$ip, $username]);
}

// Log Activity
function log_activity($pdo, $user_id, $action, $details = '')
{
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $action,
        $_SERVER['REMOTE_ADDR'],
        $details
    ]);
}

// Image Hardening: re-process image through GD to strip metadata/embedded code
function harden_uploaded_image($tmp_path, $mime, $dest_path)
{
    // Load image based on MIME type
    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($tmp_path);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($tmp_path);
            break;
        case 'image/gif':
            $img = @imagecreatefromgif($tmp_path);
            break;
        default:
            return false;
    }

    if (!$img) {
        return false; // Not a valid image
    }

    // Validate dimensions
    $width = imagesx($img);
    $height = imagesy($img);
    if ($width > 2000 || $height > 2000) {
        imagedestroy($img);
        return false; // Too large
    }

    // Re-save the image (strips all metadata, EXIF, embedded code)
    // Map MIME to extension
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    $ext = $ext_map[$mime];
    $final_path = $dest_path . '.' . $ext;

    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($img, $final_path, 85);
            break;
        case 'image/png':
            imagepng($img, $final_path, 8);
            break;
        case 'image/gif':
            imagegif($img, $final_path);
            break;
    }

    imagedestroy($img);
    return basename($final_path);
}

// Security Headers
function set_security_headers()
{
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:;");
    header("Referrer-Policy: no-referrer-when-downgrade");
}

set_security_headers();
?>