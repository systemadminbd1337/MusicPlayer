<?php 
// admin/placements.php — View All Link Placement Activity Logs
// ------------------------------------------------------------
// Shows all auto-placement logs (success / failed / pending)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../config.php"; // DB connection

// ✅ Localhost fallback (for testing without login)
if (empty($_SESSION['admin_logged'])) {
    // comment next 2 lines in production
    $_SESSION['admin_logged'] = true;
    $_SESSION['admin_id'] = 1;
}

// ✅ Security check (for real use)
if (empty($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 1);

// --- লগ ফাইল সেটআপ ---
$LOG_FILE = __DIR__ . "/mawoooooooooooo.txt";

// --- লগ লেখার ফাংশন ---
function writeToLog($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
}

// --- ডিবাগ ফাংশন ---
function debugLog($data) {
    if (is_array($data) || is_object($data)) {
        writeToLog("DEBUG: " . print_r($data, true));
    } else {
        writeToLog("DEBUG: " . $data);
    }
}

// --- ডিলিট একশন হ্যান্ডেল ---
if (isset($_POST['delete_all_logs'])) {
    writeToLog("🗑️ DELETE ACTION: Admin attempted to delete all placement logs");
    
    try {
        // k_link_placements টেবিল খালি করো - DELETE ব্যবহার করি
        $result1 = $db->query("DELETE FROM k_link_placements");
        $deleted_placements = $db->rows_affected;
        
        // k_orders টেবিলও খালি করতে চাইলে (ঐচ্ছিক)
        $result2 = $db->query("DELETE FROM k_orders");
        $deleted_orders = $db->rows_affected;
        
        writeToLog("✅ DELETE SUCCESS: Deleted {$deleted_placements} records from k_link_placements and {$deleted_orders} records from k_orders");
        
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => "Successfully deleted {$deleted_placements} placement logs and {$deleted_orders} order records!"
        ];
        
    } catch (Throwable $e) {
        writeToLog("❌ DELETE ERROR: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => "Delete failed: " . $e->getMessage()
        ];
    }
    
    header("Location: placements.php");
    exit;
}

