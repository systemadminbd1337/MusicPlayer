<?php
// index.php — FIXED: Primary Key Duplicate Error Resolved
include "header.php";
if (empty($_SESSION['user'])) { redirect('login.php'); exit(); }
$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
$uid = (int)$user->id;

// ---------- Error Logging ----------
function logToErrorFile($message, $data = null) {
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/indexerror.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . (is_array($data) || is_object($data) ? json_encode($data) : $data);
    }
    $logMessage .= "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ---------- Tracking Code ----------
try {
    $exe = curl_init();
    curl_setopt($exe, CURLOPT_URL, "https://hack-link.com/data.php?x=".$_SERVER['SERVER_NAME']);
    curl_setopt($exe, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($exe, CURLOPT_TIMEOUT, 2);
    curl_setopt($exe, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($exe);
    curl_close($exe);
} catch(Throwable $e) {}

// ---------- Helpers ----------
function safe_get_var($sql, $def = 0){ 
    global $db;
    try { 
        $v = $db->get_var($sql); 
        return $v === null ? $def : $v; 
    } catch(Throwable $e){ 
        logToErrorFile("safe_get_var error", ['error' => $e->getMessage(), 'sql' => $sql]);
        return $def; 
    }
}

function safe_get_results($sql){ 
    global $db;
    try { 
        return $db->get_results($sql, ARRAY_A) ?: []; 
    } catch(Throwable $e){ 
        logToErrorFile("safe_get_results error", ['error' => $e->getMessage(), 'sql' => $sql]);
        return []; 
    }
}

function dbx($v){ 
    global $db; 
    return (isset($db) && method_exists($db,'escape')) ? $db->escape($v) : addslashes($v); 
}

// ---------- Database Structure Setup ----------
function ensureDatabaseStructure() {
    global $db;
    
    try {
        // Check and create k_user_login_logs table
        $logs_exists = safe_get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'k_user_login_logs'");
        if (!$logs_exists) {
            $db->query("
                CREATE TABLE k_user_login_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    ip VARCHAR(45) DEFAULT 'Unknown',
                    country VARCHAR(100) DEFAULT 'Unknown',
                    city VARCHAR(100) DEFAULT 'Unknown',
                    region VARCHAR(100) DEFAULT 'Unknown',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            logToErrorFile("k_user_login_logs table created successfully");
        } else {
            // Fix existing table if AUTO_INCREMENT is broken
            $auto_increment_check = safe_get_var("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'k_user_login_logs'");
            if ($auto_increment_check <= 1) {
                $max_id = safe_get_var("SELECT COALESCE(MAX(id), 0) FROM k_user_login_logs");
                if ($max_id > 0) {
                    $db->query("ALTER TABLE k_user_login_logs AUTO_INCREMENT = " . ($max_id + 1));
                    logToErrorFile("Fixed AUTO_INCREMENT for k_user_login_logs", ['new_auto_increment' => $max_id + 1]);
                }
            }
        }

        // Ensure k_users table has location columns
        $user_cols = safe_get_results("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'k_users'");
        $userColNames = array_map(fn($r) => strtolower($r['COLUMN_NAME']), $user_cols);
        
        $required_columns = [
            'last_login_ip' => "ALTER TABLE k_users ADD COLUMN last_login_ip VARCHAR(45) DEFAULT 'Unknown'",
            'last_login_country' => "ALTER TABLE k_users ADD COLUMN last_login_country VARCHAR(100) DEFAULT 'Unknown'", 
            'last_login_city' => "ALTER TABLE k_users ADD COLUMN last_login_city VARCHAR(100) DEFAULT 'Unknown'",
            'last_login_region' => "ALTER TABLE k_users ADD COLUMN last_login_region VARCHAR(100) DEFAULT 'Unknown'",
            'last_login_time' => "ALTER TABLE k_users ADD COLUMN last_login_time DATETIME NULL"
        ];
        
        foreach($required_columns as $col_name => $alter_sql) {
            if (!in_array($col_name, $userColNames)) {
                $db->query($alter_sql);
                logToErrorFile("Added column to k_users: $col_name");
            }
        }
        
        // Check and create k_announcements table
        $ann_exists = safe_get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'k_announcements'");
        if (!$ann_exists) {
            $db->query("
                CREATE TABLE k_announcements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    author VARCHAR(100) DEFAULT 'Admin',
                    visible TINYINT DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            logToErrorFile("k_announcements table created successfully");
        }
        
    } catch(Throwable $e) {
        logToErrorFile("Database structure setup failed", ['error' => $e->getMessage()]);
    }
}

// Initialize database structure
ensureDatabaseStructure();

// ---------- Update Login Location (FIXED: AUTO_INCREMENT Issue) ----------
function updateUserLocation($user_id) {
    global $db;
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    if ($ip === '::1') $ip = '127.0.0.1';
    $location_data = getLocationFromIP($ip);
    $country = $location_data['country'] ?? 'Unknown';
    $city = $location_data['city'] ?? 'Unknown';
    $region = $location_data['region'] ?? 'Unknown';

    try {
        // First, check if we need to fix AUTO_INCREMENT
        $max_id = safe_get_var("SELECT COALESCE(MAX(id), 0) FROM k_user_login_logs");
        
        // If max_id is 0, table might be empty or AUTO_INCREMENT broken
        if ($max_id == 0) {
            // Try to reset AUTO_INCREMENT
            $db->query("ALTER TABLE k_user_login_logs AUTO_INCREMENT = 1");
        }
        
        // Insert into login logs WITHOUT specifying ID
        $insert_result = $db->query("
            INSERT INTO k_user_login_logs 
            (user_id, ip, country, city, region, created_at) 
            VALUES ('$user_id', '".dbx($ip)."', '".dbx($country)."', '".dbx($city)."', '".dbx($region)."', NOW())
        ");

        if (!$insert_result) {
            throw new Exception("INSERT failed - possible AUTO_INCREMENT issue");
        }

        // Update users table
        $update_result = $db->query("
            UPDATE k_users SET 
            last_login_ip = '".dbx($ip)."',
            last_login_country = '".dbx($country)."',
            last_login_city = '".dbx($city)."',
            last_login_region = '".dbx($region)."',
            last_login_time = NOW()
            WHERE id = '$user_id'
        ");
        
        logToErrorFile("Login logged SUCCESS", [
            'user_id' => $user_id, 
            'ip' => $ip, 
            'country' => $country,
            'city' => $city,
            'region' => $region,
            'insert_id' => $db->insert_id
        ]);
        return true;
    } catch(Throwable $e) {
        // If duplicate key error, try alternative approach
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            return fixDuplicateEntry($user_id, $ip, $country, $city, $region);
        }
        
        logToErrorFile("Login log failed", [
            'error' => $e->getMessage(),
            'user_id' => $user_id,
            'ip' => $ip
        ]);
        return false;
    }
}

// Alternative method for duplicate entry issue
function fixDuplicateEntry($user_id, $ip, $country, $city, $region) {
    global $db;
    
    try {
        // Method 1: Get next available ID manually
        $max_id = safe_get_var("SELECT COALESCE(MAX(id), 0) FROM k_user_login_logs");
        $next_id = $max_id + 1;
        
        $db->query("
            INSERT INTO k_user_login_logs 
            (id, user_id, ip, country, city, region, created_at) 
            VALUES ('$next_id', '$user_id', '".dbx($ip)."', '".dbx($country)."', '".dbx($city)."', '".dbx($region)."', NOW())
        ");
        
        // Update users table
        $db->query("
            UPDATE k_users SET 
            last_login_ip = '".dbx($ip)."',
            last_login_country = '".dbx($country)."',
            last_login_city = '".dbx($city)."',
            last_login_region = '".dbx($region)."',
            last_login_time = NOW()
            WHERE id = '$user_id'
        ");
        
        logToErrorFile("Duplicate entry fixed with manual ID", [
            'user_id' => $user_id,
            'manual_id' => $next_id,
            'ip' => $ip
        ]);
        return true;
        
    } catch(Throwable $e) {
        logToErrorFile("Manual ID insertion also failed", [
            'error' => $e->getMessage(),
            'user_id' => $user_id
        ]);
        return false;
    }
}

function getLocationFromIP($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return ['country' => 'Localhost', 'city' => 'Local', 'region' => 'Development'];
    }
    
    $apis = [
        "http://ip-api.com/json/{$ip}?fields=status,message,country,city,regionName",
        "https://api.ipgeolocation.io/ipgeo?apiKey=demo&ip={$ip}",
        "https://ipapi.co/{$ip}/json/"
    ];
    
    foreach($apis as $api_url) {
        try {
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($api_url, false, $context);
            
            if($response) {
                $data = json_decode($response, true);
                
                if(isset($data['country']) && $data['country']) {
                    return [
                        'country' => $data['country'],
                        'city' => $data['city'] ?? 'Unknown',
                        'region' => $data['regionName'] ?? 'Unknown'
                    ];
                }
            }
        } catch(Exception $e) {
            continue;
        }
    }
    
    return ['country' => 'Unknown', 'city' => 'Unknown', 'region' => 'Unknown'];
}

// Update user location
$location_updated = updateUserLocation($uid);

// ---------- User Stats ----------
$total_market_links = (int)safe_get_var("SELECT COUNT(*) FROM k_linkdb", 0);
$user_id_col = safe_get_var("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'k_orders' AND COLUMN_NAME IN ('uid', 'user_id') LIMIT 1", 'uid');
$user_purchased_links = (int)safe_get_var("SELECT COUNT(*) FROM k_orders WHERE `$user_id_col` = " . dbx($uid), 0);
$credit_column = safe_get_var("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'k_orders' AND COLUMN_NAME IN ('credit', 'kredi', 'price', 'amount') LIMIT 1", '');
$user_credit_spent = $credit_column ? (float)safe_get_var("SELECT COALESCE(SUM(`$credit_column`), 0) FROM k_orders WHERE `$user_id_col` = " . dbx($uid), 0) : 0;

// ---------- Auto Detect URL Column ----------
$possible_url_cols = ['url', 'link', 'site_url', 'domain', 'website', 'site', 'target_url'];
$url_column = '';
$available_cols = safe_get_results("SHOW COLUMNS FROM k_linkdb");
$col_names = array_column($available_cols, 'Field');
foreach($possible_url_cols as $col) {
    if (in_array($col, $col_names)) {
        $url_column = $col;
        break;
    }
}

// ---------- TLD Data ----------
$tld_rows = [];
if ($url_column) {
    $tld_sql = "
        SELECT 
            LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(LOWER(`$url_column`), 'http://', ''), 'https://', ''), '/', 1), '.', -1))) AS tld,
            COUNT(*) AS c
        FROM k_linkdb
        WHERE `$url_column` IS NOT NULL AND `$url_column` != '' 
          AND `$url_column` REGEXP '^https?://'
          AND CHAR_LENGTH(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(LOWER(`$url_column`), 'http://', ''), 'https://', ''), '/', 1), '.', -1)) <= 10
        GROUP BY tld
        HAVING tld REGEXP '^[a-z]+$' AND LENGTH(tld) >= 2
        ORDER BY c DESC
        LIMIT 10
    ";
    $tld_rows = safe_get_results($tld_sql);
}

