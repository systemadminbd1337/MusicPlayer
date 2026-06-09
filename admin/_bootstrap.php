<?php
// admin/_bootstrap.php
// Improved: Robust database setup, error handling, and debugging

// ✅ Define paths
$__ADMIN_ROOT = __DIR__;
$__APP_ROOT = dirname($__ADMIN_ROOT);

// ✅ Start output buffering and session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

// ✅ Include config and validate
$cfgPath = $__APP_ROOT . '/config.php';
if (!file_exists($cfgPath)) {
    ob_end_clean();
    die("Error: config.php not found at $cfgPath. Please ensure the configuration file exists.");
}
include $cfgPath;

// ✅ Ensure global DB object exists
global $db;
if (!isset($db) || !is_object($db)) {
    ob_end_clean();
    die("Error: Database object \$db not initialized in config.php. Please check your database configuration.");
}

// ✅ Test database connection
try {
    // Assuming $db uses PDO or a similar interface
    if (method_exists($db, 'query')) {
        $db->query("SELECT 1");
    } else {
        throw new Exception("Database object does not support query method.");
    }
} catch (Throwable $e) {
    ob_end_clean();
    die("Error: Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// ✅ Validate user login
if (empty($_SESSION['user'])) {
    if (ob_get_length()) ob_end_clean();
    header("Location: $__APP_ROOT/login.php");
    exit;
}

$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];

// ✅ Role check
$role = isset($user->role) ? strtolower($user->role) : '';
if ($role !== 'admin') {
    if (ob_get_length()) ob_end_clean();
    http_response_code(403);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Access Denied</title></head>
    <body style='background:#0b0d21;color:#fff;font-family:Inter,system-ui;padding:40px;'>
    <h2>🚫 Access Denied</h2>
    <p>Admin access required.</p>
    <p><a href='$__APP_ROOT/index.php' style='color:#9ae6b4'>Back to Dashboard</a></p>
    </body></html>";
    exit;
}

// ✅ Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ✅ Utility functions
function db_escape($value) {
    global $db;
    if (isset($db) && method_exists($db, 'escape')) {
        return $db->escape($value);
    }
    // Fallback to PDO quote if available, otherwise error
    if ($db instanceof PDO) {
        return $db->quote($value);
    }
    throw new Exception("No secure escape method available for database queries.");
}

function i($n) {
    return (int)$n;
}

function b($n) {
    return $n ? 1 : 0;
}

// ✅ NEW: Admin login logging function
function log_admin_login_attempt($admin_id, $username, $success) {
    global $db;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Safe escape using your existing db_escape function
        $admin_id = $admin_id ? (int)$admin_id : 'NULL';
        $username = db_escape($username);
        $ip = db_escape($ip);
        $user_agent = db_escape($user_agent);
        $success = $success ? 1 : 0;
        
        $sql = "INSERT INTO k_admin_login_logs 
                (admin_id, username, ip, user_agent, success) 
                VALUES ($admin_id, '$username', '$ip', '$user_agent', $success)";
        
        return $db->query($sql);
    } catch (Exception $e) {
        // Silent fail - don't break login if logging fails
        error_log("Admin login log error: " . $e->getMessage());
        return false;
    }
}

// ✅ NEW: Auto-create admin logs table if missing
function create_admin_logs_table() {
    global $db;
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS k_admin_login_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NULL,
                username VARCHAR(190) NULL,
                ip VARCHAR(45) NULL,
                user_agent TEXT NULL,
                success TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(admin_id),
                INDEX(created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        return true;
    } catch (Throwable $e) {
        error_log("Error creating admin logs table: " . $e->getMessage());
        return false;
    }
}

// ✅ NEW: Call table creation on bootstrap
create_admin_logs_table();

// ✅ End buffering
if (ob_get_length()) ob_end_flush();
?>