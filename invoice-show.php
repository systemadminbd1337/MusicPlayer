<?php
include "header.php";

// Error logging function
function logInvoiceError($message) {
    $logFile = 'faturalarerror.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] INVOICE_SHOW - $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Start session for security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    logInvoiceError("Invalid invoice ID requested: $id");
    echo "<div class='container my-4'><div class='alert alert-danger'>❌ Invalid invoice ID.</div></div>";
    include "footer.php"; 
    exit;
}

// Debug user information
logInvoiceError("User ID: " . (isset($user->id) ? $user->id : 'User not defined'));
logInvoiceError("Requesting invoice ID: $id");

try {
    // First, let's check if user is properly authenticated
    if (!isset($user->id) || empty($user->id)) {
        throw new Exception("User not authenticated or user ID not found");
    }

    // Debug: Check database connection
    if (!isset($db)) {
        throw new Exception("Database connection not established");
    }

    // Sanitize inputs for ezSQL
    $user_id = $db->escape($user->id);
    $invoice_id = $db->escape($id);

    // Let's first check the table structure to get correct column names
    logInvoiceError("Checking table structure...");
    
    // Check k_paketler table columns
    $package_columns = $db->get_results("SHOW COLUMNS FROM k_paketler");
    $package_fields = [];
    foreach ($package_columns as $col) {
        $package_fields[] = $col->Field;
    }
    logInvoiceError("k_paketler columns: " . implode(', ', $package_fields));
    
    // Check k_faturalar table columns  
    $invoice_columns = $db->get_results("SHOW COLUMNS FROM k_faturalar");
    $invoice_fields = [];
    foreach ($invoice_columns as $col) {
        $invoice_fields[] = $col->Field;
    }
    logInvoiceError("k_faturalar columns: " . implode(', ', $invoice_fields));

    // Build query based on actual column names
    $select_fields = [
        "f.id as invoice_no",
        "f.tutar as amount", 
        "f.tarih as date",
        "f.durum as status",
        "f.odeme_yontemi as payment_method",
        "f.son_odeme_tarihi as due_date"
    ];

    // Add package fields if they exist
    if (in_array('baslik', $package_fields)) {
        $select_fields[] = "p.baslik as package_name";
    } else {
        $select_fields[] = "NULL as package_name";
    }
    
    // Check for description column - try common names
    if (in_array('aciklama', $package_fields)) {
        $select_fields[] = "p.aciklama as package_description";
    } elseif (in_array('description', $package_fields)) {
        $select_fields[] = "p.description as package_description";
    } elseif (in_array('paket_aciklama', $package_fields)) {
        $select_fields[] = "p.paket_aciklama as package_description";
    } else {
        $select_fields[] = "NULL as package_description";
    }

    $query = "
        SELECT " . implode(", ", $select_fields) . "
        FROM k_faturalar f
        LEFT JOIN k_paketler p ON f.paket_id = p.id
        WHERE f.id = '$invoice_id' AND f.uid = '$user_id'
        LIMIT 1
    ";
    
    logInvoiceError("Final query: " . $query);
    
    $invoice = $db->get_row($query);

    if (!$invoice) {
        logInvoiceError("Invoice not found or access denied - Invoice ID: $id, User ID: $user->id");
        
        // Let's check if invoice exists but user doesn't have access
        $checkInvoice = $db->get_row("SELECT id FROM k_faturalar WHERE id = '$invoice_id' LIMIT 1");
        if ($checkInvoice) {
            logInvoiceError("Invoice exists but user doesn't have permission");
            echo "<div class='container my-4'><div class='alert alert-warning'>⚠️ Access denied to this invoice.</div></div>";
        } else {
            logInvoiceError("Invoice does not exist in database");
            echo "<div class='container my-4'><div class='alert alert-warning'>⚠️ Invoice not found.</div></div>";
        }
        include "footer.php"; 
        exit;
    }

    logInvoiceError("Invoice found successfully - ID: $id, Amount: " . $invoice->amount);

} catch (Exception $e) {
    logInvoiceError("Database error: " . $e->getMessage());
    echo "<div class='container my-4'><div class='alert alert-danger'>❌ System error occurred: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    include "footer.php"; 
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>🧾 Invoice #<?= htmlspecialchars($invoice->invoice_no) ?> - HackLink Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg: #050510;
        --panel: #0a0920;
        --neon1: #00e6ff;
        --neon2: #00ff9d;
        --accent: #facc15;
        --danger: #ff4757;
    }
    body {
        background: radial-gradient(900px 300px at 10% 10%, rgba(0,230,255,.03), transparent 50%),
                    radial-gradient(700px 300px at 90% 90%, rgba(0,255,157,.02), transparent 50%), var(--bg);
        color: #e7edf8;
        font-family: 'Share Tech Mono', monospace;
        min-height: 100vh;
    }
    .invoice-header {
        background: linear-gradient(135deg, rgba(0,230,255,.1), rgba(0,255,157,.1));
        border: 1px solid rgba(0,255,157,.2);
        border-radius: 15px;
        backdrop-filter: blur(10px);
    }
    .invoice-card {
        background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
        border: 1px solid rgba(0,255,157,.1);
        border-radius: 12px;
        box-shadow: 0 0 20px rgba(0,255,157,.08);
        backdrop-filter: blur(10px);
    }
    .detail-item {
        border-bottom: 1px solid rgba(255,255,255,.1);
        padding: 15px 0;
        transition: all 0.3s ease;
    }
    .detail-item:hover {
        background: rgba(255,255,255,.02);
        border-radius: 8px;
        padding-left: 10px;
    }
    .detail-item:last-child {
        border-bottom: none;
    }
    .status-badge {
        font-size: 14px;
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: 700;
    }
    .badge-paid {
        background: linear-gradient(45deg, var(--neon2), var(--neon1));
        color: #000;
    }
    .badge-pending {
        background: linear-gradient(45deg, var(--accent), #ffb142);
        color: #000;
    }
    .badge-cancelled {
        background: linear-gradient(45deg, var(--danger), #ff6b81);
        color: #fff;
    }
    .action-btn {
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-back {
        background: rgba(255,255,255,.05);
        color: var(--neon1);
        border: 1px solid var(--neon1);
    }
    .btn-back:hover {
        background: var(--neon1);
        color: #000;
        box-shadow: 0 0 15px rgba(0,230,255,.5);
    }
    .btn-download {
        background: rgba(0,255,157,.1);
        color: var(--neon2);
        border: 1px solid var(--neon2);
    }
    .btn-download:hover {
        background: var(--neon2);
        color: #000;
        box-shadow: 0 0 15px rgba(0,255,157,.5);
    }
    .btn-print {
        background: rgba(252, 92, 101, 0.1);
        color: #fc5c65;
        border: 1px solid #fc5c65;
    }
    .btn-print:hover {
        background: #fc5c65;
        color: #fff;
        box-shadow: 0 0 15px rgba(252, 92, 101, 0.5);
    }
    .invoice-title {
        font-family: 'Orbitron', sans-serif;
        color: var(--neon1);
        text-shadow: 0 0 10px rgba(0,230,255,.5);
    }
    .amount-display {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(45deg, var(--neon1), var(--neon2));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 0 20px rgba(0,255,157,.3);
    }
    .info-icon {
        color: var(--neon1);
        width: 20px;
        text-align: center;
    }
    .timeline-item {
        position: relative;
        padding-left: 30px;
        margin-bottom: 20px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--neon2);
    }
    .timeline-item::after {
        content: '';
        position: absolute;
        left: 5px;
        top: 17px;
        width: 2px;
        height: calc(100% + 3px);
        background: rgba(0,255,157,.3);
    }
    .timeline-item:last-child::after {
        display: none;
    }
    </style>
</head>
<body>
<div class="container my-5">
    <!-- Header Section -->
    <div class="invoice-header p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="invoice-title mb-2">🧾 INVOICE DETAILS</h1>
                <p class="mb-0 text-light">Invoice #<?= htmlspecialchars($invoice->invoice_no) ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="amount-display"><?= number_format($invoice->amount, 2) ?> ৳</div>
                <?php
                $status_class = '';
                $status_text = '';
                switch ($invoice->status) {
                    case 1:
                        $status_class = 'badge-paid';
                        $status_text = 'PAID';
                        break;
                    case 0:
                        $status_class = 'badge-pending';
                        $status_text = 'PENDING';
                        break;
                    case -1:
                        $status_class = 'badge-cancelled';
                        $status_text = 'CANCELLED';
                        break;
                    default:
                        $status_class = 'badge-pending';
                        $status_text = 'UNKNOWN';
                }
                ?>
                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Invoice Details -->
        <div class="col-lg-8">
            <div class="invoice-card p-4 mb-4">
                <h4 class="mb-4" style="color: var(--neon1);">
                    <i class="fas fa-receipt"></i> Invoice Information
                </h4>
                
                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-hashtag info-icon"></i> <strong>Invoice Number</strong></div>
                        <div class="col-sm-8">#<?= htmlspecialchars($invoice->invoice_no) ?></div>
                    </div>
                </div>

                <?php if (!empty($invoice->package_name)): ?>
                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-cube info-icon"></i> <strong>Package Name</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($invoice->package_name) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-dollar-sign info-icon"></i> <strong>Amount</strong></div>
                        <div class="col-sm-8" style="color: var(--neon2); font-weight: 700;">
                            <?= number_format($invoice->amount, 2) ?> ৳
                        </div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-calendar info-icon"></i> <strong>Invoice Date</strong></div>
                        <div class="col-sm-8"><?= date("F d, Y H:i", strtotime($invoice->date)) ?></div>
                    </div>
                </div>

                <?php if (isset($invoice->due_date) && $invoice->due_date && $invoice->due_date != '0000-00-00'): ?>
                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-clock info-icon"></i> <strong>Due Date</strong></div>
                        <div class="col-sm-8"><?= date("F d, Y", strtotime($invoice->due_date)) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($invoice->payment_method) && $invoice->payment_method): ?>
                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-credit-card info-icon"></i> <strong>Payment Method</strong></div>
                        <div class="col-sm-8"><?= htmlspecialchars($invoice->payment_method) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="detail-item">
                    <div class="row">
                        <div class="col-sm-4"><i class="fas fa-info-circle info-icon"></i> <strong>Status</strong></div>
                        <div class="col-sm-8">
                            <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions & Timeline -->
        <div class="col-lg-4">
            <!-- Action Buttons -->
            <div class="invoice-card p-4 mb-4">
                <h5 class="mb-3" style="color: var(--neon1);">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
                <div class="d-grid gap-2">
                    <a href="faturalar.php" class="action-btn btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Invoices
                    </a>
                    <a href="invoice-download.php?id=<?= $invoice->invoice_no ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                       class="action-btn btn-download">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                    <button onclick="window.print()" class="action-btn btn-print">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                </div>
            </div>

            <!-- Invoice Timeline -->
            <div class="invoice-card p-4">
                <h5 class="mb-3" style="color: var(--neon1);">
                    <i class="fas fa-history"></i> Invoice Timeline
                </h5>
                <div class="timeline-item">
                    <strong>Invoice Created</strong>
                    <div class="text-muted small"><?= date("M d, Y H:i", strtotime($invoice->date)) ?></div>
                </div>
                <?php if ($invoice->status == 1): ?>
                <div class="timeline-item">
                    <strong>Payment Completed</strong>
                    <div class="text-muted small"><?= date("M d, Y H:i", strtotime($invoice->date) + 3600) ?></div>
                </div>
                <?php elseif ($invoice->status == 0): ?>
                <div class="timeline-item">
                    <strong>Awaiting Payment</strong>
                    <div class="text-muted small">Pending</div>
                </div>
                <?php elseif ($invoice->status == -1): ?>
                <div class="timeline-item">
                    <strong>Invoice Cancelled</strong>
                    <div class="text-muted small"><?= date("M d, Y H:i", strtotime($invoice->date) + 1800) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Additional Information -->
    <?php if (isset($invoice->package_description) && $invoice->package_description): ?>
    <div class="invoice-card p-4 mt-4">
        <h5 class="mb-3" style="color: var(--neon1);">
            <i class="fas fa-file-alt"></i> Package Description
        </h5>
        <p class="mb-0"><?= nl2br(htmlspecialchars($invoice->package_description)) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Print Styles -->
<style media="print">
    body { background: white !important; color: black !important; }
    .invoice-header, .invoice-card { 
        background: white !important; 
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    .action-btn { display: none !important; }
    .status-badge { background: #333 !important; color: white !important; }
    .amount-display { color: black !important; }
</style>

<script>
// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Add loading animation to buttons
    const buttons = document.querySelectorAll('.action-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 1500);
        });
    });
});
</script>

<?php include "footer.php"; ?>
</body>
</html>