<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        log_activity($pdo, null, 'CSRF_FAILURE', 'CSRF token mismatch on login form');
        $error = "CSRF Token Validation Failed";
    }
    else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "All fields are required.";
        }
        else {
            // Rate limiting check
            $ip = $_SERVER['REMOTE_ADDR'];
            if (check_login_rate_limit($pdo, $ip, $username)) {
                $error = "Too many failed login attempts. Please try again after 15 minutes.";
                log_activity($pdo, null, 'LOGIN_BLOCKED', "Rate limited: $username from $ip");
            }
            else {
                $stmt = $pdo->prepare("SELECT pehchan, gupt_sanket FROM upyogkarta WHERE naam = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['gupt_sanket'])) {
                    // Successful login
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['pehchan'];
                    $_SESSION['username'] = $username;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['session_created_at'] = time();

                    // Clear failed attempts on success
                    clear_login_attempts($pdo, $ip, $username);
                    log_activity($pdo, $user['pehchan'], 'LOGIN', 'User logged in successfully');

                    header("Location: dashboard.php");
                    exit();
                }
                else {
                    // Record failed attempt
                    record_failed_login($pdo, $ip, $username);
                    log_activity($pdo, null, 'LOGIN_FAILED', "Failed login for username: $username");
                    $error = "Invalid username or password.";
                }
            }
        }
    }
}
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">Login</div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= sanitize_input($error)?>
                </div>
                <?php
endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token()?>">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required minlength="4" maxlength="18">
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6"
                            maxlength="50">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                    <div class="mt-3 text-center">
                        <a href="register.php">Create an account</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
