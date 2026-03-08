<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

require_login();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF Token Validation Failed";
    }
    else {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All fields are required.";
        }
        elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        }
        elseif ($old_password === $new_password) {
            $error = "New password must be different from old password.";
        }
        else {
            // Validate new password strength
            $pw_error = validate_password($new_password);
            if ($pw_error) {
                $error = $pw_error;
            }
            else {
                // Verify old password
                $stmt = $pdo->prepare("SELECT gupt_sanket FROM upyogkarta WHERE pehchan = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($old_password, $user['gupt_sanket'])) {
                    $error = "Current password is incorrect.";
                    log_activity($pdo, $user_id, 'PASSWORD_CHANGE_FAILED', 'Incorrect current password');
                }
                else {
                    // Update password
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE upyogkarta SET gupt_sanket = ? WHERE pehchan = ?");
                    $stmt->execute([$new_hash, $user_id]);

                    log_activity($pdo, $user_id, 'PASSWORD_CHANGED', 'Password changed successfully');

                    // Regenerate session to invalidate old sessions
                    session_regenerate_id(true);

                    $success = "Password changed successfully!";
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
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= sanitize_input($error)?>
                </div>
                <?php
endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= sanitize_input($success)?>
                </div>
                <?php
endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token()?>">
                    <div class="mb-3">
                        <label>Current Password</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6"
                            maxlength="14">
                        <small class="text-muted">6-14 characters, must include uppercase, lowercase, and a
                            digit</small>
                    </div>
                    <div class="mb-3">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6"
                            maxlength="14">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Change Password</button>
                    <div class="mt-3 text-center">
                        <a href="dashboard.php">Back to Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>