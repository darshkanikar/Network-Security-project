<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF Token Validation Failed";
    }
    else {
        // Update basic info (username is NOT editable)
        $full_name = trim($_POST['full_name']);
        $bio = trim($_POST['bio']);

        // Validate lengths
        if (strlen($full_name) > 100) {
            $error = "Full name must be 100 characters or less.";
        }
        elseif (strlen($bio) > 500) {
            $error = "Bio must be 500 characters or less.";
        }

        // Handle File Upload
        $profile_image = null;
        if (!$error && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_image']['tmp_name'];
            $file_size = $_FILES['profile_image']['size'];

            // Validate MIME type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_types)) {
                $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
            }
            elseif ($file_size > 2 * 1024 * 1024) {
                $error = "File too large. Max 2MB.";
            }
            else {
                // Generate secure filename (extension from MIME, not user input)
                $upload_dir = __DIR__ . '/uploads/';
                $base_name = bin2hex(random_bytes(16));

                // Re-process image through GD to strip metadata/embedded code
                $result_filename = harden_uploaded_image($file_tmp, $mime, $upload_dir . $base_name);

                if ($result_filename) {
                    $profile_image = $result_filename;
                }
                else {
                    $error = "Invalid image file or dimensions too large (max 2000x2000).";
                }
            }
        }

        if (!$error) {
            // Build query dynamically (NO username update)
            $sql = "UPDATE upyogkarta SET poora_naam = ?, parichay = ?";
            $params = [$full_name, $bio];

            if ($profile_image) {
                $sql .= ", chitra = ?";
                $params[] = $profile_image;
            }

            $sql .= " WHERE pehchan = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                log_activity($pdo, $user_id, 'PROFILE_UPDATE', 'User updated profile details');
                // Redirect to dashboard after save (Whiteboard #8)
                header("Location: dashboard.php?msg=profile_updated");
                exit();
            }
            else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Fetch current data
$stmt = $pdo->prepare("SELECT naam, poora_naam, parichay, chitra FROM upyogkarta WHERE pehchan = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Edit Profile</div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= sanitize_input($error)?>
                </div>
                <?php
endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token()?>">

                    <div class="text-center mb-4">
                        <img src="<?='uploads/' . sanitize_input($user['chitra'])?>" class="rounded-circle mb-2"
                            width="120" height="120" style="object-fit: cover;">
                        <div class="small text-muted">Current Profile Image</div>
                    </div>

                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?= sanitize_input($user['naam'])?>" disabled
                            readonly>
                        <small class="text-muted">Username cannot be changed after registration.</small>
                    </div>

                    <div class="mb-3">
                        <label>Full Name <small class="text-muted">(max 100 chars)</small></label>
                        <input type="text" name="full_name" class="form-control"
                            value="<?= sanitize_input($user['poora_naam'])?>" maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label>Bio <small class="text-muted">(max 500 chars)</small></label>
                        <textarea name="bio" class="form-control" rows="5"
                            maxlength="500"><?= sanitize_input($user['parichay'])?></textarea>
                    </div>

                    <div class="mb-3">
                        <label>Profile Image (JPG, PNG, GIF - Max 2MB, Max 2000×2000)</label>
                        <input type="file" name="profile_image" class="form-control"
                            accept="image/jpeg,image/png,image/gif">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <a href="reset_password.php" class="btn btn-outline-warning">Change Password</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>