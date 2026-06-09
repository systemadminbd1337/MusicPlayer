<?php
// admin/payments.php
include __DIR__ . "/_bootstrap.php";
global $db, $pdo;

// ✅ Admin check
if (($user->role ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// --- Fix k_faturalar table structure if needed ---
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS k_faturalar (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            uid INT NOT NULL,
            paket_id INT NOT NULL,
            tutar DECIMAL(10,2) DEFAULT 0,
            durum TINYINT(1) DEFAULT 0,
            tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_uid (uid),
            INDEX idx_durum (durum)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// --- Pagination Settings ---
$per_page = 10; // 1 page = 10 items
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

// --- Get counts for dashboard ---
$pending_count = $db->get_var("SELECT COUNT(*) FROM k_payments WHERE status='pending'");
$approved_count = $db->get_var("SELECT COUNT(*) FROM k_payments WHERE status='approved'");
$rejected_count = $db->get_var("SELECT COUNT(*) FROM k_payments WHERE status='rejected'");
$total_payments = $db->get_var("SELECT COUNT(*) FROM k_payments");
$total_pages = ceil($total_payments / $per_page);

// --- Get credit statistics ---
$total_credits_issued = $db->get_var("SELECT COALESCE(SUM(amount), 0) FROM k_payments WHERE status='approved'");
$today_credits = $db->get_var("SELECT COALESCE(SUM(amount), 0) FROM k_payments WHERE status='approved' AND DATE(approved_at) = CURDATE()");
$month_credits = $db->get_var("SELECT COALESCE(SUM(amount), 0) FROM k_payments WHERE status='approved' AND MONTH(approved_at) = MONTH(CURDATE()) AND YEAR(approved_at) = YEAR(CURDATE())");

// --- Handle Approve / Reject ---
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $payment = $db->get_row("SELECT * FROM k_payments WHERE id={$id}");

    if ($payment) {
        if ($action === 'approve' && $payment->status === 'pending') {
            $db->query("UPDATE k_payments SET status='approved', approved_at=NOW() WHERE id={$id}");

            // ✅ ইউজারকে ক্রেডিট ও প্যাকেজ যোগ করো
            $uid = (int)$payment->uid;
            $amount = (float)$payment->amount;
            $paket_id = (int)$payment->paket_id;

            // ইউজার ও প্যাকেজ ডাটা বের করো
            $userData  = $db->get_row("SELECT id, username, kredi FROM k_users WHERE id={$uid}");
            $paketData = $db->get_row("SELECT id, baslik, kota, sure FROM k_paketler WHERE id={$paket_id}");

            if ($userData && $paketData) {
                // 💰 ক্রেডিট যোগ (1 USD = 1 Credit)
                $credits_to_add = (int)$amount; // Since 1 USD = 1 Credit
                $db->query("UPDATE k_users SET kredi = kredi + {$credits_to_add} WHERE id={$uid}");

                // 🎯 প্যাকেজ সেট করো (যদি প্রয়োজন হয়)
                $expire = date('Y-m-d H:i:s', strtotime("+{$paketData->sure} days"));
                $db->query("
                    UPDATE k_users 
                    SET paket_id = {$paketData->id},
                        kota = {$paketData->kota},
                        paket_expire = '{$expire}'
                    WHERE id = {$uid}
                ");

                // ✅ AUTO INVOICE CREATION - FIXED AUTO_INCREMENT ISSUE
                try {
                    $invoice_result = $db->query("
                        INSERT INTO k_faturalar (uid, paket_id, tutar, durum, tarih) 
                        VALUES ('$uid', '$paket_id', '$amount', 1, NOW())
                    ");

                    if ($invoice_result) {
                        $invoice_id = $db->insert_id;
                        $invoice_msg = " & Invoice #{$invoice_id} created";
                    } else {
                        $invoice_msg = " & Invoice creation failed";
                    }
                } catch (Throwable $e) {
                    error_log("Invoice creation error: " . $e->getMessage());
                    // Alternative method if AUTO_INCREMENT still fails
                    $max_id = $db->get_var("SELECT COALESCE(MAX(id), 0) + 1 FROM k_faturalar");
                    $invoice_result = $db->query("
                        INSERT INTO k_faturalar (id, uid, paket_id, tutar, durum, tarih) 
                        VALUES ('$max_id', '$uid', '$paket_id', '$amount', 1, NOW())
                    ");
                    $invoice_msg = $invoice_result ? " & Invoice #{$max_id} created" : " & Invoice failed";
                }

                // লগ রেকর্ড
                $reason = "Payment approved — {$paketData->baslik} ({$credits_to_add} Credits)";
                try {
                    $stmt = $pdo->prepare("INSERT INTO k_credits_log (uid, delta, reason, admin_id, created_at)
                                           VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$uid, $credits_to_add, $reason, $user->id]);
                } catch (Throwable $e) {
                    error_log("Credit log insert failed: " . $e->getMessage());
                }
            }

            $msg = "✅ Payment #{$id} approved — {$credits_to_add} Credits added successfully.{$invoice_msg}";
        } 
        elseif ($action === 'reject' && $payment->status === 'pending') {
            $db->query("UPDATE k_payments SET status='rejected', approved_at=NOW() WHERE id={$id}");
            
            // ✅ REJECT করলেও CANCELLED ইনভয়েস তৈরি
            $uid = (int)$payment->uid;
            $amount = (float)$payment->amount;
            $paket_id = (int)$payment->paket_id;
            
            try {
                $max_id = $db->get_var("SELECT COALESCE(MAX(id), 0) + 1 FROM k_faturalar");
                $invoice_result = $db->query("
                    INSERT INTO k_faturalar (id, uid, paket_id, tutar, durum, tarih) 
                    VALUES ('$max_id', '$uid', '$paket_id', '$amount', -1, NOW())
                ");
                
                $reject_msg = $invoice_result ? " & Invoice created (Cancelled)" : "";
            } catch (Throwable $e) {
                error_log("Reject invoice error: " . $e->getMessage());
                $reject_msg = "";
            }
            
            $msg = "❌ Payment #{$id} rejected.{$reject_msg}";
        }

        header("Location: payments.php?page={$current_page}&msg=" . urlencode($msg));
        exit;
    }
}

// --- Fetch Payments with Pagination ---
$rows = $db->get_results("
    SELECT p.*, u.username, pk.baslik AS paket_name, pk.fiyat
    FROM k_payments p
    LEFT JOIN k_users u ON u.id = p.uid
    LEFT JOIN k_paketler pk ON pk.id = p.paket_id
    ORDER BY p.created_at DESC
    LIMIT $offset, $per_page
");

// Function to generate pagination
function generatePagination($current_page, $total_pages, $url = 'payments.php') {
    if ($total_pages <= 1) return '';
    
    $html = '<nav class="pagination-wrapper"><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page - 1) . '"><i class="fas fa-chevron-left"></i> PREV</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> PREV</span></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=1">1</a></li>';
        if ($start_page > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page + 1) . '">NEXT <i class="fas fa-chevron-right"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">NEXT <i class="fas fa-chevron-right"></i></span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>💰 Payments — Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root {
    --neon-green: #00ff9d;
    --neon-blue: #00e6ff;
    --neon-purple: #9d4edd;
    --neon-red: #ff4757;
    --neon-yellow: #facc15;
    --neon-gold: #ffd700;
    --dark-bg: #050510;
    --panel-bg: #0a0920;
    --accent-bg: #0f0e2a;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: var(--dark-bg);
    color: #e6eef8;
    font-family: 'Share Tech Mono', monospace;
    background-image: 
        radial-gradient(circle at 10% 20%, rgba(0, 255, 157, 0.05) 0%, transparent 20%),
        radial-gradient(circle at 90% 80%, rgba(0, 230, 255, 0.03) 0%, transparent 20%),
        linear-gradient(45deg, var(--dark-bg) 0%, var(--panel-bg) 100%);
    min-height: 100vh;
    overflow-x: hidden;
}

/* 🔥 Header Styles */
.admin-header {
    background: linear-gradient(135deg, rgba(0, 255, 157, 0.1), rgba(0, 230, 255, 0.05));
    border-bottom: 1px solid rgba(0, 255, 157, 0.2);
    backdrop-filter: blur(10px);
    padding: 15px 0;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.admin-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 157, 0.1), transparent);
    animation: scan 3s linear infinite;
}

@keyframes scan {
    0% { left: -100%; }
    100% { left: 100%; }
}

.header-title {
    font-family: 'Orbitron', sans-serif;
    font-weight: 900;
    font-size: 2.5rem;
    background: linear-gradient(45deg, var(--neon-green), var(--neon-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 0 30px rgba(0, 255, 157, 0.5);
    text-transform: uppercase;
    letter-spacing: 2px;
}

/* 💎 Professional Credit Dashboard */
.credit-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.credit-card {
    background: linear-gradient(135deg, var(--panel-bg), var(--accent-bg));
    border: 1px solid;
    border-radius: 20px;
    padding: 25px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.credit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--neon-gold), #ffb700);
}

.credit-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(255, 215, 0, 0.2);
}

.credit-card.total {
    border-color: var(--neon-gold);
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 183, 0, 0.05));
}

.credit-card.today {
    border-color: var(--neon-green);
    background: linear-gradient(135deg, rgba(0, 255, 157, 0.1), rgba(0, 230, 255, 0.05));
}

.credit-card.month {
    border-color: var(--neon-blue);
    background: linear-gradient(135deg, rgba(0, 230, 255, 0.1), rgba(0, 150, 255, 0.05));
}

.credit-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 15px;
    position: relative;
}

.credit-card.total .credit-icon {
    background: linear-gradient(135deg, var(--neon-gold), #ffb700);
    color: #000;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
}

.credit-card.today .credit-icon {
    background: linear-gradient(135deg, var(--neon-green), var(--neon-blue));
    color: #000;
    box-shadow: 0 0 20px rgba(0, 255, 157, 0.4);
}

.credit-card.month .credit-icon {
    background: linear-gradient(135deg, var(--neon-blue), #0099ff);
    color: #000;
    box-shadow: 0 0 20px rgba(0, 230, 255, 0.4);
}

.credit-amount {
    font-size: 2.2rem;
    font-weight: 900;
    font-family: 'Orbitron', sans-serif;
    margin-bottom: 5px;
    background: linear-gradient(45deg, #fff, #e6eef8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
}

.credit-card.total .credit-amount {
    background: linear-gradient(45deg, var(--neon-gold), #ffb700);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.credit-label {
    color: #9ca3af;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.credit-subtext {
    font-size: 0.8rem;
    color: var(--neon-green);
    background: rgba(0, 255, 157, 0.1);
    padding: 4px 8px;
    border-radius: 10px;
    display: inline-block;
}

/* 💰 Credit Badge */
.credit-badge {
    background: linear-gradient(45deg, var(--neon-gold), #ffb700);
    color: #000;
    font-weight: 900;
    padding: 8px 15px;
    border-radius: 15px;
    font-size: 0.85rem;
    border: 2px solid rgba(255, 215, 0, 0.3);
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.4);
    font-family: 'Orbitron', sans-serif;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.credit-badge::before {
    content: '🎯';
    font-size: 1rem;
}

/* 📊 Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, var(--panel-bg), var(--accent-bg));
    border: 1px solid rgba(0, 255, 157, 0.1);
    border-radius: 15px;
    padding: 25px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--neon-green), var(--neon-blue));
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--neon-green);
    box-shadow: 0 10px 30px rgba(0, 255, 157, 0.2);
}

.stat-card.pending::before { background: var(--neon-yellow); }
.stat-card.approved::before { background: var(--neon-green); }
.stat-card.rejected::before { background: var(--neon-red); }

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    font-family: 'Orbitron', sans-serif;
    margin-bottom: 5px;
}

.stat-card.pending .stat-number { color: var(--neon-yellow); }
.stat-card.approved .stat-number { color: var(--neon-green); }
.stat-card.rejected .stat-number { color: var(--neon-red); }

.stat-label {
    color: #9ca3af;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* 🎮 Navigation */
.hacker-nav {
    background: rgba(10, 9, 32, 0.8);
    border: 1px solid rgba(0, 255, 157, 0.2);
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 25px;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(0, 255, 157, 0.1);
}

.nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
}

.nav-item {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
    border: 1px solid rgba(0, 255, 157, 0.1);
    border-radius: 10px;
    padding: 12px 8px;
    text-align: center;
    text-decoration: none;
    color: #e6eef8;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.nav-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 157, 0.2), transparent);
    transition: left 0.5s;
}

.nav-item:hover::before {
    left: 100%;
}

.nav-item:hover {
    transform: translateY(-3px);
    border-color: var(--neon-green);
    box-shadow: 0 5px 20px rgba(0, 255, 157, 0.3);
    color: var(--neon-green);
}

.nav-item.active {
    background: linear-gradient(135deg, rgba(0, 255, 157, 0.2), rgba(0, 230, 255, 0.1));
    border-color: var(--neon-green);
    color: var(--neon-green);
    box-shadow: 0 0 20px rgba(0, 255, 157, 0.4);
}

/* 🎯 Main Content */
.content-panel {
    background: linear-gradient(135deg, var(--panel-bg), var(--accent-bg));
    border: 1px solid rgba(0, 255, 157, 0.15);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(10px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
    position: relative;
    overflow: hidden;
}

.content-panel::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--neon-green), transparent);
}

/* 📋 Table Styles */
.hacker-table {
    background: transparent;
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.hacker-table thead th {
    background: linear-gradient(135deg, rgba(0, 255, 157, 0.1), rgba(0, 230, 255, 0.05));
    border: none;
    color: var(--neon-blue);
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 20px 15px;
    font-size: 0.9rem;
}

.hacker-table tbody tr {
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(0, 255, 157, 0.05);
    transition: all 0.3s ease;
}

.hacker-table tbody tr:hover {
    background: rgba(0, 255, 157, 0.05);
    transform: translateX(5px);
}

.hacker-table tbody td {
    border: none;
    padding: 15px;
    vertical-align: middle;
    color: #e6eef8;
}

/* 🏷️ Badge Styles */
.badge {
    font-family: 'Share Tech Mono', monospace;
    font-weight: 700;
    padding: 8px 15px;
    border-radius: 25px;
    border: 1px solid transparent;
    font-size: 0.8rem;
}

.badge-pending {
    background: linear-gradient(45deg, var(--neon-yellow), #fbbf24);
    color: #000;
    border-color: var(--neon-yellow);
    box-shadow: 0 0 15px rgba(250, 204, 21, 0.3);
}

.badge-approved {
    background: linear-gradient(45deg, var(--neon-green), var(--neon-blue));
    color: #000;
    border-color: var(--neon-green);
    box-shadow: 0 0 15px rgba(0, 255, 157, 0.3);
}

.badge-rejected {
    background: linear-gradient(45deg, var(--neon-red), #ff6b81);
    color: #fff;
    border-color: var(--neon-red);
    box-shadow: 0 0 15px rgba(255, 71, 87, 0.3);
}

/* 🔘 Button Styles */
.btn-hacker {
    font-family: 'Share Tech Mono', monospace;
    font-weight: 700;
    padding: 8px 16px;
    border: 1px solid;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.8rem;
    margin: 2px;
}

.btn-approve {
    background: linear-gradient(45deg, var(--neon-green), var(--neon-blue));
    border-color: var(--neon-green);
    color: #000;
}

.btn-approve:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 255, 157, 0.4);
    color: #000;
}

.btn-reject {
    background: linear-gradient(45deg, var(--neon-red), #ff6b81);
    border-color: var(--neon-red);
    color: #fff;
}

.btn-reject:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 71, 87, 0.4);
    color: #fff;
}

/* 📄 Pagination */
.pagination-wrapper {
    margin-top: 30px;
}

.pagination {
    justify-content: center;
    gap: 5px;
}

.page-link {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(0, 255, 157, 0.2);
    color: var(--neon-green);
    padding: 8px 15px;
    border-radius: 8px;
    font-family: 'Share Tech Mono', monospace;
    transition: all 0.3s ease;
}

.page-link:hover {
    background: rgba(0, 255, 157, 0.1);
    border-color: var(--neon-green);
    color: var(--neon-green);
    transform: translateY(-2px);
}

.page-item.active .page-link {
    background: linear-gradient(45deg, var(--neon-green), var(--neon-blue));
    border-color: var(--neon-green);
    color: #000;
    font-weight: 700;
}

.page-item.disabled .page-link {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.1);
    color: #666;
}

/* 🔔 Notification Badge */
.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: linear-gradient(45deg, var(--neon-red), #ff6b81);
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    animation: pulse 2s infinite;
    box-shadow: 0 0 15px rgba(255, 71, 87, 0.7);
    border: 2px solid var(--dark-bg);
}

/* 📱 Responsive */
@media (max-width: 768px) {
    .nav-grid {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    
    .header-title {
        font-size: 1.8rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .content-panel {
        padding: 20px;
    }
    
    .credit-dashboard {
        grid-template-columns: 1fr;
    }
}

/* 🆕 New Payment Highlight */
.new-payment {
    background: rgba(255, 71, 87, 0.05) !important;
    border-left: 3px solid var(--neon-red);
    animation: highlight 3s ease-in-out;
}

@keyframes highlight {
    0% { background: rgba(255, 71, 87, 0.1); }
    100% { background: rgba(255, 71, 87, 0.05); }
}

/* 💬 Alert Styles */
.alert-hacker {
    background: rgba(0, 255, 157, 0.1);
    border: 1px solid var(--neon-green);
    border-radius: 12px;
    color: var(--neon-green);
    backdrop-filter: blur(10px);
}

.txn-code {
    background: rgba(0, 0, 0, 0.3);
    padding: 3px 8px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    border: 1px solid rgba(0, 255, 157, 0.2);
}
</style>
</head>
<body>

<!-- 🔥 Header Section -->
<div class="admin-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="header-title">💰 PAYMENT CONTROL</h1>
                <p class="text-muted mb-0">Admin Dashboard • Financial Operations</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="index.php" class="btn-hacker btn-approve">
                    <i class="fas fa-arrow-left"></i> DASHBOARD
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">

    <!-- 🎮 Navigation Grid -->
    <div class="hacker-nav">
        <div class="nav-grid">
            <a href="index.php" class="nav-item">🏠 HOME</a>
            <a href="users.php" class="nav-item">👥 USERS</a>
            <a href="announcements.php" class="nav-item">📢 ANNOUNCE</a>
            <a href="broken_links.php" class="nav-item">⚠️ BROKEN</a>
            <a href="login_logs.php" class="nav-item">🧾 LOGS</a>
            <a href="monitors.php" class="nav-item">🔗 MONITOR</a>
            <a href="deleted_sites.php" class="nav-item">🚫 REMOVED</a>
            <a href="../index.php" class="nav-item">🌐 FRONT</a>
            <a href="add_link.php" class="nav-item">➕ LINKS</a>
            <a href="auto_jobs.php" class="nav-item">⚙️ JOBS</a>
            <a href="placements.php" class="nav-item">📍 PLACEMENT</a>
            <a href="payments.php" class="nav-item active">
                💳 PAYMENTS
                <?php if ($pending_count > 0): ?>
                    <span class="notification-badge"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="actions.php" class="nav-item">🧩 ACTIONS</a>
            <a href="logout.php" class="nav-item" style="color: var(--neon-red);">🚪 LOGOUT</a>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-hacker mb-4">
            <i class="fas fa-terminal"></i> <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <!-- 💎 Professional Credit Dashboard -->
    <div class="credit-dashboard">
        <div class="credit-card total">
            <div class="credit-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="credit-amount"><?= number_format($total_credits_issued) ?> CR</div>
            <div class="credit-label">Total Credits Issued</div>
            <div class="credit-subtext">All Time • 1 USD = 1 CR</div>
        </div>
        
        <div class="credit-card today">
            <div class="credit-icon">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="credit-amount"><?= number_format($today_credits) ?> CR</div>
            <div class="credit-label">Today's Credits</div>
            <div class="credit-subtext">Real-time • Active Today</div>
        </div>
        
        <div class="credit-card month">
            <div class="credit-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="credit-amount"><?= number_format($month_credits) ?> CR</div>
            <div class="credit-label">This Month</div>
            <div class="credit-subtext">Monthly Revenue • Growing</div>
        </div>
    </div>

    <!-- 📊 Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-number">
                <i class="fas fa-clock"></i> <?= $pending_count ?>
            </div>
            <div class="stat-label">PENDING PAYMENTS</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-number">
                <i class="fas fa-check-circle"></i> <?= $approved_count ?>
            </div>
            <div class="stat-label">APPROVED PAYMENTS</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-number">
                <i class="fas fa-times-circle"></i> <?= $rejected_count ?>
            </div>
            <div class="stat-label">REJECTED PAYMENTS</div>
        </div>
    </div>

    <!-- 💡 System Info -->
    <div class="alert alert-hacker mb-4">
        <strong>💡 CREDIT SYSTEM ACTIVE:</strong> 1 USD = 1 CREDIT • Auto-invoice generation enabled • Real-time monitoring
        <?php if ($pending_count > 0): ?>
            <div class="mt-2">
                <i class="fas fa-bell text-warning"></i> 
                <strong>ALERT: <?= $pending_count ?> PAYMENT(S) AWAITING REVIEW</strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- 🎯 Main Content Panel -->
    <div class="content-panel">
        <div class="table-responsive">
            <table class="table table-dark hacker-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>USER</th>
                        <th>PACKAGE</th>
                        <th>CREDITS</th>
                        <th>CURRENCY</th>
                        <th>AMOUNT</th>
                        <th>TRANSACTION</th>
                        <th>STATUS</th>
                        <th>DATE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!$rows): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="fas fa-database fa-2x mb-3"></i><br>
                            NO PAYMENT RECORDS FOUND
                        </td>
                    </tr>
                <?php else: 
                    $counter = 0;
                    foreach($rows as $r): 
                    $is_new = ($r->status === 'pending' && $counter < 3);
                    $counter++;
                    $credits = (int)$r->fiyat;
                ?>
                    <tr class="<?= $is_new ? 'new-payment' : '' ?>">
                        <td>
                            <strong>#<?= $r->id ?></strong>
                            <?php if ($is_new): ?>
                                <br><span class="badge bg-danger mt-1">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r->username ?? 'N/A') ?></strong>
                        </td>
                        <td><?= htmlspecialchars($r->paket_name ?? 'N/A') ?></td>
                        <td>
                            <span class="credit-badge"><?= $credits ?> CR</span>
                        </td>
                        <td>
                            <strong><?= $r->currency ?></strong>
                        </td>
                        <td>
                            <strong>$<?= number_format($r->amount, 2) ?> USD</strong>
                        </td>
                        <td>
                            <code class="txn-code"><?= htmlspecialchars($r->txn_id) ?></code>
                        </td>
                        <td>
                            <?php if($r->status==='approved'){ ?>
                                <span class="badge badge-approved">
                                    <i class="fas fa-check-circle"></i> APPROVED
                                </span>
                            <?php } elseif($r->status==='rejected'){ ?>
                                <span class="badge badge-rejected">
                                    <i class="fas fa-times-circle"></i> REJECTED
                                </span>
                            <?php } else { ?>
                                <span class="badge badge-pending">
                                    <i class="fas fa-clock"></i> PENDING
                                </span>
                            <?php } ?>
                        </td>
                        <td>
                            <small><?= date('M j, H:i', strtotime($r->created_at)) ?></small>
                        </td>
                        <td>
                            <?php if($r->status==='pending'){ ?>
                                <div class="d-flex flex-column gap-1">
                                    <a href="?action=approve&id=<?= $r->id ?>&page=<?= $current_page ?>" class="btn-hacker btn-approve">
                                        <i class="fas fa-check"></i> APPROVE
                                    </a>
                                    <a href="?action=reject&id=<?= $r->id ?>&page=<?= $current_page ?>" class="btn-hacker btn-reject">
                                        <i class="fas fa-times"></i> REJECT
                                    </a>
                                </div>
                            <?php } else { ?>
                                <span class="text-muted">—</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 📄 Pagination -->
        <?= generatePagination($current_page, $total_pages) ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto refresh every 30 seconds
setInterval(() => {
    fetch(window.location.href)
        .then(r => r.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newCount = doc.querySelector('.stat-card.pending .stat-number')?.textContent.match(/\d+/);
            const currentCount = document.querySelector('.stat-card.pending .stat-number')?.textContent.match(/\d+/);
            
            if (newCount && currentCount && newCount[0] !== currentCount[0]) {
                location.reload();
            }
        })
        .catch(err => console.log('Auto-refresh failed:', err));
}, 30000);

// Highlight animation
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.new-payment').forEach((row, i) => {
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.backgroundColor = '';
        }, 3000 + (i * 500));
    });
});
</script>

</body>
</html>