<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

require_login();

$results = [];
$search_query = '';

if (isset($_GET['q'])) {
    $search_query = trim($_GET['q']);
    if (!empty($search_query)) {
        // Prepared statement prevents SQL Injection
        $stmt = $pdo->prepare("SELECT id, username, full_name, profile_image FROM users WHERE username LIKE ? OR full_name LIKE ? LIMIT 20");
        $like_query = "%" . $search_query . "%";
        $stmt->execute([$like_query, $like_query]);
        $results = $stmt->fetchAll();
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Search Users</div>
            <div class="card-body">
                <form method="GET" action="" class="d-flex gap-2 mb-4">
                    <input type="text" name="q" class="form-control" placeholder="Search by username or name..."
                        value="<?= sanitize_input($search_query)?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>

                <?php if ($search_query && empty($results)): ?>
                <p class="text-muted text-center">No users found.</p>
                <?php
endif; ?>

                <?php if (!empty($results)): ?>
                <div class="list-group">
                    <?php foreach ($results as $user): ?>
                    <div class="list-group-item d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?='uploads/' . sanitize_input($user['profile_image'])?>" class="rounded-circle"
                                width="50" height="50" style="object-fit: cover;">
                            <div>
                                <h5 class="mb-0">
                                    <a href="user_profile.php?id=<?= $user['id']?>" class="text-decoration-none">
                                        <?= sanitize_input($user['username'])?>
                                    </a>
                                </h5>
                                <small class="text-muted">
                                    <?= sanitize_input($user['full_name'])?>
                                </small>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="user_profile.php?id=<?= $user['id']?>" class="btn btn-sm btn-outline-primary">View
                                Profile</a>
                            <a href="transfer.php?to=<?= $user['id']?>" class="btn btn-sm btn-success">Transfer</a>
                        </div>
                    </div>
                    <?php
    endforeach; ?>
                </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>