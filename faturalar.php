<?php
// Start output buffering to prevent header errors
ob_start();
include "header.php";

// Enhanced error logging
function logInvoiceDebug($message, $data = null) {
    $logFile = 'fu.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= " | " . (is_array($data) ? json_encode($data) : $data);
    }
    
    $logMessage .= PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

logInvoiceDebug("=== FATURALAR.PHP STARTED ===");
logInvoiceDebug("User ID from session", $user->id ?? 'NOT FOUND');

// Fix k_faturalar table structure first
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS k_faturalar (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            uid INT NOT NULL,
            paket_id INT DEFAULT 0,
            tutar DECIMAL(10,2) DEFAULT 0,
            durum TINYINT(1) DEFAULT 0,
            tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_uid (uid),
            INDEX idx_durum (durum)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;
    ");
    
    // Fix AUTO_INCREMENT if needed
    $check_auto_inc = $db->get_var("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = 'k_faturalar' AND TABLE_SCHEMA = DATABASE()");
    if (!$check_auto_inc || $check_auto_inc == 0) {
        $db->query("ALTER TABLE k_faturalar AUTO_INCREMENT = 1");
        logInvoiceDebug("Fixed AUTO_INCREMENT", "Set to 1");
    }
} catch (Throwable $e) {
    logInvoiceDebug("Table creation error", $e->getMessage());
}