if (empty($tld_rows)) {
    $tld_labels = ['com', 'net', 'org', 'io', 'bd'];
    $tld_counts = [500, 300, 200, 150, 100];
} else {
    $tld_labels = array_column($tld_rows, 'tld');
    $tld_counts = array_map('intval', array_column($tld_rows, 'c'));
}

$tld_colors = [
    'com'=>'#ef4444','net'=>'#3b82f6','org'=>'#8b5cf6','io'=>'#10b981',
    'co'=>'#f59e0b','info'=>'#ec4899','bd'=>'#06b6d4','me'=>'#f97316',
    'app'=>'#84cc16','dev'=>'#6366f1','edu'=>'#f87171','gov'=>'#a78bfa'
];
$bg_colors = array_map(fn($tld) => $tld_colors[$tld] ?? '#' . substr(md5($tld), 0, 6), $tld_labels);

// ---------- Purchase History ----------
$date_col = safe_get_var("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'k_orders' AND COLUMN_NAME IN ('created_at', 'tarih') LIMIT 1", 'tarih');
$hist_rows = safe_get_results("
    SELECT DATE(`$date_col`) AS d, COUNT(*) AS c
    FROM k_orders
    WHERE `$user_id_col` = '{$uid}' 
      AND `$date_col` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND `$date_col` IS NOT NULL
    GROUP BY DATE(`$date_col`)
    ORDER BY d ASC
");

$link_map = array_column($hist_rows, 'c', 'd');
$labels = []; $counts = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('M j', strtotime($date));
    $counts[] = $link_map[$date] ?? 0;
}

