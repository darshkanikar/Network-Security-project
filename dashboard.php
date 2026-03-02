<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

require_login();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check for redirect messages
$flash_message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'profile_updated') {
    $flash_message = "Profile updated successfully!";
}

// Fetch user details
$stmt = $pdo->prepare("SELECT balance, full_name, bio, profile_image FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, 
           u_sender.username AS sender_name, 
           u_receiver.username AS receiver_name 
    FROM transactions t
    JOIN users u_sender ON t.sender_id = u_sender.id
    JOIN users u_receiver ON t.receiver_id = u_receiver.id
    WHERE t.sender_id = ? OR t.receiver_id = ?
    ORDER BY t.timestamp DESC
    LIMIT 10
");
$stmt->execute([$user_id, $user_id]);
$transactions = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <?php if ($flash_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= sanitize_input($flash_message)?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php
endif; ?>

                <img src="<?='uploads/' . sanitize_input($user['profile_image'])?>" class="rounded-circle mb-3"
                    width="100" height="100" style="object-fit: cover;">
                <h4>
                    <?= sanitize_input($user['full_name'] ?: $username)?>
                </h4>
                <p class="text-muted">Balance: <strong>₹
                        <?= number_format($user['balance'], 2)?>
                    </strong></p>
                <div class="d-grid gap-2">
                    <a href="profile.php" class="btn btn-outline-primary">Edit Profile</a>
                    <a href="transfer.php" class="btn btn-success">Transfer Money</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Recent Transactions</div>
            <div class="card-body">
                <?php if (count($transactions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Other Party</th>
                                <th>Amount</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <?php
        $is_sender = $t['sender_id'] == $user_id;
        $type = $is_sender ? 'Sent' : 'Received';
        $color = $is_sender ? 'text-danger' : 'text-success';
        $other_party = $is_sender ? $t['receiver_name'] : $t['sender_name'];
?>
                            <tr>
                                <td>
                                    <?= sanitize_input($t['timestamp'])?>
                                </td>
                                <td><span class="<?= $color?>">
                                        <?= $type?>
                                    </span></td>
                                <td>
                                    <?= sanitize_input($other_party)?>
                                </td>
                                <td class="<?= $color?>">
                                    <?= $is_sender ? '-' : '+'?>₹
                                    <?= number_format($t['amount'], 2)?>
                                </td>
                                <td>
                                    <?= sanitize_input($t['comment'])?>
                                </td>
                            </tr>
                            <?php
    endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
else: ?>
                <p class="text-muted text-center">No transactions yet.</p>
                <?php
endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>