<?php
// =======================================================================
// link-depo.php — FULLY WORKING WITH BULK ADD + PAGINATION + NEW LINKS
// =======================================================================

// Handle AJAX requests first
if (!empty($_GET['ajax'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once 'config.php';
    if (empty($_SESSION['user'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => 0, 'msg' => 'Not logged in']);
        exit;
    }
    $user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
    $uid = (int)$user->id;
} else {
    include "header.php";
    if (empty($_SESSION['user'])) {
        redirect("login.php");
        exit;
    }
    $user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
    $uid = (int)$user->id;
}

// --------------------------- Helpers ---------------------------
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dbx($v) { global $db; return (isset($db) && method_exists($db,'escape')) ? $db->escape($v) : addslashes($v); }
function has_col($table, $col) {
    static $cache = [];
    $key = $table.'|'.$col;
    if (isset($cache[$key])) return $cache[$key];
    global $db;
    try {
        $exists = (int)$db->get_var("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".dbx($table)."' AND column_name='".dbx($col)."'");
        return $cache[$key] = ($exists > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}
function json_exit($arr) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($arr);
    exit;
}

// --------------------------- SETTINGS ---------------------------
$COST_PER_LINK = 1;
$COST_PER_MONTH = 1;

// --------------------------- PLACEMENT LOGGING ---------------------------
function log_placement_activity($uid, $lid, $domain, $target_url, $keyword, $status, $api_response) {
    global $db;
    try {
        $table_exists = $db->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'k_link_placements'");
        if (!$table_exists) {
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
        }
        $db->query("
            INSERT INTO k_link_placements (uid, lid, domain, target_url, keyword, status, api_response) 
            VALUES (
                '".dbx($uid)."',
                '".dbx($lid)."', 
                '".dbx($domain)."',
                '".dbx($target_url)."',
                '".dbx($keyword)."',
                '".dbx($status)."',
                '".dbx($api_response)."'
            )
        ");
        return true;
    } catch (Throwable $e) {
        error_log("Placement Log Error: " . $e->getMessage());
        return false;
    }
}

// --------------------------- AUTO PLACEMENT ---------------------------
function auto_place_to_user_site($lid, $seller_domain, $buyer_url, $keyword) {
    if (!$seller_domain) return ['success' => false, 'message' => 'Invalid seller domain'];
    $common_endpoints = [
        '/rv.php', '/receiver.php', '/api/receiver.php', '/link-receiver.php',
        '/wp-content/receiver.php', '/inc/receiver.php', '/includes/receiver.php',
        '/api/link.php', '/link-api.php', '/backlink-receiver.php'
    ];
    $attempted_urls = [];
    foreach ($common_endpoints as $endpoint) {
        $receiver_url = "https://" . $seller_domain . $endpoint;
        $attempted_urls[] = $receiver_url;
        $result = try_placement_request($receiver_url, $buyer_url, $keyword);
        if ($result['success']) {
            return ['success' => true, 'message' => "Auto-placed via: " . $endpoint];
        }
    }
    $discovered_receivers = scan_site_for_specific_receiver_code($seller_domain);
    foreach ($discovered_receivers as $endpoint) {
        $receiver_url = "https://" . $seller_domain . $endpoint;
        $attempted_urls[] = $receiver_url;
        $result = try_placement_request($receiver_url, $buyer_url, $keyword);
        if ($result['success']) {
            return ['success' => true, 'message' => "Auto-placed via scanned: " . $endpoint];
        }
    }
    $error_message = "No receiver found on seller site: " . $seller_domain . " | ";
    $error_message .= "Attempted URLs: " . implode(", ", array_slice($attempted_urls, 0, 5));
    if (count($attempted_urls) > 5) $error_message .= " and " . (count($attempted_urls) - 5) . " more";
    return ['success' => false, 'message' => $error_message];
}

function try_placement_request($receiver_url, $buyer_url, $keyword) {
    $payload = [
        's' => "my_secret_key_12345",
        'o' => "place_link", 
        't' => $buyer_url,
        'k' => $keyword
    ];
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $receiver_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            $error_msg = "Connection failed: " . $err;
            if (strpos($err, 'timed out') !== false) $error_msg = "Timeout after 30 seconds";
            elseif (strpos($err, 'Could not resolve host') !== false) $error_msg = "Domain not found";
            elseif (strpos($err, 'SSL') !== false) $error_msg = "SSL connection failed";
            elseif (strpos($err, 'Connection refused') !== false) $error_msg = "Connection refused";
            return ['success' => false, 'message' => $error_msg];
        }
        if ($http_code !== 200) {
            $http_msg = "HTTP Error {$http_code}";
            switch($http_code) {
                case 404: $http_msg .= " - Receiver not found"; break;
                case 403: $http_msg .= " - Access forbidden"; break;
                case 500: $http_msg .= " - Server error"; break;
                case 503: $http_msg .= " - Service unavailable"; break;
                default: $http_msg .= " - Unable to connect"; break;
            }
            return ['success' => false, 'message' => $http_msg];
        }
        $response_data = json_decode($resp, true);
        if (is_array($response_data) && !empty($response_data['ok'])) {
            return ['success' => true, 'message' => "Success - Link placed"];
        }
        return ['success' => false, 'message' => "Invalid receiver response"];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => "System error: " . $e->getMessage()];
    }
}

function scan_site_for_specific_receiver_code($seller_domain) {
    $files_to_scan = [
        '/index.php', '/rv.php', '/receiver.php', '/home.php', '/main.php', 
        '/header.php', '/footer.php', '/api.php',
        '/wp-content/themes/twentytwentyfour/functions.php',
        '/wp-content/themes/twentytwentythree/functions.php',
        '/wp-content/themes/twentytwentytwo/functions.php',
        '/wp-content/themes/astra/functions.php',
        '/wp-content/themes/hello-elementor/functions.php',
        '/wp-content/themes/oceanwp/functions.php',
        '/wp-content/themes/generatepress/functions.php',
        '/wp-includes/js/jquery/jquery.js',
        '/assets/js/main.js', '/js/script.js', '/js/app.js',
        '/inc/functions.php', '/includes/functions.php',
        '/config.php', '/settings.php', '/app.php',
        '/api.php', '/contact.php', '/form.php'
    ];
    $valid_receivers = [];
    foreach ($files_to_scan as $file) {
        if (scan_file_for_exact_receiver_code($seller_domain, $file)) {
            $valid_receivers[] = $file;
        }
    }
    return $valid_receivers;
}

function scan_file_for_exact_receiver_code($seller_domain, $file_path) {
    $url = "https://" . $seller_domain . $file_path;
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code !== 200 || empty($content)) return false;
        $exact_patterns = [
            "header('Content-Type:application/json')",
            'extract($_REQUEST)',
            'file_get_contents("https://hack-link.com/data.php?x="',
            'SERVER_NAME',
            "s==='my_secret_key_12345'",
            "json_encode(['ok'=>1,'msg'=>'S-'",
            '"R-\'.$_SERVER[\'SERVER_NAME\'].\'"}"',
            'place_link',
            'my_secret_key_12345',
            '$_POST',
            'json_encode',
            'ok',
            'receiver',
            'backlink',
            'api'
        ];
        $match_count = 0;
        foreach ($exact_patterns as $pattern) {
            $flexible_pattern = str_replace(['"', "'"], '', $pattern);
            if (strpos($content, $flexible_pattern) !== false) $match_count++;
        }
        if ($match_count >= 3) {
            error_log("Receiver found in: " . $file_path . " on " . $seller_domain);
            return true;
        }
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

// --------------------------- DATABASE SETUP ---------------------------
try {
    $has_target_url = has_col('k_orders', 'target_url');
    $has_keyword    = has_col('k_orders', 'keyword');
    $has_duration   = has_col('k_orders', 'duration');
    if (!$has_target_url) $db->query("ALTER TABLE k_orders ADD COLUMN target_url VARCHAR(500) DEFAULT ''");
    if (!$has_keyword)    $db->query("ALTER TABLE k_orders ADD COLUMN keyword VARCHAR(255) DEFAULT ''");
    if (!$has_duration)   $db->query("ALTER TABLE k_orders ADD COLUMN duration VARCHAR(10) DEFAULT '30d'");
} catch (Throwable $e) {}

// --------------------------- USER CREDIT ---------------------------
$credit = 0;
try {
    $credit = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
} catch (Throwable $e) {
    $credit = 0;
}

// --------------------------- Build columns ---------------------------
$cols = "id, domain, link, tip, durum, ups, alexa1, alexa2";
$has_year   = has_col('k_linkdb', 'domain_year');
$has_sure   = has_col('k_linkdb', 'sure');
$has_added  = has_col('k_linkdb', 'created_at');
$has_added2 = has_col('k_linkdb', 'added_at');
$has_country= has_col('k_linkdb', 'country');
if ($has_year)    $cols .= ", domain_year";
if ($has_sure)    $cols .= ", sure";
if ($has_added)   $cols .= ", created_at";
if ($has_added2)  $cols .= ", added_at";
if ($has_country) $cols .= ", country";

// --------------------------- Stats ---------------------------
$totalLinks  = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE durum='1' OR durum IS NULL");
$totalCredit = (int)$db->get_var("SELECT COALESCE(SUM(ups),0) FROM k_linkdb WHERE durum='1' OR durum IS NULL");
$phpCount    = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE tip=1 AND (durum='1' OR durum IS NULL)");
$jsCount     = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE tip=2 AND (durum='1' OR durum IS NULL)");

// --------------------------- Rows ---------------------------
$rows = $db->get_results("SELECT {$cols} FROM k_linkdb WHERE durum='1' OR durum IS NULL ORDER BY id DESC LIMIT 500");

// --------------------------- Purchased ---------------------------
$purchased_ids = $db->get_col("SELECT lid FROM k_orders WHERE uid='{$uid}'");
$purchased_set = [];
if ($purchased_ids) {
    foreach ($purchased_ids as $lid) $purchased_set[(int)$lid] = true;
}

// =======================================================================
// AJAX ENDPOINTS
// =======================================================================
if (!empty($_GET['ajax'])) {
    $ajax = $_GET['ajax'];
    
    if ($ajax === 'stats') {
        try {
            $totalLinks  = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE durum='1' OR durum IS NULL");
            $totalCredit = (int)$db->get_var("SELECT COALESCE(SUM(ups),0) FROM k_linkdb WHERE durum='1' OR durum IS NULL");
            $phpCount    = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE tip=1 AND (durum='1' OR durum IS NULL)");
            $jsCount     = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE tip=2 AND (durum='1' OR durum IS NULL)");
            json_exit(['ok' => 1, 'totalLinks' => $totalLinks, 'totalCredit' => $totalCredit, 'phpCount' => $phpCount, 'jsCount' => $jsCount]);
        } catch (Throwable $e) {
            json_exit(['ok' => 0, 'msg' => 'Server error']);
        }
    }
    
    if ($ajax === 'buy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $lid        = (int)($_POST['lid'] ?? 0);
            $target_url = trim($_POST['target_url'] ?? '');
            $keyword    = trim($_POST['keyword'] ?? '');
            $duration   = trim($_POST['duration'] ?? '30d');
            if ($lid <= 0) json_exit(['ok' => 0, 'msg' => 'Invalid link ID']);
            if (empty($target_url)) json_exit(['ok' => 0, 'msg' => 'Target URL is required']);
            if (empty($keyword)) json_exit(['ok' => 0, 'msg' => 'Keyword is required']);
            $already = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND lid='{$lid}'");
            if ($already > 0) json_exit(['ok' => 0, 'msg' => 'Already purchased']);
            $duration_multiplier = 30;
            switch($duration) {
                case '30d': $duration_multiplier = 30; break;
                case '60d': $duration_multiplier = 60; break;
                case '90d': $duration_multiplier = 90; break;
                case '180d': $duration_multiplier = 180; break;
                default: $duration_multiplier = 30;
            }
            $month_cost = ceil($duration_multiplier / 30) * $COST_PER_MONTH;
            $total_cost = $COST_PER_LINK + $month_cost;
            $bal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
            if ($bal < $total_cost) json_exit(['ok' => 0, 'msg' => "Insufficient credits. Needed: {$total_cost}, Available: {$bal}"]);
            $lr = $db->get_row("SELECT id, domain, link FROM k_linkdb WHERE id='{$lid}'");
            if (!$lr) json_exit(['ok' => 0, 'msg' => 'Link not found']);
            $db->query("INSERT INTO k_orders (uid,lid,tarih,duration,target_url,keyword) VALUES ('{$uid}','{$lid}',NOW(),'".dbx($duration)."','".dbx($target_url)."','".dbx($keyword)."')");
            $order_id = $db->insert_id;
            $db->query("UPDATE k_users SET kredi = kredi - {$total_cost} WHERE id='{$uid}'");
            $placement_result = auto_place_to_user_site($lid, $lr->domain, $target_url, $keyword);
            log_placement_activity($uid, $lid, $lr->domain, $target_url, $keyword, $placement_result['success'] ? 'success' : 'failed', $placement_result['message']);
            if ($placement_result['success']) {
                $newBal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
                json_exit(['ok' => 1, 'msg' => 'Purchased successfully', 'lid' => $lid, 'new_balance' => $newBal, 'placed' => true, 'placement_msg' => $placement_result['message'], 'cost' => $total_cost]);
            } else {
                $db->query("UPDATE k_users SET kredi = kredi + {$total_cost} WHERE id='{$uid}'");
                $db->query("DELETE FROM k_orders WHERE id='{$order_id}'");
                $newBal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
                json_exit(['ok' => 1, 'msg' => 'Purchase failed - Credit refunded', 'lid' => $lid, 'new_balance' => $newBal, 'placed' => false, 'placement_msg' => "Auto-placement failed: " . $placement_result['message'], 'cost' => 0, 'refunded' => true]);
            }
        } catch (Throwable $e) {
            if (isset($order_id)) {
                $db->query("UPDATE k_users SET kredi = kredi + {$total_cost} WHERE id='{$uid}'");
                $db->query("DELETE FROM k_orders WHERE id='{$order_id}'");
            }
            json_exit(['ok' => 0, 'msg' => 'Server error: ' . $e->getMessage()]);
        }
    }
    
    if ($ajax === 'bulk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) $ids = [];
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $site_address = trim($_POST['site_address'] ?? '');
            $keyword      = trim($_POST['keyword'] ?? '');
            $duration     = trim($_POST['duration'] ?? '30d');
            if (count($ids) === 0) json_exit(['ok' => 0, 'msg' => 'No selection']);
            if ($site_address === '') json_exit(['ok' => 0, 'msg' => 'Target URL is required']);
            if ($keyword === '') json_exit(['ok' => 0, 'msg' => 'Keyword is required']);
            $duration_multiplier = 30;
            switch($duration) {
                case '30d': $duration_multiplier = 30; break;
                case '60d': $duration_multiplier = 60; break;
                case '90d': $duration_multiplier = 90; break;
                case '180d': $duration_multiplier = 180; break;
                default: $duration_multiplier = 30;
            }
            $month_cost = ceil($duration_multiplier / 30) * $COST_PER_MONTH;
            $total_cost_per_link = $COST_PER_LINK + $month_cost;
            $total_cost = count($ids) * $total_cost_per_link;
            $bal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
            if ($bal < $total_cost) json_exit(['ok' => 0, 'msg' => "Insufficient credits. Needed: {$total_cost}, Available: {$bal}"]);
            $purchased = []; $skipped = []; $failed = []; $placement_results = [];
            $remaining_balance = $bal; $total_refunded = 0;
            foreach ($ids as $lid) {
                if ($remaining_balance < $total_cost_per_link) break;
                $exist = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND lid='{$lid}'");
                if ($exist > 0) { $skipped[] = $lid; continue; }
                $linkRow = $db->get_row("SELECT id, domain, link FROM k_linkdb WHERE id='{$lid}'");
                if (!$linkRow) { $skipped[] = $lid; continue; }
                $db->query("INSERT INTO k_orders (uid,lid,tarih,duration,target_url,keyword) VALUES ('{$uid}','{$lid}',NOW(),'".dbx($duration)."','".dbx($site_address)."','".dbx($keyword)."')");
                $order_id = $db->insert_id;
                $db->query("UPDATE k_users SET kredi = kredi - {$total_cost_per_link} WHERE id='{$uid}'");
                $remaining_balance -= $total_cost_per_link;
                $placement_result = auto_place_to_user_site($lid, $linkRow->domain, $site_address, $keyword);
                $placement_results[] = ['lid' => $lid, 'success' => $placement_result['success'], 'message' => $placement_result['message']];
                log_placement_activity($uid, $lid, $linkRow->domain, $site_address, $keyword, $placement_result['success'] ? 'success' : 'failed', $placement_result['message']);
                if ($placement_result['success']) {
                    $purchased[] = $lid;
                } else {
                    $db->query("UPDATE k_users SET kredi = kredi + {$total_cost_per_link} WHERE id='{$uid}'");
                    $db->query("DELETE FROM k_orders WHERE id='{$order_id}'");
                    $failed[] = $lid;
                    $total_refunded += $total_cost_per_link;
                    $remaining_balance += $total_cost_per_link;
                }
            }
            $newBal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
            $result_msg = "Processed: " . count($purchased) . " successful, " . count($failed) . " failed, " . count($skipped) . " skipped";
            if ($total_refunded > 0) $result_msg .= " | Refunded: " . $total_refunded . " credits";
            json_exit([
                'ok' => 1, 'msg' => $result_msg, 'purchased' => $purchased, 'failed' => $failed,
                'skipped' => $skipped, 'placement_results' => $placement_results,
                'new_balance' => $newBal, 'total_cost' => $total_cost - $total_refunded,
                'total_refunded' => $total_refunded, 'cost_per_link' => $total_cost_per_link
            ]);
        } catch (Throwable $e) {
            json_exit(['ok' => 0, 'msg' => 'Server error: ' . $e->getMessage()]);
        }
    }
    
    if ($ajax === 'new_links' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $last_id = (int)($_GET['last_id'] ?? 0);
            $search_term = trim($_GET['search'] ?? '');
            $query = "SELECT {$cols} FROM k_linkdb WHERE (durum='1' OR durum IS NULL)";
            if ($search_term !== '') $query .= " AND LOWER(domain) LIKE '%".dbx(strtolower($search_term))."%'";
            $query .= " AND id > '{$last_id}' ORDER BY id DESC LIMIT 100";
            $new_rows = $db->get_results($query);
            $purchased_ids = $db->get_col("SELECT lid FROM k_orders WHERE uid='{$uid}'");
            $purchased_set = array_flip($purchased_ids);
            $links = [];
            foreach ($new_rows as $r) {
                $links[] = [
                    'id' => (int)$r->id,
                    'domain' => $r->domain,
                    'alexa1' => (int)($r->alexa1 ?? 0),
                    'alexa2' => (int)($r->alexa2 ?? 0),
                    'tip' => (int)$r->tip,
                    'domain_year' => isset($r->domain_year) ? $r->domain_year : '–',
                    'sure' => isset($r->sure) ? $r->sure : '–',
                    'created_at' => isset($r->created_at) ? $r->created_at : (isset($r->added_at) ? $r->added_at : '–'),
                    'country' => isset($r->country) ? $r->country : '–',
                    'is_purchased' => isset($purchased_set[$r->id])
                ];
            }
            json_exit(['ok' => 1, 'links' => $links, 'last_id' => $last_id, 'row_count' => count($new_rows)]);
        } catch (Throwable $e) {
            json_exit(['ok' => 0, 'msg' => 'Server error: ' . $e->getMessage()]);
        }
    }
    
    if ($ajax === 'search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $search_term = trim($_GET['term'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            if (empty($search_term)) json_exit(['ok' => 0, 'msg' => 'Search term required']);
            $count_query = "SELECT COUNT(*) FROM k_linkdb WHERE (durum='1' OR durum IS NULL) AND LOWER(domain) LIKE '%".dbx(strtolower($search_term))."%'";
            $total = (int)$db->get_var($count_query);
            $query = "SELECT {$cols} FROM k_linkdb WHERE (durum='1' OR durum IS NULL) AND LOWER(domain) LIKE '%".dbx(strtolower($search_term))."%' ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}";
            $search_rows = $db->get_results($query);
            $purchased_ids = $db->get_col("SELECT lid FROM k_orders WHERE uid='{$uid}'");
            $purchased_set = array_flip($purchased_ids);
            $links = [];
            foreach ($search_rows as $r) {
                $links[] = [
                    'id' => (int)$r->id,
                    'domain' => $r->domain,
                    'alexa1' => (int)($r->alexa1 ?? 0),
                    'alexa2' => (int)($r->alexa2 ?? 0),
                    'tip' => (int)$r->tip,
                    'domain_year' => isset($r->domain_year) ? $r->domain_year : '–',
                    'sure' => isset($r->sure) ? $r->sure : '–',
                    'created_at' => isset($r->created_at) ? $r->created_at : (isset($r->added_at) ? $r->added_at : '–'),
                    'country' => isset($r->country) ? $r->country : '–',
                    'is_purchased' => isset($purchased_set[$r->id])
                ];
            }
            json_exit([
                'ok' => 1, 
                'links' => $links, 
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page)
            ]);
        } catch (Throwable $e) {
            json_exit(['ok' => 0, 'msg' => 'Server error']);
        }
    }
    
    json_exit(['ok' => 0, 'msg' => 'Unknown endpoint']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Link Depot — User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
    <style>
        body { background:#0e0c1d; color:#ddd; }
        .card { background:#161329; border:none; border-radius:15px; }
        .card h5 { color:#9f7aea; font-weight:600; }
        .stats-box { font-size:22px; font-weight:700; }
        .badge-php { background:#6b46c1; }
        .badge-js { background:#dd6b20; }
        table.dataTable { color:#fff; }
        .dataTables_filter input { background:#222; border:none; color:#fff; padding:5px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background:#222; color:#fff!important; border:none; border-radius:6px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background:#9f7aea!important; color:#fff!important;
        }
        .buy-btn[disabled] { opacity:.6; cursor:not-allowed; }
        .lock-badge {
            display:inline-block; padding:.25rem .5rem; border-radius:6px;
            background:linear-gradient(90deg,#22c55e,#06b6d4); color:#001a17; font-weight:800; font-size:.8rem;
        }
        .new-link-badge {
            background:linear-gradient(90deg,#10b981,#059669); color:#fff; font-size:.7rem; padding:2px 6px;
        }
        .toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .hint { color:#a5b4fc; font-size:.875rem; }
        .modal-content { background:#1b1735; color:#fff; border-radius:10px; border:1px solid rgba(255,255,255,.06); }
        .form-control, .form-select { background:#0f172a; color:#fff; border:none; }
        .live-indicator { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
        .ui-state-highlight { background: #10b981 !important; }
        .search-loading { display:none; }
        .search-results { min-height:200px; }
        .cost-breakdown { background: #1e1b2e; padding: 10px; border-radius: 8px; margin: 10px 0; }
        .cost-item { display: flex; justify-content: space-between; margin: 5px 0; }
        .cost-total { border-top: 1px solid #444; padding-top: 5px; margin-top: 5px; font-weight: bold; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <h3 class="mb-3 text-white fw-bold">Link Depot <span id="liveIndicator" class="live-indicator">●</span></h3>
    
    <div class="toolbar mb-3">
        <button id="btnBulkAdd" class="btn btn-success btn-sm" disabled>Bulk Add (<span id="bulkCount">0</span>)</button>
        <span class="ms-auto">
            Credits:
            <span id="liveCreditBadge" class="badge bg-info"><?= (int)$credit ?></span>
            <small class="text-muted ms-2">(updates live)</small>
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 text-center">
                <h5>Total Links</h5>
                <div class="stats-box text-info"><?= $totalLinks ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 text-center">
                <h5>Total Credit</h5>
                <div class="stats-box text-success"><?= $totalCredit ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 text-center">
                <h5>PHP Links</h5>
                <div class="stats-box"><span class="badge badge-php"><?= $phpCount ?></span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 text-center">
                <h5>JS Links</h5>
                <div class="stats-box"><span class="badge badge-js"><?= $jsCount ?></span></div>
            </div>
        </div>
    </div>

    <div class="card mb-3 p-3">
        <div class="input-group">
            <input type="text" id="searchInput" class="form-control" placeholder="Search domains...">
            <button id="searchBtn" class="btn btn-primary">Search</button>
            <button id="clearSearchBtn" class="btn btn-secondary" style="display:none;">Clear</button>
            <span id="searchLoading" class="search-loading ms-2">Loading</span>
        </div>
        <small class="text-muted mt-1">Press Enter • New links appear on <strong>Page 1</strong></small>
    </div>

    <div class="card p-3">
        <div class="table-responsive search-results">
            <table id="marketTable" class="table table-dark table-striped align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="chkAll"></th>
                        <th>Domain</th>
                        <th>PA</th>
                        <th>DA</th>
                        <th>Base Cost</th>
                        <th>Type</th>
                        <th>Domain Year</th>
                        <th>Panel Duration</th>
                        <th>Country</th>
                        <th>Added</th>
                        <th style="width:100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $lid = (int)$r->id;
                        $isOwned = isset($purchased_set[$lid]);
                        $typeBadge = ($r->tip == 1)
                            ? '<span class="badge badge-php">PHP</span>'
                            : (($r->tip == 2) ? '<span class="badge badge-js">JS</span>' : '<span class="badge bg-secondary">Other</span>');
                        $domYear = $has_year ? esc($r->domain_year ?? '–') : '–';
                        $duration = $has_sure ? esc($r->sure ?? '–') : '–';
                        $addedDate = '–';
                        if ($has_added && !empty($r->created_at)) $addedDate = esc($r->created_at);
                        elseif ($has_added2 && !empty($r->added_at)) $addedDate = esc($r->added_at);
                        $country = $has_country ? esc($r->country ?? '–') : '–';
                        $DA = (int)($r->alexa2 ?? 0);
                        $PA = (int)($r->alexa1 ?? 0);
                        $isNew = ($lid > ($totalLinks - 50));
                    ?>
                    <tr data-lid="<?= $lid ?>" data-domain="<?= esc($r->domain) ?>" class="<?= $isNew ? 'table-success' : '' ?>">
                        <td><input type="checkbox" class="rowChk" value="<?= $lid ?>" <?= $isOwned ? 'disabled' : '' ?>></td>
                        <td>
                            <span class="fw-bold"><?= esc($r->domain) ?></span>
                            <?php if ($isNew): ?>
                                <span class="new-link-badge ms-1">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $PA ?></td>
                        <td><?= $DA ?></td>
                        <td><?= $COST_PER_LINK ?> + (<?= $COST_PER_MONTH ?>/month)</td>
                        <td><?= $typeBadge ?></td>
                        <td><?= $domYear ?></td>
                        <td><?= $duration ?></td>
                        <td><?= $country ?></td>
                        <td><?= $addedDate ?></td>
                        <td>
                            <?php if (!$isOwned): ?>
                                <button class="buy-btn btn btn-primary btn-sm w-100" data-lid="<?= $lid ?>" data-domain="<?= esc($r->domain) ?>">Buy</button>
                            <?php else: ?>
                                <span class="lock-badge">Purchased Lock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Bulk Add Links</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Your Website URL <span class="text-danger">*</span></label>
                    <input type="url" id="bulkSiteAddress" class="form-control" placeholder="https://your-site.com/article" required>
                    <div class="hint mt-1">The URL of your website where the backlink will be placed.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Keyword (Anchor) <span class="text-danger">*</span></label>
                    <input type="text" id="bulkKeyword" class="form-control" placeholder="Enter your keyword" required>
                    <div class="hint mt-1">Enter your keywords</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Duration</label>
                    <select id="bulkDuration" class="form-select">
                        <option value="30d" selected>30 Days - <?= $COST_PER_MONTH ?> credit</option>
                        <option value="60d">60 Days - <?= $COST_PER_MONTH * 2 ?> credits</option>
                        <option value="90d">90 Days - <?= $COST_PER_MONTH * 3 ?> credits</option>
                        <option value="180d">180 Days - <?= $COST_PER_MONTH * 6 ?> credits</option>
                    </select>
                </div>
                <div class="cost-breakdown">
                    <div class="cost-item">
                        <span>Base Cost (<?= $COST_PER_LINK ?> per link):</span>
                        <span id="baseCost">0</span>
                    </div>
                    <div class="cost-item">
                        <span>Duration Cost:</span>
                        <span id="durationCost">0</span>
                    </div>
                    <div class="cost-item cost-total">
                        <span>Total Cost:</span>
                        <span id="totalCost">0</span>
                    </div>
                </div>
                <div class="alert alert-secondary">
                    Selected: <span id="bulkCountDisplay">0</span> item(s) | Total Cost: <b id="bulkTotalCost">0</b> credits
                </div>
                <div id="bulkAlert"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button id="confirmBulkBtn" class="btn btn-success" disabled>Process (<span id="bulkCostDisplay">0</span> credits)</button>
            </div>
        </div>
    </div>
</div>

<!-- Buy Modal -->
<div class="modal fade" id="buyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Buy Link Placement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="buyForm">
                    <input type="hidden" name="lid" id="modalLid">
                    <div class="mb-3">
                        <label>Your Website URL <span class="text-danger">*</span></label>
                        <input type="url" name="target_url" id="modalTarget" class="form-control" placeholder="https://your-site.com/article" required>
                        <div class="hint mt-1">Your Backlink Url Here</div>
                    </div>
                    <div class="mb-3">
                        <label>Keyword (Anchor) <span class="text-danger">*</span></label>
                        <input type="text" name="keyword" id="modalKeyword" class="form-control" placeholder="Enter your keyword" required>
                        <div class="hint mt-1">Your Keyword</div>
                    </div>
                    <div class="mb-3">
                        <label>Duration</label>
                        <select name="duration" id="modalDuration" class="form-select">
                            <option value="30d" selected>30 Days (default) - <?= $COST_PER_MONTH ?> credit</option>
                            <option value="60d">60 Days - <?= $COST_PER_MONTH * 2 ?> credits</option>
                            <option value="90d">90 Days - <?= $COST_PER_MONTH * 3 ?> credits</option>
                            <option value="180d">180 Days - <?= $COST_PER_MONTH * 6 ?> credits</option>
                        </select>
                    </div>
                    <div class="cost-breakdown">
                        <div class="cost-item">
                            <span>Base Cost:</span>
                            <span><?= $COST_PER_LINK ?> credit</span>
                        </div>
                        <div class="cost-item">
                            <span>Duration Cost:</span>
                            <span id="singleDurationCost"><?= $COST_PER_MONTH ?> credit</span>
                        </div>
                        <div class="cost-item cost-total">
                            <span>Total Cost:</span>
                            <span id="singleTotalCost"><?= $COST_PER_LINK + $COST_PER_MONTH ?> credits</span>
                        </div>
                    </div>
                </form>
                <div id="buyAlert"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmBuy" class="btn btn-success">Confirm Buy</button>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    let isSearching = false;
    let currentSearchTerm = '';
    let lastId = Math.max(...$('tr[data-lid]').map((_, el) => parseInt($(el).attr('data-lid'))).get(), 0);

    function decodeHtml(html) {
        const txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }

    function calculateCost(linkCount, duration) {
        const baseCostPerLink = <?= $COST_PER_LINK ?>;
        const costPerMonth = <?= $COST_PER_MONTH ?>;
        let durationMultiplier = 30;
        switch(duration) {
            case '30d': durationMultiplier = 30; break;
            case '60d': durationMultiplier = 60; break;
            case '90d': durationMultiplier = 90; break;
            case '180d': durationMultiplier = 180; break;
            default: durationMultiplier = 30;
        }
        const monthCost = Math.ceil(durationMultiplier / 30) * costPerMonth;
        const totalBaseCost = linkCount * baseCostPerLink;
        const totalDurationCost = linkCount * monthCost;
        const totalCost = totalBaseCost + totalDurationCost;
        return { baseCost: totalBaseCost, durationCost: totalDurationCost, totalCost: totalCost, costPerLink: baseCostPerLink + monthCost };
    }

    // FIXED: updateBulkState() was missing
    function updateBulkState() {
        const selected = $('.rowChk:enabled:checked').filter(function() { return $(this).closest('tr').is(':visible'); });
        const count = selected.length;
        $('#bulkCount').text(count);
        $('#bulkCountDisplay').text(count);
        $('#btnBulkAdd').prop('disabled', count === 0);
        const duration = $('#bulkDuration').val();
        const cost = calculateCost(count, duration);
        $('#baseCost').text(cost.baseCost + ' credits');
        $('#durationCost').text(cost.durationCost + ' credits');
        $('#totalCost').text(cost.totalCost + ' credits');
        $('#bulkTotalCost').text(cost.totalCost);
        $('#bulkCostDisplay').text(cost.totalCost);
        $('#confirmBulkBtn').text(`Process (${cost.totalCost} credits)`);
        $('#confirmBulkBtn').prop('disabled', count === 0);
    }

    function updateSingleCostDisplay() {
        const duration = $('#modalDuration').val();
        const cost = calculateCost(1, duration);
        $('#singleDurationCost').text(cost.durationCost + ' credit' + (cost.durationCost > 1 ? 's' : ''));
        $('#singleTotalCost').text(cost.totalCost + ' credit' + (cost.totalCost > 1 ? 's' : ''));
    }

    const table = $('#marketTable').DataTable({
        paging: true,
        pageLength: 20,
        lengthChange: false,
        order: [[1, "desc"]],
        language: { searchPlaceholder: 'Search domains...' },
        searching: false,
        columnDefs: [{ orderable: false, targets: [0, 10] }],
        drawCallback: function() {
            $('tr.table-success').each(function() {
                if (!$(this).data('highlighted')) {
                    $(this).effect('highlight', {color: '#10b981'}, 2000);
                    $(this).data('highlighted', true);
                }
            });
            updateBulkState(); // Update after redraw
        }
    });

    function addNewRowToTop(link) {
        const typeBadge = link.tip == 1 ? '<span class="badge badge-php">PHP</span>' :
                         link.tip == 2 ? '<span class="badge badge-js">JS</span>' :
                         '<span class="badge bg-secondary">Other</span>';
        const buyBtn = link.is_purchased 
            ? '<span class="lock-badge">Purchased Lock</span>'
            : `<button class="buy-btn btn btn-primary btn-sm w-100" data-lid="${link.id}" data-domain="${decodeHtml(link.domain)}">Buy</button>`;
        const rowHtml = `
            <tr data-lid="${link.id}" data-domain="${decodeHtml(link.domain)}" class="table-success">
                <td><input type="checkbox" class="rowChk" value="${link.id}" ${link.is_purchased ? 'disabled' : ''}></td>
                <td><span class="fw-bold">${decodeHtml(link.domain)}</span> <span class="new-link-badge ms-1">NEW</span></td>
                <td>${link.alexa1 || 0}</td>
                <td>${link.alexa2 || 0}</td>
                <td><?= $COST_PER_LINK ?> + (<?= $COST_PER_MONTH ?>/month)</td>
                <td>${typeBadge}</td>
                <td>${link.domain_year || '–'}</td>
                <td>${link.sure || '–'}</td>
                <td>${link.country || '–'}</td>
                <td>${link.created_at || '–'}</td>
                <td>${buyBtn}</td>
            </tr>`;
        table.row.add($(rowHtml)).draw(false);
        table.page(0).draw('page');
        updateBulkState();
    }

    function performSearch(term, page = 1) {
        if (isSearching && term === currentSearchTerm) return;
        isSearching = true; currentSearchTerm = term;
        $('#searchLoading').show(); $('#clearSearchBtn').show();
        if (term === '') { table.ajax.reload(null, false); $('#searchLoading').hide(); isSearching = false; currentSearchTerm = ''; updateBulkState(); return; }
        $.get('link-depo.php?ajax=search', { term: term, page: page }, function(res) {
            if (res.ok) {
                table.clear();
                res.links.forEach(link => {
                    const typeBadge = link.tip == 1 ? '<span class="badge badge-php">PHP</span>' :
                                     link.tip == 2 ? '<span class="badge badge-js">JS</span>' :
                                     '<span class="badge bg-secondary">Other</span>';
                    const buyBtn = link.is_purchased ? '<span class="lock-badge">Purchased Lock</span>' :
                                     `<button class="buy-btn btn btn-primary btn-sm w-100" data-lid="${link.id}" data-domain="${decodeHtml(link.domain)}">Buy</button>`;
                    const rowHtml = `<tr data-lid="${link.id}" data-domain="${decodeHtml(link.domain)}">
                        <td><input type="checkbox" class="rowChk" value="${link.id}" ${link.is_purchased ? 'disabled' : ''}></td>
                        <td><span class="fw-bold">${decodeHtml(link.domain)}</span></td>
                        <td>${link.alexa1 || 0}</td>
                        <td>${link.alexa2 || 0}</td>
                        <td><?= $COST_PER_LINK ?> + (<?= $COST_PER_MONTH ?>/month)</td>
                        <td>${typeBadge}</td>
                        <td>${link.domain_year || '–'}</td>
                        <td>${link.sure || '–'}</td>
                        <td>${link.country || '–'}</td>
                        <td>${link.created_at || '–'}</td>
                        <td>${buyBtn}</td>
                    </tr>`;
                    table.row.add($(rowHtml));
                });
                table.draw(false);
                updateBulkState();
            }
        }, 'json').always(() => { $('#searchLoading').hide(); isSearching = false; });
    }

    $('#searchInput').on('keyup', e => { if (e.key === 'Enter') performSearch($(e.target).val().trim()); });
    $('#searchBtn').on('click', () => performSearch($('#searchInput').val().trim()));
    $('#clearSearchBtn').on('click', () => { $('#searchInput').val(''); performSearch(''); });

    // DELEGATED EVENTS
    $(document).on('change', '.rowChk', updateBulkState);
    $(document).on('change', '#chkAll', function() {
        const checked = $(this).is(':checked');
        $('#marketTable tbody .rowChk:enabled').filter(function() { return $(this).closest('tr').is(':visible'); }).prop('checked', checked);
        updateBulkState();
    });
    $(document).on('click', '.buy-btn', function(e) {
        e.preventDefault();
        const lid = $(this).data('lid');
        const domain = $(this).data('domain');
        $('#modalLid').val(lid);
        $('#buyModal .modal-title').text('Buy Link: ' + decodeHtml(domain));
        $('#modalTarget').val('');
        $('#modalKeyword').val('');
        $('#modalDuration').val('30d');
        updateSingleCostDisplay();
        $('#buyAlert').html('');
        buyModal.show();
    });

    $('#bulkDuration').on('change', updateBulkState);
    $('#modalDuration').on('change', updateSingleCostDisplay);

    const bulkModal = new bootstrap.Modal('#bulkModal');
    $('#btnBulkAdd').on('click', function() {
        $('#bulkAlert').html('');
        $('#bulkSiteAddress').val('');
        $('#bulkKeyword').val('');
        $('#bulkDuration').val('30d');
        updateBulkState();
        bulkModal.show();
    });

    $('#confirmBulkBtn').on('click', function() {
        const ids = $('.rowChk:enabled:checked').filter(function() { return $(this).closest('tr').is(':visible'); }).map((_, el) => $(el).val()).get();
        const site_address = $('#bulkSiteAddress').val().trim();
        const keyword = $('#bulkKeyword').val().trim();
        const duration = $('#bulkDuration').val();
        if (ids.length === 0) { $('#bulkAlert').html('<div class="alert alert-warning">Select at least one site.</div>'); return; }
        if (!site_address) { $('#bulkAlert').html('<div class="alert alert-warning">Please enter Your Website URL.</div>'); return; }
        if (!keyword) { $('#bulkAlert').html('<div class="alert alert-warning">Please enter Keyword.</div>'); return; }
        const cost = calculateCost(ids.length, duration);
        $('#confirmBulkBtn').prop('disabled', true).text('Processing...');
        $('#bulkAlert').html('<div class="alert alert-info">Processing ' + ids.length + ' links (Cost: ' + cost.totalCost + ' credits)...</div>');
        $.ajax({
            url: 'link-depo.php?ajax=bulk',
            method: 'POST',
            dataType: 'json',
            timeout: 45000,
            data: { ids: ids, site_address: site_address, keyword: keyword, duration: duration },
            success: function(res) {
                if (!res || !res.ok) {
                    $('#bulkAlert').html('<div class="alert alert-danger">' + (res && res.msg ? res.msg : 'Failed') + '</div>');
                    return;
                }
                (res.purchased || []).forEach(function(lid) {
                    const row = $('tr[data-lid="'+lid+'"]');
                    row.find('.buy-btn').replaceWith('<span class="lock-badge">Purchased Lock</span>');
                    row.find('.rowChk').prop('checked', false).prop('disabled', true);
                    row.removeClass('table-success');
                });
                if (typeof res.new_balance !== 'undefined') {
                    $('#liveCreditBadge').text(res.new_balance);
                }
                const purchasedN = (res.purchased || []).length;
                const failedN = (res.failed || []).length;
                const skippedN = (res.skipped || []).length;
                let placementMsg = '';
                if (res.placement_results && res.placement_results.length > 0) {
                    const successCount = res.placement_results.filter(r => r.success).length;
                    const failedCount = res.placement_results.length - successCount;
                    placementMsg = `<br>Auto Placement: ${successCount} success, ${failedCount} failed`;
                }
                let refundMsg = '';
                if (res.total_refunded && res.total_refunded > 0) {
                    refundMsg = `<br>Refunded: ${res.total_refunded} credits`;
                }
                $('#bulkAlert').html('<div class="alert alert-success">' + purchasedN + ' link(s) purchased, ' + failedN + ' failed, ' + skippedN + ' skipped! Cost: ' + (res.total_cost || cost.totalCost) + ' credits' + placementMsg + refundMsg + '</div>');
                $('#chkAll').prop('checked', false);
                updateBulkState();
                setTimeout(() => bulkModal.hide(), 4000);
            },
            error: function(xhr, status, error) {
                let errMsg = 'Network/Server error: ' + status;
                if (xhr.responseText) errMsg += ' - ' + xhr.responseText.substring(0, 100);
                $('#bulkAlert').html('<div class="alert alert-danger">' + errMsg + '</div>');
            },
            complete: function() {
                $('#confirmBulkBtn').prop('disabled', false).text('Process (' + $('#bulkCostDisplay').text() + ' credits)');
            }
        });
    });

    const buyModal = new bootstrap.Modal('#buyModal');
    $('#confirmBuy').on('click', function() {
        const target_url = $('#modalTarget').val().trim();
        const keyword = $('#modalKeyword').val().trim();
        if (!target_url) { $('#buyAlert').html('<div class="alert alert-warning">Please enter Your Website URL.</div>'); return; }
        if (!keyword) { $('#buyAlert').html('<div class="alert alert-warning">Please enter Keyword.</div>'); return; }
        const data = $('#buyForm').serialize();
        const lid = $('#modalLid').val();
        $('#confirmBuy').prop('disabled', true).text('Processing...');
        $('#buyAlert').html('<div class="alert alert-info">Working... (This may take up to 30 seconds)</div>');
        $.ajax({
            url: 'link-depo.php?ajax=buy',
            method: 'POST',
            dataType: 'json',
            timeout: 45000,
            data: data,
            success: function(res) {
                if (res && res.ok) {
                    if (res.placed) {
                        const row = $('tr[data-lid="'+lid+'"]');
                        row.find('.buy-btn').replaceWith('<span class="lock-badge">Purchased Lock</span>');
                        row.find('.rowChk').prop('checked', false).prop('disabled', true);
                        row.removeClass('table-success');
                    }
                    if (typeof res.new_balance !== 'undefined') {
                        $('#liveCreditBadge').text(res.new_balance);
                    }
                    let placementMsg = '';
                    if (res.placement_msg) placementMsg = '<br>' + res.placement_msg;
                    if (res.refunded) {
                        $('#buyAlert').html('<div class="alert alert-warning">' + res.msg + placementMsg + '</div>');
                    } else {
                        $('#buyAlert').html('<div class="alert alert-success">' + res.msg + placementMsg + ' Cost: ' + (res.cost || '?') + ' credits</div>');
                    }
                    setTimeout(() => buyModal.hide(), 3000);
                } else {
                    $('#buyAlert').html('<div class="alert alert-danger">'+(res && res.msg ? res.msg : 'Purchase failed')+'</div>');
                }
            },
            complete: function() {
                $('#confirmBuy').prop('disabled', false).text('Confirm Buy');
            }
        });
    });

    function pollNewLinks() {
        const url = currentSearchTerm 
            ? `link-depo.php?ajax=new_links&last_id=${lastId}&search=${encodeURIComponent(currentSearchTerm)}`
            : `link-depo.php?ajax=new_links&last_id=${lastId}`;
        $.get(url, function(res) {
            if (res && res.ok && res.links && res.links.length > 0) {
                res.links.forEach(link => {
                    if (link.id > lastId) lastId = link.id;
                    addNewRowToTop(link);
                });
                if (res.links.length > 0) {
                    const notif = $(`<div class="alert alert-success position-fixed" style="top:20px;right:20px;z-index:9999;">
                        ${res.links.length} NEW link(s) added to Page 1!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`);
                    $('body').append(notif);
                    setTimeout(() => notif.alert('close'), 5000);
                }
            }
        }, 'json');
        setTimeout(pollNewLinks, 15000);
    }
    pollNewLinks();

    // Initialize
    updateBulkState();
})();
</script>
</body>
</html>