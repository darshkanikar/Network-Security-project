<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

require_login();

$profile_username = trim($_GET['username'] ?? '');

if (empty($profile_username)) {
    header("Location: search.php");
    exit();
}

// Fetch public profile data by username (NO balance, NO email)
$stmt = $pdo->prepare("SELECT pehchan, naam, poora_naam, parichay, chitra, nirmit FROM upyogkarta WHERE naam = ?");
$stmt->execute([$profile_username]);
$profile = $stmt->fetch();

if (!$profile) {
    header("Location: search.php");
    exit();
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?='uploads/' . sanitize_input($profile['chitra'])?>" class="rounded-circle mb-3" width="120"
                    height="120" style="object-fit: cover;">
                <h4>
                    <?= sanitize_input($profile['poora_naam'] ?: $profile['naam'])?>
                </h4>
                <p class="text-muted">@
                    <?= sanitize_input($profile['naam'])?>
                </p>

                <?php if ($profile['parichay']): ?>
                <hr>
                <p>
                    <?= nl2br(sanitize_input($profile['parichay']))?>
                </p>
                <?php
endif; ?>

                <hr>
                <small class="text-muted">Member since
                    <?= date('M Y', strtotime($profile['nirmit']))?>
                </small>

                <div class="d-grid gap-2 mt-3">
                    <?php if ($profile['pehchan'] != $_SESSION['user_id']): ?>
                    <a href="transfer.php?to=<?= sanitize_input($profile['naam'])?>" class="btn btn-success">Transfer
                        Money</a>
                    <?php
endif; ?>
                    <a href="search.php" class="btn btn-outline-secondary">Back to Search</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>