// Simple auto invoice creator function - FIXED VERSION
function createAutoInvoice($user_id, $amount, $package_id = 0) {
    global $db;
    
    $user_id = (int)$user_id;
    $amount = floatval($amount);
    $package_id = (int)$package_id;
    
    logInvoiceDebug("Creating invoice - User: $user_id, Amount: $amount, Package: $package_id");
    
    if ($user_id > 0 && $amount > 0) {
        try {
            // First get the next available ID
            $max_id = $db->get_var("SELECT COALESCE(MAX(id), 0) FROM k_faturalar");
            $next_id = $max_id + 1;
            
            logInvoiceDebug("Next available ID", $next_id);
            
            // Insert with explicit ID to avoid AUTO_INCREMENT issues
            $result = $db->query("
                INSERT INTO k_faturalar (id, uid, paket_id, tutar, durum, tarih) 
                VALUES ('$next_id', '$user_id', '$package_id', '$amount', 0, NOW())
            ");
            
            if ($result) {
                $inserted_id = $db->insert_id ?: $next_id;
                logInvoiceDebug("✅ Invoice created successfully", "ID: $inserted_id");
                return $inserted_id;
            } else {
                logInvoiceDebug("❌ Database error", $db->last_error);
            }
        } catch (Throwable $e) {
            logInvoiceDebug("❌ Exception in invoice creation", $e->getMessage());
        }
    } else {
        logInvoiceDebug("❌ Invalid user ID or amount");
    }
    return false;
}

// Check if invoice creation is requested - NO REDIRECT VERSION
$show_success_msg = false;
$show_error_msg = false;
$success_message = '';
$error_message = '';

if (isset($_POST['create_invoice'])) {
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
    
    if ($amount > 0) {
        $new_invoice_id = createAutoInvoice($user->id, $amount, $package_id);
        
        if ($new_invoice_id) {
            $show_success_msg = true;
            $success_message = "✅ Invoice #$new_invoice_id created successfully!";
            
            // Refresh the data to show new invoice
            $totalInvoices = (int)$db->get_var("SELECT COUNT(*) FROM k_faturalar WHERE uid='$uid'");
            $invoiceList = $db->get_results("
                SELECT id AS invoice_no, tutar AS amount, tarih AS date, durum AS status,
                       'Service Package' AS package_name
                FROM k_faturalar 
                WHERE uid='$uid'
                ORDER BY id DESC
                LIMIT 20
            ");
        } else {
            $show_error_msg = true;
            $error_message = "❌ Failed to create invoice. Check fu.txt for details.";
        }
    }
}

// Get user invoices with FIXED query
$uid = (int)$user->id;
logInvoiceDebug("Loading invoices for user ID: $uid");

try {
    // First check table structure
    $table_check = $db->get_results("SHOW COLUMNS FROM k_faturalar");
    logInvoiceDebug("Table structure check", count($table_check) . " columns found");
    
    $totalInvoices = (int)$db->get_var("SELECT COUNT(*) FROM k_faturalar WHERE uid='$uid'");
    logInvoiceDebug("Total invoices found", $totalInvoices);
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = $limit * ($page - 1);
    
    // Simple query without JOIN to avoid issues
    $invoiceList = $db->get_results("
        SELECT id AS invoice_no, tutar AS amount, tarih AS date, durum AS status,
               'Service Package' AS package_name
        FROM k_faturalar 
        WHERE uid='$uid'
        ORDER BY id DESC
        LIMIT $offset, $limit
    ");
    
    logInvoiceDebug("Invoices retrieved", count($invoiceList));
    
} catch (Exception $e) {
    logInvoiceDebug("❌ EXCEPTION: " . $e->getMessage());
    $totalInvoices = 0;
    $invoiceList = [];
}

// End output buffering and send output
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>📑 Invoices - HackLink Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --neon1: #00e6ff;
    --neon2: #00ff9d;
}
body {
    background: #0b0a16;
    color: #e7edf8;
    font-family: 'Inter', sans-serif;
}
.cardx {
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(0,255,157,.1);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.quick-invoice {
    background: rgba(0,255,157,.05);
    border: 1px solid rgba(0,255,157,.2);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}
.btn-neon {
    background: rgba(0,255,157,.1);
    border: 1px solid rgba(0,255,157,.3);
    color: #00ff9d;
    transition: all 0.3s;
}
.btn-neon:hover {
    background: rgba(0,255,157,.3);
    color: #000;
    box-shadow: 0 0 15px rgba(0,255,157,.4);
}
.badge-paid { background: #00ff9d; color: #000; }
.badge-pending { background: #facc15; color: #000; }
.badge-cancelled { background: #ff4757; color: #fff; }
.table-dark th {
    color: var(--neon1);
}
.alert-auto-close {
    animation: fadeOut 5s forwards;
}
@keyframes fadeOut {
    0% { opacity: 1; }
    80% { opacity: 1; }
    100% { opacity: 0; display: none; }
}
</style>
</head>
<body>

<div class="container my-4">
    <div class="cardx">
        <h4 class="mb-3" style="color:var(--neon1);">📑 Invoice History</h4>

        <!-- Quick Invoice Creator -->
        <div class="quick-invoice">
            <h5 style="color: #00ff9d;">➕ Quick Invoice Creator</h5>
            <form method="post" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <input type="number" name="amount" class="form-control" 
                           placeholder="Amount" step="0.01" min="1" required 
                           style="background: rgba(255,255,255,.1); color: white; border: 1px solid rgba(255,255,255,.2);">
                </div>
                <div class="col-md-4">
                    <select name="package_id" class="form-control" 
                            style="background: rgba(255,255,255,.1); color: white; border: 1px solid rgba(255,255,255,.2);">
                        <option value="0">Custom Service</option>
                        <?php
                        $packages = $db->get_results("SELECT id, baslik FROM k_paketler LIMIT 10");
                        foreach ($packages as $pkg) {
                            echo "<option value='{$pkg->id}'>{$pkg->baslik}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="create_invoice" class="btn btn-neon w-100">
                        🧾 Create Test Invoice
                    </button>
                </div>
            </form>
        </div>

        <!-- Show success/error messages -->
        <?php if ($show_success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show alert-auto-close" id="successAlert">
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($show_error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($totalInvoices < 1) { ?>
            <div class="alert alert-info text-center">
                <h5>📭 No Invoices Found</h5>
                <p>No invoices found for your account.</p>
                <p><small class="text-muted">User ID: <?= $uid ?></small></p>
                <div class="mt-3">
                    <p>Use the form above to create your first invoice!</p>
                    <a href="paketler.php" class="btn btn-primary">📦 Browse Packages</a>
                </div>
            </div>
        <?php } else { ?>
            <div class="alert alert-success">
                ✅ Found <strong><?= $totalInvoices ?></strong> invoices for your account
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice No</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $serial = $offset + 1;
                        foreach ($invoiceList as $invoice) { 
                            $status_badge = '';
                            switch ($invoice->status) {
                                case 1: $status_badge = 'badge-paid'; $status_text = 'Paid'; break;
                                case 0: $status_badge = 'badge-pending'; $status_text = 'Pending'; break;
                                case -1: $status_badge = 'badge-cancelled'; $status_text = 'Cancelled'; break;
                                default: $status_badge = 'badge-pending'; $status_text = 'Unknown';
                            }
                        ?>
                            <tr>
                                <td><?= $serial++ ?></td>
                                <td><strong>#<?= $invoice->invoice_no ?></strong></td>
                                <td><?= htmlspecialchars($invoice->package_name) ?></td>
                                <td style="color: #00ff9d; font-weight: 700;">
                                    <?= number_format($invoice->amount, 2) ?> ৳
                                </td>
                                <td><?= date("d.m.Y H:i", strtotime($invoice->date)) ?></td>
                                <td>
                                    <span class="badge <?= $status_badge ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <a href="invoice-show.php?id=<?= $invoice->invoice_no ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        👁️ View
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Simple Pagination -->
            <?php if ($totalInvoices > $limit) { ?>
                <div class="d-flex justify-content-between mt-3">
                    <?php if ($page > 1) { ?>
                        <a href="faturalar.php?page=<?= $page - 1 ?>" class="btn btn-neon">← Previous</a>
                    <?php } else { ?>
                        <span></span>
                    <?php } ?>
                    
                    <span class="text-muted">Page <?= $page ?> of <?= ceil($totalInvoices / $limit) ?></span>
                    
                    <?php if ($page < ceil($totalInvoices / $limit)) { ?>
                        <a href="faturalar.php?page=<?= $page + 1 ?>" class="btn btn-neon">Next →</a>
                    <?php } else { ?>
                        <span></span>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } ?>

        <!-- Debug Info -->
        <div class="mt-4 p-3 bg-dark rounded">
            <small class="text-muted">
                <strong>System Info:</strong><br>
                User ID: <?= $uid ?> | 
                Total Invoices: <?= $totalInvoices ?> | 
                Last Updated: <?= date('H:i:s') ?>
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto close success message after 5 seconds
setTimeout(function() {
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        successAlert.style.display = 'none';
    }
}, 5000);

// Refresh page when coming back from other tabs
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Page became visible, you could add a refresh here if needed
    }
});
</script>
</body>
</html>