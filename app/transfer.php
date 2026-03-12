<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$to_username = $_GET['to'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        log_activity($pdo, $user_id, 'CSRF_FAILURE', 'CSRF token mismatch on transfer form');
        $error = "CSRF Token Validation Failed";
    }
    elseif (check_transfer_rate_limit($pdo, $user_id)) {
        $error = "Too many transfers. Please wait a few minutes before trying again.";
        log_activity($pdo, $user_id, 'TRANSFER_RATE_LIMITED', "Transfer rate limit exceeded");
    }
    else {
        $recipient_username = trim($_POST['recipient_username']);
        $amount = floatval($_POST['amount']);
        $comment = trim($_POST['comment']);

        // Validate comment length
        if (strlen($comment) > 200) {
            $error = "Comment must be 200 characters or less.";
        }
        elseif (empty($recipient_username) || $amount <= 0) {
            $error = "Invalid recipient or amount.";
        }
        else {
            try {
                $pdo->beginTransaction();

                // 1. Get Sender Balance (FOR UPDATE to lock row)
                $stmt = $pdo->prepare("SELECT pehchan, shesh FROM upyogkarta WHERE pehchan = ? FOR UPDATE");
                $stmt->execute([$user_id]);
                $sender = $stmt->fetch();

                // 2. Get Recipient by username (lock row too)
                $stmt = $pdo->prepare("SELECT pehchan, naam FROM upyogkarta WHERE naam = ? FOR UPDATE");
                $stmt->execute([$recipient_username]);
                $recipient = $stmt->fetch();

                if (!$sender) {
                    throw new Exception("Session error. Please log in again.");
                }
                if (!$recipient) {
                    throw new Exception("Recipient not found.");
                }
                if ($sender['pehchan'] === $recipient['pehchan']) {
                    throw new Exception("Cannot transfer money to yourself.");
                }
                if ($sender['shesh'] < $amount) {
                    throw new Exception("Insufficient funds.");
                }

                // 3. Deduct from Sender
                $stmt = $pdo->prepare("UPDATE upyogkarta SET shesh = shesh - ? WHERE pehchan = ?");
                $stmt->execute([$amount, $sender['pehchan']]);

                // 4. Add to Recipient
                $stmt = $pdo->prepare("UPDATE upyogkarta SET shesh = shesh + ? WHERE pehchan = ?");
                $stmt->execute([$amount, $recipient['pehchan']]);

                // 5. Record Transaction
                $stmt = $pdo->prepare("INSERT INTO lenden (bhejne_wala, pane_wala, rashi, tippani) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sender['pehchan'], $recipient['pehchan'], $amount, $comment]);

                // 6. Log Activity
                log_activity($pdo, $sender['pehchan'], 'TRANSFER_SENT', "Sent $amount to {$recipient['naam']}");
                log_activity($pdo, $recipient['pehchan'], 'TRANSFER_RECEIVED', "Received $amount from " . $_SESSION['username']);

                $pdo->commit();
                $message = "Transfer successful! Sent ₹" . number_format($amount, 2) . " to " . sanitize_input($recipient['naam']) . ".";
                $to_username = '';

            }
            catch (Exception $e) {
                $pdo->rollBack();
                // Domain exceptions (Insufficient funds, Recipient not found, etc.)
                // are intentionally thrown with user-safe messages above.
                // Unexpected PDOExceptions/system errors must NOT expose internals.
                if ($e instanceof PDOException) {
                    error_log('Transfer PDOException: ' . $e->getMessage());
                    log_activity($pdo, $user_id, 'TRANSFER_ERROR', 'Unexpected DB error during transfer');
                    $error = "Transfer failed due to a system error. Please try again.";
                }
                else {
                    $error = $e->getMessage(); // safe: these are our own controlled strings
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
            <div class="card-header">Transfer Money</div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= sanitize_input($error)?>
                </div>
                <?php
endif; ?>
                <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= sanitize_input($message)?>
                </div>
                <?php
endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token()?>">

                    <div class="mb-3">
                        <label>Recipient Username</label>
                        <input type="text" name="recipient_username" class="form-control"
                            placeholder="Enter recipient's username" value="<?= sanitize_input($to_username)?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label>Amount (₹)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Comment <small class="text-muted">(optional, max 200 chars)</small></label>
                        <textarea name="comment" class="form-control" rows="2" maxlength="200"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Send Money</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>