// --- Check if placements table exists, if not create it ---
try {
    $table_exists = $db->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'k_link_placements'");
    
    writeToLog("📊 TABLE CHECK: k_link_placements exists: " . ($table_exists ? 'YES' : 'NO'));
    
    if (!$table_exists) {
        // Create the placements table
        $db->query("
            CREATE TABLE k_link_placements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uid INT NOT NULL,
                lid INT NOT NULL,
                domain VARCHAR(255) NOT NULL,
                target_url TEXT NOT NULL,
                keyword VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                api_response TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_uid (uid),
                INDEX idx_lid (lid),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        writeToLog("✅ TABLE CREATED: k_link_placements table created successfully");
    }
} catch (Throwable $e) {
    writeToLog("❌ TABLE ERROR: " . $e->getMessage());
}

// --- fetch placement logs from both k_orders and k_link_placements ---
try {
    writeToLog("🔍 DATA FETCH: Starting to fetch placement logs");
    
    // Get data from k_orders (existing purchases)
    $orders_data = $db->get_results("
        SELECT 
            o.id, 
            o.uid, 
            o.lid, 
            l.domain,
            o.target_url, 
            o.keyword,
            'success' as status,
            'Manual purchase - Legacy data' as api_response,
            o.tarih as created_at
        FROM k_orders o
        LEFT JOIN k_linkdb l ON o.lid = l.id
        ORDER BY o.id DESC 
        LIMIT 500
    ");
    
    writeToLog("📦 ORDERS DATA: Found " . count($orders_data) . " records from k_orders");
    
    // Get data from k_link_placements (new auto-placement logs)
    $placement_data = $db->get_results("
        SELECT id, uid, lid, domain, target_url, keyword, status, api_response, created_at 
        FROM k_link_placements 
        ORDER BY id DESC 
        LIMIT 500
    ");
    
    writeToLog("🎯 PLACEMENTS DATA: Found " . count($placement_data) . " records from k_link_placements");
    
    // Combine both datasets
    $all_logs = array_merge($placement_data, $orders_data);
    
    // Sort by created_at descending
    usort($all_logs, function($a, $b) {
        return strtotime($b->created_at) - strtotime($a->created_at);
    });
    
    $logs = array_slice($all_logs, 0, 500);
    
    writeToLog("📈 TOTAL LOGS: Combined total " . count($logs) . " logs");
    
} catch (Throwable $e) {
    $logs = [];
    writeToLog("❌ DATA FETCH ERROR: " . $e->getMessage());
    error_log("Placements Error: " . $e->getMessage());
}

// --- নতুন প্লেসমেন্ট টেস্ট করার জন্য ডিবাগ ফাংশন ---
if (isset($_GET['test_placement'])) {
    writeToLog("🧪 TEST PLACEMENT: Manual test triggered");
    
    // টেস্ট ডেটা ইনসার্ট করুন
    $test_data = [
        'uid' => 1,
        'lid' => 999,
        'domain' => 'test-domain.com',
        'target_url' => 'https://test.com',
        'keyword' => 'test keyword',
        'status' => 'success',
        'api_response' => 'Test placement response'
    ];
    
    try {
        $db->query("
            INSERT INTO k_link_placements (uid, lid, domain, target_url, keyword, status, api_response) 
            VALUES (
                '{$test_data['uid']}',
                '{$test_data['lid']}', 
                '{$test_data['domain']}',
                '{$test_data['target_url']}',
                '{$test_data['keyword']}',
                '{$test_data['status']}',
                '{$test_data['api_response']}'
            )
        ");
        writeToLog("✅ TEST SUCCESS: Test placement data inserted");
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => "Test placement data inserted successfully!"
        ];
    } catch (Throwable $e) {
        writeToLog("❌ TEST ERROR: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => "Test failed: " . $e->getMessage()
        ];
    }
    
    header("Location: placements.php");
    exit;
}

// Flash message display
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Status badge helper function (PHP 7 compatible)
function getStatusBadge($status) {
    switch ($status) {
        case 'success':
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Success</span>';
        case 'failed':
            return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>';
        default:
            return '<span class="badge bg-secondary"><i class="bi bi-question-circle me-1"></i>Unknown</span>';
    }
}

// Helper function to safely truncate text
function truncateText($text, $length = 60) {
    if (empty($text)) return 'N/A';
    $text = strip_tags((string)$text);
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

// Helper function to format date
function formatDate($date) {
    if (empty($date)) return 'Unknown';
    return date('Y-m-d H:i:s', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Placement Activity Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --primary-glow: rgba(120, 0, 255, 0.15);
  --success-glow: rgba(22, 163, 74, 0.2);
  --danger-glow: rgba(220, 38, 38, 0.2);
  --warning-glow: rgba(250, 204, 21, 0.2);
  --border-radius: 16px;
  --card-bg: linear-gradient(135deg, rgba(20,18,36,0.95) 0%, rgba(10,8,25,0.98) 100%);
}

* {
  box-sizing: border-box;
}

body {
  background: radial-gradient(circle at top, #0a0816 0%, #080613 100%);
  color: #d0d0e6;
  font-family: 'Inter', sans-serif;
  overflow-x: hidden;
  min-height: 100vh;
  padding: 1rem;
  margin: 0;
}

/* ✨ Professional Card Container */
.card {
  background: var(--card-bg);
  border: 1px solid rgba(120, 0, 255, 0.12);
  border-radius: var(--border-radius);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 
              0 0 0 1px rgba(255, 255, 255, 0.03),
              inset 0 1px 0 rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(10px);
  margin: 0 auto;
  max-width: 1400px;
  width: 100%;
}

.card-header {
  background: transparent;
  border-bottom: 1px solid rgba(120, 0, 255, 0.15);
  padding: 1.5rem 2rem;
}

.card-body {
  padding: 2rem;
}

.card h3 {
  color: #b497ff;
  font-weight: 700;
  font-family: 'Orbitron', sans-serif;
  text-shadow: 0 0 15px rgba(180, 100, 255, 0.4);
  margin: 0;
  font-size: 1.5rem;
}

/* 🟢 Status Badge */
.badge {
  padding: .5em .8em;
  border-radius: 8px;
  font-size: .75em;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
  border: 1px solid transparent;
}

.bg-success { 
  background: linear-gradient(135deg, #16a34a, #22c55e) !important;
  color: #fff !important;
  border-color: rgba(34, 197, 94, 0.3) !important;
}
.bg-danger { 
  background: linear-gradient(135deg, #dc2626, #ef4444) !important;
  color: #fff !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
}
.bg-warning { 
  background: linear-gradient(135deg, #f59e0b, #fbbf24) !important;
  color: #000 !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
}
.bg-secondary { 
  background: linear-gradient(135deg, #6b7280, #9ca3af) !important;
  color: #fff !important;
  border-color: rgba(156, 163, 175, 0.3) !important;
}
.bg-primary { 
  background: linear-gradient(135deg, #3b82f6, #60a5fa) !important;
  color: #fff !important;
  border-color: rgba(59, 130, 246, 0.3) !important;
}
.bg-info { 
  background: linear-gradient(135deg, #06b6d4, #22d3ee) !important;
  color: #fff !important;
  border-color: rgba(6, 182, 212, 0.3) !important;
}

/* 🟢 Response Column Styling - GREEN */
.response-text {
  color: #22c55e !important;
  background: rgba(34, 197, 94, 0.1);
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid rgba(34, 197, 94, 0.2);
  font-family: 'Courier New', monospace;
  font-size: 0.85rem;
  line-height: 1.3;
  display: inline-block;
  max-width: 300px;
  word-break: break-word;
  font-weight: 500;
  cursor: help;
}

.response-text:hover {
  background: rgba(34, 197, 94, 0.15);
  border-color: rgba(34, 197, 94, 0.3);
  box-shadow: 0 0 8px rgba(34, 197, 94, 0.2);
}

/* 🔴 Time Column Styling - RED */
.time-text {
  color: #ef4444 !important;
  background: rgba(239, 68, 68, 0.1);
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid rgba(239, 68, 68, 0.2);
  font-family: 'Courier New', monospace;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-block;
  text-align: center;
  min-width: 140px;
  cursor: help;
}

.time-text:hover {
  background: rgba(239, 68, 68, 0.15);
  border-color: rgba(239, 68, 68, 0.3);
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.2);
}

/* 🧭 Navigation */
.navx {
  margin-bottom: 2rem;
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  justify-content: center;
  max-width: 1400px;
  margin-left: auto;
  margin-right: auto;
}
.navx a {
  padding: 10px 16px;
  border-radius: 10px;
  text-decoration: none;
  color: #cfd9ff;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(96, 165, 250, 0.1);
  transition: all .2s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: .85rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}
.navx a:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 255, 157, 0.15);
  color: #00ff9d;
  border-color: rgba(0, 255, 157, 0.25);
  background: rgba(0, 255, 157, 0.05);
}
.navx a.active {
  color: #00ff9d !important;
  box-shadow: 0 0 15px rgba(0, 255, 157, 0.25);
  border-color: rgba(0, 255, 157, 0.4);
  background: rgba(0, 255, 157, 0.08);
}

/* 📊 Professional Table Styling */
.table-container {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(120, 0, 255, 0.1);
}

.table {
  border-collapse: separate !important;
  border-spacing: 0;
  margin: 0;
  width: 100%;
  color: #fff;
}

.table thead {
  background: linear-gradient(135deg, rgba(40,35,68,0.95) 0%, rgba(30,25,55,0.98) 100%);
  color: #c8bfff;
  border-bottom: 2px solid rgba(120, 0, 255, 0.2);
}

.table thead th {
  padding: 1rem 1.25rem;
  font-weight: 600;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border: none;
  white-space: nowrap;
  color: #c8bfff !important;
}

.table tbody tr {
  background: rgba(22, 18, 45, 0.7);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-bottom: 1px solid rgba(120, 0, 255, 0.05);
}

.table tbody tr:hover {
  background: rgba(100, 0, 255, 0.12);
  transform: translateX(4px);
}

.table tbody tr:last-child {
  border-bottom: none;
}

.table td {
  vertical-align: middle;
  padding: 1rem 1.25rem;
  border: none;
  font-size: 0.9rem;
  border-bottom: 1px solid rgba(120, 0, 255, 0.05);
  color: #d0d0e6;
}

.table a {
  color: #58b0ff;
  text-decoration: none;
  transition: all 0.2s ease;
  font-weight: 500;
}
.table a:hover { 
  color: #00ff9d; 
  text-shadow: 0 0 8px rgba(0, 255, 157, 0.3);
}

/* 🔍 Datatables Customization */
.dataTables_wrapper {
  margin-top: 1.5rem;
}

.dataTables_filter input {
  background: rgba(20, 18, 45, 0.8) !important;
  border: 1px solid rgba(120, 0, 255, 0.3) !important;
  color: #fff !important;
  padding: 0.75rem 1rem !important;
  border-radius: 10px !important;
  outline: none !important;
  transition: all 0.2s ease !important;
}

.dataTables_filter input:focus {
  border-color: rgba(120, 0, 255, 0.6) !important;
  box-shadow: 0 0 0 3px rgba(120, 0, 255, 0.1) !important;
}

.dataTables_length select {
  background: rgba(20, 18, 45, 0.8) !important;
  border: 1px solid rgba(120, 0, 255, 0.3) !important;
  color: #fff !important;
  border-radius: 8px !important;
  padding: 0.5rem !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  background: rgba(31, 29, 46, 0.8) !important;
  border: 1px solid rgba(120, 0, 255, 0.2) !important;
  color: #fff !important;
  border-radius: 8px !important;
  margin: 0 3px !important;
  transition: all 0.2s ease !important;
  padding: 0.5rem 0.9rem !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background: linear-gradient(135deg, #6d28d9, #9d4edd) !important;
  border-color: rgba(157, 78, 221, 0.5) !important;
  transform: translateY(-1px);
  color: #fff !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  background: linear-gradient(135deg, #6d28d9, #9d4edd) !important;
  border-color: rgba(157, 78, 221, 0.6) !important;
  box-shadow: 0 4px 12px rgba(109, 40, 217, 0.3);
  color: #fff !important;
}

/* 🔴 Delete Button Styling */
.btn-delete-all {
  background: linear-gradient(135deg, #dc2626, #ef4444);
  border: none;
  color: white;
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.3s ease;
  font-weight: 600;
}

.btn-delete-all:hover {
  background: linear-gradient(135deg, #b91c1c, #dc2626);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
  color: white;
}

/* 🟢 Test Button Styling */
.btn-test {
  background: linear-gradient(135deg, #16a34a, #22c55e);
  border: none;
  color: white;
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.3s ease;
  font-weight: 600;
}

.btn-test:hover {
  background: linear-gradient(135deg, #15803d, #16a34a);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(22, 163, 74, 0.3);
  color: white;
}

/* 🔄 Refresh Button */
.btn-refresh {
  background: linear-gradient(135deg, #6d28d9, #9d4edd);
  border: none;
  color: white;
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.btn-refresh:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(109, 40, 217, 0.3);
  color: white;
}

/* 🟡 Flash Message Styling */
.flash-message {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  min-width: 300px;
  animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* 🔵 Info Badge */
.info-badge {
  background: linear-gradient(135deg, #3b82f6, #60a5fa);
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Outline Buttons */
.btn-outline-success {
  border-color: #22c55e;
  color: #22c55e;
}
.btn-outline-success:hover {
  background-color: #22c55e;
  border-color: #22c55e;
  color: white;
}

.btn-outline-info {
  border-color: #06b6d4;
  color: #06b6d4;
}
.btn-outline-info:hover {
  background-color: #06b6d4;
  border-color: #06b6d4;
  color: white;
}

.btn-outline-warning {
  border-color: #f59e0b;
  color: #f59e0b;
}
.btn-outline-warning:hover {
  background-color: #f59e0b;
  border-color: #f59e0b;
  color: black;
}

.btn-outline-primary {
  border-color: #3b82f6;
  color: #3b82f6;
}
.btn-outline-primary:hover {
  background-color: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

/* 📱 Mobile Responsive Design */
@media (max-width: 1200px) {
  .card-body {
    padding: 1.5rem;
  }
  
  .navx a {
    padding: 8px 12px;
    font-size: 0.8rem;
  }
  
  .response-text {
    max-width: 250px;
  }
  
  .time-text {
    min-width: 120px;
    font-size: 0.8rem;
  }
}

@media (max-width: 768px) {
  body {
    padding: 0.5rem;
  }
  
  .card {
    border-radius: 12px;
  }
  
  .card-header {
    padding: 1.25rem 1.5rem;
  }
  
  .card-body {
    padding: 1rem;
  }
  
  .card h3 {
    font-size: 1.25rem;
  }
  
  .navx {
    gap: 0.4rem;
    margin-bottom: 1.5rem;
  }
  
  .navx a {
    padding: 6px 10px;
    font-size: 0.75rem;
    border-radius: 8px;
  }
  
  .table thead th {
    padding: 0.75rem 0.5rem;
    font-size: 0.75rem;
  }
  
  .table td {
    padding: 0.75rem 0.5rem;
    font-size: 0.8rem;
  }
  
  .response-text {
    max-width: 200px;
    font-size: 0.8rem;
    padding: 4px 8px;
  }
  
  .time-text {
    min-width: 100px;
    font-size: 0.75rem;
    padding: 4px 8px;
  }
  
  .dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.4rem 0.7rem !important;
    margin: 0 2px !important;
    font-size: 0.8rem;
  }
  
  .card-header .d-flex {
    flex-direction: column;
    gap: 10px;
    align-items: flex-start !important;
  }
  
  .card-header .d-flex .d-flex {
    width: 100%;
    justify-content: space-between;
  }
}

@media (max-width: 576px) {
  .table-responsive {
    border-radius: 8px;
  }
  
  .navx {
    justify-content: flex-start;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }
  
  .navx a {
    flex-shrink: 0;
  }
  
  .dataTables_filter, .dataTables_length {
    text-align: left !important;
    margin-bottom: 1rem;
  }
  
  .dataTables_filter input {
    width: 100% !important;
    margin-left: 0 !important;
  }
  
  .response-text {
    max-width: 150px;
    font-size: 0.75rem;
  }
  
  .time-text {
    min-width: 80px;
    font-size: 0.7rem;
  }
  
  .card-header .d-flex .d-flex {
    flex-direction: column;
    gap: 8px;
  }
  
  .card-header .d-flex .d-flex .btn {
    width: 100%;
    text-align: center;
  }
}

/* ✨ Loading Animation */
.loading-skeleton {
  background: linear-gradient(90deg, rgba(30,25,55,0.5) 25%, rgba(40,35,68,0.7) 50%, rgba(30,25,55,0.5) 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
  border-radius: 4px;
  height: 20px;
  margin: 5px 0;
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* 🔗 URL Truncation */
.url-truncate {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: inline-block;
  vertical-align: middle;
}

@media (max-width: 768px) {
  .url-truncate {
    max-width: 150px;
  }
}

@media (max-width: 576px) {
  .url-truncate {
    max-width: 120px;
  }
}

/* 🎯 Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
  color: #9ca3af;
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  color: #6b7280;
}

/* Alert Colors Fix */
.alert-success {
  background: linear-gradient(135deg, #16a34a, #22c55e);
  color: white;
  border: none;
}

.alert-danger {
  background: linear-gradient(135deg, #dc2626, #ef4444);
  color: white;
  border: none;
}

.alert-info {
  background: linear-gradient(135deg, #06b6d4, #22d3ee);
  color: white;
  border: none;
}

.alert-warning {
  background: linear-gradient(135deg, #f59e0b, #fbbf24);
  color: black;
  border: none;
}
</style>
</head>
<body>

<!-- 🧭 Professional Navigation -->
<div class="navx">
  <a href="index.php"><i class="bi bi-house"></i> Home</a>
  <a href="users.php"><i class="bi bi-people"></i> Users</a>
  <a href="announcements.php"><i class="bi bi-megaphone"></i> Announcements</a>
  <a href="broken_links.php"><i class="bi bi-exclamation-triangle"></i> Broken Links</a>
  <a href="login_logs.php"><i class="bi bi-journal-text"></i> Logs</a>
  <a href="user_login_logs.php"><i class="bi bi-list-check"></i> User Logins</a>
  <a href="monitors.php"><i class="bi bi-link-45deg"></i> URL Monitor</a>
  <a href="deleted_sites.php"><i class="bi bi-trash"></i> Removed Sites</a>
  <a href="../index.php"><i class="bi bi-globe"></i> Front</a>
  <a href="add_link.php"><i class="bi bi-plus-circle"></i> Add Links</a>
  <a href="auto_jobs.php"><i class="bi bi-gear"></i> Auto Jobs</a>
  <a href="placements.php" class="active"><i class="bi bi-pin-map"></i> Placements</a>
  <a href="payments.php"><i class="bi bi-credit-card"></i> Payments</a>
  <a href="actions.php"><i class="bi bi-puzzle"></i> Actions</a>
  <a href="logout.php" style="color:#f87171;"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Flash Message Display -->
<?php if (!empty($flash_message)): ?>
<div class="flash-message">
    <div class="alert alert-<?= $flash_message['type'] ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flash_message['type'] == 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($flash_message['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- 🎯 Main Content -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <h3><i class="bi bi-activity me-2"></i>Placement Activity Logs</h3>
      <small class="text-muted">
        Total: <?= count($logs) ?> logs | 
        Orders: <?= count($orders_data ?? []) ?> | 
        Placements: <?= count($placement_data ?? []) ?> |
        <span class="info-badge">Log: mawoooooooooooo.txt</span>
      </small>
    </div>
    <div class="d-flex gap-2">
      <a href="?test_placement=1" class="btn btn-test" onclick="return confirm('Add test placement data?');">
        <i class="bi bi-play-circle me-1"></i> Test Placement
      </a>
      <form method="POST" onsubmit="return confirm('⚠️ Are you sure you want to delete ALL placement logs and orders? This action cannot be undone!');">
        <button type="submit" name="delete_all_logs" class="btn btn-delete-all">
          <i class="bi bi-trash me-1"></i> Delete All
        </button>
      </form>
      <button class="btn btn-refresh" onclick="window.location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
      </button>
    </div>
  </div>
  <div class="card-body">
    <?php if(empty($logs)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <h4>No Placement Activity Found</h4>
        <p>No link placement logs have been recorded yet.</p>
        <small class="text-muted">Logs will appear here when auto-placement system runs.</small>
        <div class="mt-3">
          <a href="mawoooooooooooo.txt" target="_blank" class="btn btn-outline-primary me-2">
            <i class="bi bi-file-text me-1"></i> View Log File
          </a>
          <a href="?test_placement=1" class="btn btn-success">
            <i class="bi bi-play-circle me-1"></i> Test Placement System
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="table-container">
        <div class="table-responsive">
          <table id="logTable" class="table table-dark align-middle" style="width:100%">
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Link ID</th>
                <th>Domain</th>
                <th>Target URL</th>
                <th>Keyword</th>
                <th>Status</th>
                <th>Response</th>
                <th>Time</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($logs as $r): ?>
              <tr>
                <td><strong>#<?= (int)($r->id ?? 0) ?></strong></td>
                <td><?= (int)($r->uid ?? 0) ?></td>
                <td><?= (int)($r->lid ?? 0) ?></td>
                <td><?= htmlspecialchars($r->domain ?? 'N/A') ?></td>
                <td>
                  <?php if(!empty($r->target_url)): ?>
                    <a href="<?= htmlspecialchars($r->target_url) ?>" target="_blank" class="url-truncate" title="<?= htmlspecialchars($r->target_url) ?>">
                      <?= htmlspecialchars($r->target_url) ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">N/A</span>
                  <?php endif; ?>
                </td>
                <td><code><?= htmlspecialchars($r->keyword ?? 'N/A') ?></code></td>
                <td><?= getStatusBadge($r->status ?? 'unknown') ?></td>
                <td>
                  <span class="response-text" title="<?= htmlspecialchars($r->api_response ?? 'No response') ?>">
                    <?= truncateText($r->api_response ?? 'N/A') ?>
                  </span>
                </td>
                <td>
                  <span class="time-text" title="<?= htmlspecialchars($r->created_at ?? 'Unknown') ?>">
                    <?= formatDate($r->created_at) ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= strpos($r->api_response ?? '', 'Legacy') !== false ? 'bg-secondary' : 'bg-primary' ?>">
                    <?= strpos($r->api_response ?? '', 'Legacy') !== false ? 'Orders' : 'Placements' ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-3 text-center">
        <a href="mawoooooooooooo.txt" target="_blank" class="btn btn-outline-success me-2">
          <i class="bi bi-file-text me-1"></i> View Detailed Log File
        </a>
        <a href="?test_placement=1" class="btn btn-outline-info me-2">
          <i class="bi bi-play-circle me-1"></i> Test Placement
        </a>
        <button class="btn btn-outline-warning" onclick="checkPlacementSystem()">
          <i class="bi bi-gear me-1"></i> Check System
        </button>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
  $('#logTable').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
    responsive: true,
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search logs...",
      lengthMenu: "_MENU_ records per page",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: "No records available",
      infoFiltered: "(filtered from _MAX_ total entries)",
      paginate: {
        previous: '<i class="bi bi-chevron-left"></i>',
        next: '<i class="bi bi-chevron-right"></i>'
      }
    },
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
    drawCallback: function(settings) {
      // Add smooth animations to table rows
      $('tbody tr').css('opacity', '0').each(function(i) {
        $(this).delay(i * 50).animate({opacity: 1}, 200);
      });
    }
  });

  // Auto-hide flash messages after 5 seconds
  setTimeout(function() {
    $('.flash-message').fadeOut(300);
  }, 5000);
});

function checkPlacementSystem() {
  alert('🔍 System Check:\n\n1. Use "Test Placement" to verify system works\n2. Check log file for detailed debugging\n3. Make sure link-depo.php has log_placement_activity() calls\n4. Verify database permissions');
}
</script>
</body>
</html>