<?php
include "_bootstrap.php";
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session for CSRF protection if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Debug: Log user object and GET parameters
error_log('User object: ' . print_r($user, true));
error_log('GET parameters: ' . print_r($_GET, true));

// Check admin privileges with flexible conditions
$isAdmin = false;
if (isset($user->is_admin) && $user->is_admin) {
    $isAdmin = true;
} elseif (isset($user->role) && in_array($user->role, ['admin', 'administrator'])) {
    $isAdmin = true;
} elseif (isset($user->admin) && $user->admin) {
    $isAdmin = true;
}
if (!$isAdmin) {
    die('Access denied: Admin privileges required. User properties: ' . htmlspecialchars(print_r($user, true)));
}

// Check if ticket ID is provided
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 1]]);
$showTicketList = ($ticketId === 0 || $ticketId === false);

// Fetch all tickets if no valid ID is provided
if ($showTicketList) {
    try {
        $stmt = $pdo->prepare("SELECT d.*, u.username FROM k_destek d LEFT JOIN k_users u ON d.uid = u.id ORDER BY d.id DESC LIMIT 50");
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_OBJ);
        error_log("Fetched " . count($tickets) . " tickets for admin list");
    } catch (PDOException $e) {
        $error = '⚠️ Database error: ' . htmlspecialchars($e->getMessage());
        $tickets = [];
        error_log('Ticket list fetch error: ' . $e->getMessage());
    }
} else {
    // Fetch specific ticket details
    try {
        $stmt = $pdo->prepare("SELECT d.*, u.username FROM k_destek d LEFT JOIN k_users u ON d.uid = u.id WHERE d.id = :id");
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_OBJ);
        error_log("Ticket query result for ID $ticketId: " . ($ticket ? 'Found' : 'Not found'));
    } catch (PDOException $e) {
        error_log('Ticket fetch error: ' . $e->getMessage());
        die('Database error: ' . htmlspecialchars($e->getMessage()));
    }

    if (!$ticket) {
        die('Ticket not found for ID: ' . htmlspecialchars($ticketId));
    }

    // Fetch replies
    try {
        $stmt = $pdo->prepare("SELECT c.*, u.username, u.is_admin FROM k_destek_cevaplar c LEFT JOIN k_users u ON c.uid = u.id WHERE c.ticket_id = :ticket_id ORDER BY c.tarih ASC");
        $stmt->execute([':ticket_id' => $ticketId]);
        $replies = $stmt->fetchAll(PDO::FETCH_OBJ);
        error_log("Fetched " . count($replies) . " replies for ticket $ticketId: " . print_r($replies, true));
    } catch (PDOException $e) {
        $replies = [];
        $error = '⚠️ Database error fetching replies: ' . htmlspecialchars($e->getMessage());
        error_log('Replies fetch error: ' . $e->getMessage());
    }
}

// Handle reply submission or status update
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$showTicketList) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '⚠️ Invalid CSRF token. Please try again.';
        error_log("CSRF validation failed for ticket $ticketId");
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'reply') {
            $message = trim($_POST['mesaj'] ?? '');
            if (empty($message)) {
                $error = '⚠️ Please enter a reply message.';
            } elseif (strlen($message) > 5000) {
                $error = '⚠️ Reply must be 5000 characters or less.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO k_destek_cevaplar (ticket_id, uid, mesaj, tarih) VALUES (:ticket_id, :uid, :mesaj, NOW())");
                    $stmt->execute([':ticket_id' => $ticketId, ':uid' => (int)$user->id, ':mesaj' => $message]);
                    $replyId = $pdo->lastInsertId();
                    error_log("Admin reply saved: ticket_id=$ticketId, reply_id=$replyId, uid=" . (int)$user->id . ", mesaj=" . htmlspecialchars($message));
                    $success = '✅ Reply submitted successfully.';
                    // Reopen ticket if closed
                    if ($ticket->durum === 'kapali') {
                        $pdo->prepare("UPDATE k_destek SET durum = 'acik' WHERE id = :id")->execute([':id' => $ticketId]);
                        error_log("Ticket $ticketId reopened from closed to open");
                    }
                } catch (PDOException $e) {
                    $error = '⚠️ Database error: ' . htmlspecialchars($e->getMessage());
                    error_log("Admin reply insertion failed: " . $e->getMessage());
                }
            }
        } elseif ($action === 'update_status') {
            $newStatus = $_POST['durum'] ?? 'acik';
            if (!in_array($newStatus, ['acik', 'kapali'])) {
                $error = '⚠️ Invalid status selected.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE k_destek SET durum = :durum WHERE id = :id");
                    $stmt->execute([':durum' => $newStatus, ':id' => $ticketId]);
                    $success = '✅ Ticket status updated to ' . ($newStatus === 'acik' ? 'Open' : 'Closed') . '.';
                    $ticket->durum = $newStatus;
                    error_log("Ticket $ticketId status updated to $newStatus");
                } catch (PDOException $e) {
                    $error = '⚠️ Database error: ' . htmlspecialchars($e->getMessage());
                    error_log("Status update failed: " . $e->getMessage());
                }
            }
        }
    }
}