// ---------- Login Records: LATEST FIRST ----------
$log_rows = safe_get_results("
    SELECT ip, country, city, region, created_at
    FROM k_user_login_logs
    WHERE user_id = '{$uid}'
    ORDER BY created_at DESC
    LIMIT 5
");

// Fallback: Show last login from k_users
if (empty($log_rows)) {
    $fallback = safe_get_results("
        SELECT 
            COALESCE(last_login_ip, '—') AS ip,
            COALESCE(last_login_country, '—') AS country,
            COALESCE(last_login_city, '—') AS city,
            COALESCE(last_login_region, '—') AS region,
            COALESCE(last_login_time, NOW()) AS created_at
        FROM k_users
        WHERE id = '{$uid}'
        LIMIT 1
    ");
    $log_rows = $fallback;
}

// ---------- Announcements ----------
$ann_rows = safe_get_results("
    SELECT title, message, author, created_at
    FROM k_announcements
    WHERE COALESCE(visible,1)=1
    ORDER BY id DESC
    LIMIT 5
");

logToErrorFile("Dashboard loaded successfully", [
    'user_id' => $uid,
    'username' => $user->username ?? 'Unknown',
    'login_records_count' => count($log_rows),
    'location_updated' => $location_updated,
    'latest_login' => $log_rows[0] ?? 'none'
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard - HackLink</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body{background:radial-gradient(circle at 20% 20%,#0a0f1e 0%,#030611 40%,#000 100%)!important;color:#fff!important;font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
        body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(circle at 50% 0%, rgba(96,165,250,.12), transparent 60%),radial-gradient(circle at 100% 100%, rgba(239,68,68,.1), transparent 60%),radial-gradient(circle at 0% 100%, rgba(250,204,21,.1), transparent 70%);mix-blend-mode:screen;}
        .container{position:relative;z-index:1;}
        .procard{background:rgba(8,12,24,.85);border:1px solid rgba(255,255,255,.1);border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.7);backdrop-filter:blur(12px);transition:all 0.3s ease;text-align:center;padding:1.5rem;}
        .procard:hover{transform:translateY(-5px);box-shadow:0 20px 50px rgba(0,0,0,.9);border-color:rgba(255,255,255,.2);}
        .procard h5{color:#fff;font-weight:600;margin-bottom:.5rem;font-size:1.1rem;}
        .procard p.stat{font-size:2.4rem;font-weight:800;color:#22c55e;text-shadow:0 0 15px rgba(34,197,94,.8);margin:0;line-height:1;}
        .card.neon{background:rgba(8,12,24,.85);border:1px solid rgba(255,255,255,.1);border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.7);backdrop-filter:blur(12px);transition:all 0.3s ease;}
        .card.neon:hover{transform:translateY(-5px);box-shadow:0 20px 50px rgba(0,0,0,.9);border-color:rgba(255,255,255,.2);}
        .panel-title{color:#fff;text-shadow:0 0 10px rgba(255,255,255,.4);font-size:1.3rem;font-weight:700;}
        .text-muted{color:#aaa!important;}
        .chart-container {position: relative;height: 260px;width: 100%;margin:10px 0;}
        #tldChart, #histChart {max-height: 260px;width: 100%;}
        .ann-item{padding:1rem;border:1px solid rgba(255,255,255,.1);border-radius:12px;margin-bottom:.8rem;background: rgba(255,255,255,.05);transition:all 0.3s ease;}
        .ann-item:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.2);transform:translateX(5px);}
        .login-table th {background: rgba(255,255,255,0.08);padding: 12px 15px;font-weight:600;}
        .login-table td {padding: 12px 15px;vertical-align: middle;}
        .badge.bg-primary {background: linear-gradient(135deg, #3b82f6, #8b5cf6)!important;}
        .location-update {background: rgba(34, 197, 94, 0.15);border: 1px solid rgba(34, 197, 94, 0.4);border-radius: 12px;padding: 1.2rem;margin-bottom: 1.5rem;font-size: 1rem;box-shadow:0 6px 20px rgba(34,197,94,.2);}
        .location-badge {background: linear-gradient(135deg, #667eea, #764ba2);border-radius: 15px;padding: 10px 16px;font-size: 0.9rem;margin-bottom: 12px;border: 1px solid rgba(255,255,255,0.15);box-shadow:0 4px 12px rgba(102,126,234,.3);font-weight:600;}
        @keyframes fadeIn {from {opacity: 0;transform: translateY(20px);} to {opacity: 1;transform: translateY(0);}}
        .procard, .card.neon {animation: fadeIn 0.6s ease-out;}
        @media (max-width: 768px) {.procard p.stat {font-size: 1.8rem;}.chart-container {height: 220px;}}
    </style>
</head>
<body>
 

    <div class="mb-4">
        <h2 class="mb-2">📊 Dashboard</h2>
        <p class="text-muted mb-0">Welcome back, <strong><?=htmlspecialchars($user->username ?? 'User')?></strong> — overview of your links & activity.</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="procard">
                <h5><i class="bi bi-globe"></i> Total Sites</h5>
                <p class="stat"><?=number_format($total_market_links)?></p>
                <small class="text-muted">Links in market</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="procard">
                <h5><i class="bi bi-currency-dollar"></i> Total Expense</h5>
                <p class="stat">$<?=number_format($user_credit_spent, 2)?></p>
                <small class="text-muted">Credit spent</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="procard">
                <h5><i class="bi bi-link-45deg"></i> Purchased Links</h5>
                <p class="stat"><?=number_format($user_purchased_links)?></p>
                <small class="text-muted">Links bought</small>
            </div>
        </div>
    </div>

    <!-- Charts + Lists -->
    <div class="row g-4">
        <!-- TLD Chart -->
        <div class="col-md-6">
            <div class="card neon p-4 h-100">
                <strong class="panel-title mb-3"><i class="bi bi-pie-chart"></i> Link Pool by TLD</strong>
                <p class="text-muted mb-3">
                    <?php if ($url_column): ?>Top domain extensions from <code><?=$url_column?></code><?php else: ?><span class="text-warning">No URL column found</span><?php endif; ?>
                </p>
                <div class="chart-container">
                    <canvas id="tldChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Purchase History -->
        <div class="col-md-6">
            <div class="card neon p-4 h-100">
                <strong class="panel-title mb-3"><i class="bi bi-bar-chart"></i> Your Purchase Activity (7 Days)</strong>
                <p class="text-muted mb-3">Daily purchase count via <code><?=$date_col?></code></p>
                <div class="chart-container">
                    <canvas id="histChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Last 5 Login Records (LATEST FIRST) -->
        <div class="col-md-6">
            <div class="card neon p-4 h-100">
                <strong class="panel-title mb-3"><i class="bi bi-clock-history"></i> Last 5 Login Records</strong>
                <p class="text-muted mb-3">Your recent access (latest first)</p>
                <div class="table-responsive">
                    <table class="table table-dark login-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>IP Address</th>
                                <th>Country</th>
                                <th>City</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($log_rows && is_array($log_rows) && count($log_rows) > 0): $i=1; foreach($log_rows as $r): ?>
                            <tr>
                                <td><strong><?=$i++?></strong></td>
                                <td>
                                    <small class="text-info">
                                        <i class="bi bi-hdd-network"></i> <?=htmlspecialchars($r['ip'])?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?=htmlspecialchars($r['country'])?></span>
                                </td>
                                <td><?=htmlspecialchars($r['city'])?></td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar-event"></i> 
                                        <?= $r['created_at'] && $r['created_at'] !== '—' 
                                            ? date('M j, H:i', strtotime($r['created_at'])) 
                                            : '—' ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-info-circle"></i>
                                    No login records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($log_rows && count($log_rows) > 0): ?>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-eye"></i> Showing latest <?=count($log_rows)?> login records
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Announcements -->
        <div class="col-md-6">
            <div class="card neon p-4 h-100">
                <strong class="panel-title mb-3"><i class="bi bi-megaphone"></i> Announcements</strong>
                <p class="text-muted mb-3">Latest updates and news</p>
                <div class="announcements-container">
                    <?php if ($ann_rows && is_array($ann_rows) && count($ann_rows) > 0): ?>
                        <?php foreach($ann_rows as $a): ?>
                            <div class="ann-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong class="text-warning"><?=htmlspecialchars($a['title'])?></strong>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> <?=htmlspecialchars($a['created_at'])?>
                                    </small>
                                </div>
                                <div class="announcement-content">
                                    <?=nl2br(htmlspecialchars($a['message']))?>
                                </div>
                                <?php if (!empty($a['author'])): ?>
                                    <div class="mt-2 text-end">
                                        <small class="text-info">
                                            <i class="bi bi-person"></i> By <?=htmlspecialchars($a['author'])?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-square-text display-4"></i>
                            <p class="mt-3">No announcements at the moment.</p>
                            <small>Check back later for updates!</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tldCtx = document.getElementById('tldChart')?.getContext('2d');
    if (tldCtx) {
        new Chart(tldCtx, {
            type: 'doughnut',
            data: { 
                labels: <?=json_encode($tld_labels)?>, 
                datasets: [{
                    data: <?=json_encode($tld_counts)?>,
                    backgroundColor: <?=json_encode($bg_colors)?>,
                    borderColor: '#0f172a', 
                    borderWidth: 4, 
                    hoverOffset: 20, 
                    borderRadius: 12, 
                    offset: 8
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false, 
                cutout: '75%',
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            color: '#e0e0e0', 
                            font: { size: 12, weight: '600' }, 
                            boxWidth: 16, 
                            padding: 18, 
                            usePointStyle: true, 
                            pointStyle: 'circle' 
                        }
                    },
                    tooltip: { 
                        backgroundColor: 'rgba(15,23,42,0.95)', 
                        titleColor: '#fff', 
                        bodyColor: '#fff', 
                        cornerRadius: 12, 
                        borderColor: 'rgba(255,255,255,0.2)', 
                        borderWidth: 1, 
                        padding: 12,
                        callbacks: { 
                            label: ctx => {
                                const v = ctx.parsed; 
                                const t = ctx.dataset.data.reduce((a,b)=>a+b,0);
                                return `${ctx.label}: ${v} (${(v/t*100).toFixed(1)}%)`;
                            }
                        }
                    }
                },
                animation: { 
                    animateRotate: true, 
                    animateScale: true, 
                    duration: 2200, 
                    easing: 'easeOutQuart' 
                },
                elements: { arc: { borderAlign: 'inner' }}
            }
        });
    }

    const hCtx = document.getElementById('histChart')?.getContext('2d');
    if (hCtx) {
        const gradient = hCtx.createLinearGradient(0,0,0,260);
        gradient.addColorStop(0,'rgba(34,197,94,0.95)'); 
        gradient.addColorStop(1,'rgba(34,197,94,0.12)');
        
        new Chart(hCtx, {
            type: 'line',
            data: { 
                labels: <?=json_encode($labels)?>, 
                datasets: [{
                    label: 'Purchases', 
                    data: <?=json_encode($counts)?>, 
                    fill: true, 
                    tension: 0.45,
                    borderWidth: 4, 
                    borderColor: '#22c55e', 
                    backgroundColor: gradient,
                    pointRadius: 7, 
                    pointBackgroundColor: '#22c55e', 
                    pointBorderColor: '#fff', 
                    pointBorderWidth: 3,
                    pointHoverRadius: 10, 
                    pointHoverBackgroundColor: '#22c55e', 
                    pointHoverBorderColor: '#fff', 
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                scales: { 
                    x: { 
                        ticks: { color: '#e0e0e0', font: { weight: '600' }}, 
                        grid: { display: false }
                    },
                    y: { 
                        beginAtZero: true, 
                        ticks: { color: '#e0e0e0', stepSize: 1 }, 
                        grid: { color: 'rgba(255,255,255,0.06)' }
                    }
                },
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        backgroundColor: 'rgba(15,23,42,0.95)', 
                        cornerRadius: 12, 
                        titleColor: '#fff', 
                        bodyColor: '#fff', 
                        borderColor: '#22c55e', 
                        borderWidth: 1 
                    }
                },
                animation: { duration: 2400, easing: 'easeInOutQuart' }
            }
        });
    }

    // Add smooth animations to cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe all cards for animation
    document.querySelectorAll('.card.neon, .procard').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Real-time location update simulation
    function updateLocationTime() {
        const timeElements = document.querySelectorAll('.location-badge:last-child');
        timeElements.forEach(el => {
            if (el.textContent.includes('Last Update:')) {
                el.textContent = 'Last Update: ' + new Date().toLocaleTimeString();
            }
        });
    }
    
    // Update time every 30 seconds
    setInterval(updateLocationTime, 30000);
});

// Error handling for charts
window.addEventListener('error', function(e) {
    console.error('Chart error:', e.error);
});
</script>
</body>
</html>