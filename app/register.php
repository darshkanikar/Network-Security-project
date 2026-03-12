<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        log_activity($pdo, null, 'CSRF_FAILURE', 'CSRF token mismatch on registration form');
        $error = "CSRF Token Validation Failed";
    }
    else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        }
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        }
        else {
            // Validate username (4-18 chars, alphanumeric + underscore)
            $username_error = validate_username($username);
            if ($username_error) {
                $error = $username_error;
            }

            // Validate password (6-50 chars, 1 upper, 1 lower, 1 digit)
            if (!$error) {
                $password_error = validate_password($password);
                if ($password_error) {
                    $error = $password_error;
                }
            }

            // Validate email
            if (!$error) {
                $email_error = validate_email($email);
                if ($email_error) {
                    $error = $email_error;
                }
            }

            if (!$error) {
                // Check if username or email exists
                $stmt = $pdo->prepare("SELECT pehchan FROM upyogkarta WHERE naam = ? OR vipatra = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->rowCount() > 0) {
                    $error = "Username or Email already exists.";
                }
                else {
                    $password_hash = password_hash($password, PASSWORD_ARGON2ID);
                    $stmt = $pdo->prepare("INSERT INTO upyogkarta (naam, vipatra, gupt_sanket, shesh) VALUES (?, ?, ?, 100.00)");
                    if ($stmt->execute([$username, $email, $password_hash])) {
                        $new_user_id = $pdo->lastInsertId();
                        log_activity($pdo, $new_user_id, 'REGISTER', "New account created: $username");
                        $success = "Registration successful! <a href='login.php'>Login here</a>";
                    }
                    else {
                        log_activity($pdo, null, 'REGISTER_FAILED', "Registration DB error for: $username");
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        }
    }
}
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Register</div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= sanitize_input($error)?>
                </div>
                <?php
endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= $success?>
                </div>
                <?php
endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token()?>">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required minlength="4" maxlength="18"
                            pattern="[a-zA-Z0-9_]+" value="<?= sanitize_input($_POST['username'] ?? '')?>"
                            title="4-18 characters, letters, numbers, and underscores only">
                        <small class="text-muted">4-18 characters, letters, numbers, underscores only</small>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required
                            value="<?= sanitize_input($_POST['email'] ?? '')?>">
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6"
                            maxlength="50">
                        <small class="text-muted">6-50 characters, must include uppercase, lowercase, a digit, and a
                            special character (e.g. @, #, !, $)</small>
                    </div>
                    <div class="mb-3">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6"
                            maxlength="50">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                    <div class="mt-3 text-center">
                        <a href="login.php">Already have an account? Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