// Database optimization: Add indexes if not exists
try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_uid ON k_destek (uid)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tarih ON k_destek (tarih)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_id ON k_destek_cevaplar (ticket_id)");
} catch (PDOException $e) {
    error_log('Index creation failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>🎫 Admin Ticket View - HackLink Panel</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #050510;
    --panel: #0a0920;
    --neon1: #00e6ff;
    --neon2: #00ff9d;
    --accent: #facc15;
}
body {
    background: radial-gradient(900px 300px at 10% 10%, rgba(0,230,255,.03), transparent 50%),
                radial-gradient(700px 300px at 90% 90%, rgba(0,255,157,.02), transparent 50%), var(--bg);
    color: #e7edf8;
    font-family: 'Share Tech Mono', monospace;
}
.cardx {
    background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
    border: 1px solid rgba(0,255,157,.1);
    border-radius: 12px;
    box-shadow: 0 0 16px rgba(0,255,157,.05);
    padding: 20px;
    margin-bottom: 20px;
}
.table-dark th {
    color: var(--neon1);
}
.btn-neon {
    background: linear-gradient(90deg, var(--neon2), var(--neon1));
    border: none;
    color: #000;
    font-weight: 600;
    box-shadow: 0 0 12px rgba(0,255,157,.2);
    transition: .25s;
}
.btn-neon:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 18px rgba(0,255,157,.4);
}
.btn-outline-info {
    border-color: var(--neon1);
    color: var(--neon1);
}
.btn-outline-info:hover {
    background: var(--neon1);
    color: #000;
}
.badge-open {
    background: var(--accent);
    color: #000;
    font-weight: 700;
}
.badge-closed {
    background: var(--neon2);
    color: #000;
}
.reply-admin {
    background: rgba(0,230,255,.1);
    border-left: 3px solid var(--neon1);
}
.reply-user {
    background: rgba(0,255,157,.1);
    border-left: 3px solid var(--neon2);
}
footer {
    color: #9ab1d1;
    text-align: center;
    margin-top: 40px;
    padding: 15px 0;
    font-size: 13px;
}
</style>
</head>
<body>
<div class="container my-4" style="max-width:950px;">
    <div class="cardx">
        <?php if ($showTicketList) { ?>
            <h4 class="mb-3" style="color:var(--neon1);">📂 All Tickets</h4>
            <?php if (!empty($error)) echo "<div class='alert alert-warning'>$error</div>"; ?>
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle">
                    <thead><tr><th>#</th><th>Subject</th><th>User</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($tickets) { $i = 1;
                            foreach ($tickets as $t) { ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($t->konu) ?></td>
                                    <td><?= htmlspecialchars($t->username ?? 'Unknown') ?></td>
                                    <td><?= date("d.m.Y H:i", strtotime($t->tarih)) ?></td>
                                    <td><?= ($t->durum == 'acik'
                                        ? "<span class='badge badge-open'>Open</span>"
                                        : "<span class='badge badge-closed'>Closed</span>") ?></td>
                                    <td><a href="destek-goster-admin.php?id=<?= htmlspecialchars($t->id) ?>" class="btn btn-sm btn-outline-info">👁️ View</a></td>
                                </tr>
                        <?php } } else { ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">⚠️ No tickets found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <h4 class="mb-3" style="color:var(--neon1);">🎫 Ticket #<?= htmlspecialchars($ticket->id) ?></h4>
            <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
            <?php if (!empty($error)) echo "<div class='alert alert-warning'>$error</div>"; ?>
            
            <div class="mb-3">
                <p><strong>Subject:</strong> <?= htmlspecialchars($ticket->konu) ?></p>
                <p><strong>User:</strong> <?= htmlspecialchars($ticket->username ?? 'Unknown') ?></p>
                <p><strong>Date:</strong> <?= date("d.m.Y H:i", strtotime($ticket->tarih)) ?></p>
                <p><strong>Status:</strong> 
                    <?= $ticket->durum == 'acik' ? '<span class="badge badge-open">Open</span>' : '<span class="badge badge-closed">Closed</span>' ?>
                </p>
                <p><strong>Message:</strong></p>
                <div class="p-3 bg-dark rounded"><?= nl2br(htmlspecialchars($ticket->mesaj)) ?></div>
            </div>

            <?php if ($replies) { ?>
                <h5 class="mb-3" style="color:var(--neon1);">💬 Replies</h5>
                <?php foreach ($replies as $reply) { ?>
                    <div class="mb-3 p-3 rounded <?= $reply->is_admin ? 'reply-admin' : 'reply-user' ?>">
                        <p><strong><?= htmlspecialchars($reply->username ?? 'Unknown') ?> (<?= $reply->is_admin ? 'Admin' : 'User' ?>)</strong> - <?= date("d.m.Y H:i", strtotime($reply->tarih)) ?></p>
                        <p><?= nl2br(htmlspecialchars($reply->mesaj)) ?></p>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <p class="text-muted">No replies yet.</p>
            <?php } ?>

            <h5 class="mb-3" style="color:var(--neon1);">✍️ Add Reply</h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="reply">
                <div class="mb-3">
                    <label class="form-label">Reply</label>
                    <textarea name="mesaj" class="form-control bg-dark text-light border-0" rows="4" maxlength="5000" required></textarea>
                </div>
                <button class="btn btn-neon px-4">📨 Submit Reply</button>
            </form>

            <h5 class="mb-3 mt-4" style="color:var(--neon1);">⚙️ Update Status</h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="update_status">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="durum" class="form-select bg-dark text-light border-0">
                        <option value="acik" <?= $ticket->durum == 'acik' ? 'selected' : '' ?>>Open</option>
                        <option value="kapali" <?= $ticket->durum == 'kapali' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <button class="btn btn-neon px-4">✅ Update</button>
            </form>
            <a href="destek-goster-admin.php" class="btn btn-outline-info mt-3">🔙 Back to Tickets</a>
        <?php } ?>
    </div>
</div>
<footer>© <?= date('Y') ?> HackLink Panel</footer>
</body>
</html>