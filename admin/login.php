<?php
// admin/login.php - FINAL FIXED VERSION
$root = dirname(__DIR__);
if (session_status() === PHP_SESSION_NONE) session_start();
include $root . "/config.php";

global $db;

/** Safe esc helper */
function esc($v){
    global $db;
    return isset($db) && method_exists($db,'escape') ? $db->escape($v) : addslashes($v);
}

/** ✅ FIXED: Simple table check */
try {
    $db->query("CREATE TABLE IF NOT EXISTS k_admin_login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        username VARCHAR(190) NULL,
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        success TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {
    // Silent fail
}

/** ✅ FIXED: SIMPLE login logger */
function log_admin_login($admin_id, $username, $success) {
    global $db;
    
    if (empty($username)) $username = 'unknown';
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $admin_id_val = $admin_id ? (int)$admin_id : 'NULL';
    $username_esc = esc($username);
    $ip_esc = esc($ip);
    $ua_esc = esc($ua);
    $success_val = (int)$success;
    
    $sql = "INSERT INTO k_admin_login_logs 
            (admin_id, username, ip, user_agent, success) 
            VALUES ($admin_id_val, '$username_esc', '$ip_esc', '$ua_esc', $success_val)";
    
    return $db->query($sql);
}

// ✅ Redirect if already logged in
if (!empty($_SESSION['user']) && ($_SESSION['user']->role ?? '') === 'admin') {
    header('Location: index.php'); 
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ident = trim($_POST['ident'] ?? '');
    $pass = $_POST['password'] ?? '';
    
    if (empty($ident) || empty($pass)) {
        $msg = '⚠️ সব ফিল্ড পূরণ করুন';
        log_admin_login(0, empty($ident) ? 'empty_user' : $ident, 0);
    } else {
        $esc_ident = esc($ident);
        $user_data = null;
        
        // Try k_admins table
        $admin = $db->get_row("SELECT * FROM k_admins WHERE username='$esc_ident' OR email='$esc_ident' LIMIT 1");
        if ($admin && password_verify($pass, $admin->password)) {
            $user_data = $admin;
            $user_type = 'admin';
        }
        
        // Try k_users table
        if (!$user_data) {
            $user = $db->get_row("SELECT * FROM k_users WHERE (username='$esc_ident' OR email='$esc_ident') AND role='admin' LIMIT 1");
            if ($user && password_verify($pass, $user->password)) {
                $user_data = $user;
                $user_type = 'user';
            }
        }
        
        if ($user_data) {
            // ✅ SUCCESS - Log and create session
            $_SESSION['user'] = (object)[
                'id' => (int)($user_data->user_id ?? $user_data->id),
                'username' => $user_data->username,
                'email' => $user_data->email,
                'role' => 'admin'
            ];
            
            log_admin_login($_SESSION['user']->id, $user_data->username, 1);
            header('Location: index.php');
            exit;
        } else {
            // ✅ FAILED - Log failed attempt
            log_admin_login(0, $ident, 0);
            $msg = '❌ ভুল ইউজারনেম বা পাসওয়ার্ড';
        }
    }
}
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#030611;color:#e6eef8;font-family:Inter,system-ui;padding-top:60px;}
.box{max-width:420px;margin:auto;padding:25px;border-radius:12px;background:linear-gradient(180deg,rgba(255,255,255,0.03),rgba(255,255,255,0.01));border:1px solid rgba(96,165,250,0.08);box-shadow:0 12px 36px rgba(0,0,0,.6);}
.btn-hack{background:linear-gradient(90deg,#ef4444,#f97316);border:0;color:white;font-weight:600;}
.btn-hack:hover{background:linear-gradient(90deg,#dc2626,#ea580c);color:white;}
.err{background:rgba(239,68,68,0.1);color:#fca5a5;padding:8px;border-radius:8px;margin-bottom:10px;text-align:center}
.form-control{background:rgba(255,255,255,0.05);border:1px solid rgba(96,165,250,0.2);color:#e6eef8;}
.form-control:focus{background:rgba(255,255,255,0.08);border-color:#00e6ff;box-shadow:0 0 0 0.2rem rgba(0,230,255,0.25);color:#e6eef8;}
.form-label{color:#9ae6b4;font-weight:500;}
</style>
</head>
<body>
<div class="box">
<h4 class="mb-3 text-center text-warning">⚙️ Admin Login</h4>
<?php if($msg): ?>
<div class="err"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="post">
<div class="mb-3">
<label class="form-label">Username / Email</label>
<input type="text" name="ident" class="form-control" required value="<?= htmlspecialchars($_POST['ident'] ?? '') ?>">
</div>
<div class="mb-3">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" required>
</div>
<button class="btn btn-hack w-100">Login</button>
</form>

</div>
</body>
</html>