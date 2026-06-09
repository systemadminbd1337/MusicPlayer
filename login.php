<?php
// login.php — Systemadminbd Hacker Edition (Login Only + Success Animation + Auto Log + last_login update)
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config.php";

// --- Cloudflare-aware helpers ---
function is_public_ip($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
}
function get_client_ip(array $trusted_proxies = []) {
    $s = $_SERVER;
    if (!empty($s['HTTP_CF_CONNECTING_IP']) && filter_var($s['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        return $s['HTTP_CF_CONNECTING_IP'];
    }
    $remote = $s['REMOTE_ADDR'] ?? null;
    $use_forwarded = false;
    if ($remote && $trusted_proxies && in_array($remote, $trusted_proxies, true)) $use_forwarded = true;
    if (!empty($s['HTTP_X_REAL_IP']) && filter_var($s['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        $ip = $s['HTTP_X_REAL_IP']; if (is_public_ip($ip) || $use_forwarded) return $ip;
    }
    if (!empty($s['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $s['HTTP_X_FORWARDED_FOR']));
        foreach ($parts as $p) {
            if (!filter_var($p, FILTER_VALIDATE_IP)) continue;
            if (is_public_ip($p)) return $p;
            if ($use_forwarded) return $p;
        }
    }
    if (!empty($s['HTTP_CLIENT_IP']) && filter_var($s['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $ip = $s['HTTP_CLIENT_IP']; if (is_public_ip($ip) || $use_forwarded) return $ip;
    }
    if (!empty($remote) && filter_var($remote, FILTER_VALIDATE_IP)) return $remote;
    return '0.0.0.0';
}
function get_country_from_ip($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') return 'Localhost';
    if ($ip === '0.0.0.0') return 'Unknown';
    $ctx = stream_context_create(['http'=>['timeout'=>2]]);
    try {
        $resp = @file_get_contents("https://ipapi.co/{$ip}/country_name/", false, $ctx);
        if ($resp && strlen($resp) < 80) return trim($resp);
    } catch (Throwable $e) {}
    return 'Unknown';
}

// --- Security Headers ---
$nonce = base64_encode(random_bytes(12));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self';");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");

// --- Helper ---
function post_input($k){return isset($_POST[$k]) ? trim($_POST[$k]) : '';}

// --- State ---
$error=''; $success=''; $userName='';

// --- Login Logic ---
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login'){
  $username=post_input('username');
  $password=post_input('password');
  if($username===''||$password===''){
    $error="⚠️ Both fields are required!";
  } else {
    try{
      $stmt=$pdo->prepare("SELECT * FROM k_users WHERE username=:u OR email=:u LIMIT 1");
      $stmt->execute([':u'=>$username]);
      $user=$stmt->fetch(PDO::FETCH_ASSOC);

      if($user && password_verify($password,$user['password'])){
        // ✅ Login success
        $_SESSION['user']=(object)$user;
        $userName=$user['username'];
        $success="Welcome back, {$userName}!";

        // --- Auto Log the successful login ---
        try {
          $trusted_proxies = [];
          $uid = (int)$user['id'];
          $ip  = get_client_ip($trusted_proxies);
          $ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
          $country = get_country_from_ip($ip);

          // Insert into logs
          $logSQL = "INSERT INTO k_user_login_logs (user_id, username, ip, country, user_agent, success, created_at)
                     VALUES (:uid, :username, :ip, :country, :ua, 1, NOW())";
          $logStmt = $pdo->prepare($logSQL);
          $logStmt->execute([
            ':uid' => $uid,
            ':username' => $userName,
            ':ip' => $ip,
            ':country' => $country,
            ':ua' => $ua
          ]);

          // ✅ Update k_users last_login fields (for index.php)
          $upd = $pdo->prepare("UPDATE k_users 
                                SET last_login_ip=:ip, last_login_country=:country, last_login_time=NOW() 
                                WHERE id=:uid");
          $upd->execute([':ip'=>$ip, ':country'=>$country, ':uid'=>$uid]);

        } catch (Throwable $logErr) {
          error_log("Login log insert error: ".$logErr->getMessage());
        }

      } else {
        // ❌ Wrong login
        $error="❌ Invalid username or password.";
        try {
          $trusted_proxies = [];
          $ip  = get_client_ip($trusted_proxies);
          $ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
          $country = get_country_from_ip($ip);
          $failSQL = "INSERT INTO k_user_login_logs (user_id, username, ip, country, user_agent, success, created_at)
                      VALUES (0, :username, :ip, :country, :ua, 0, NOW())";
          $failStmt = $pdo->prepare($failSQL);
          $failStmt->execute([
            ':username' => $username,
            ':ip' => $ip,
            ':country' => $country,
            ':ua' => $ua
          ]);
        } catch (Throwable $failErr) {
          error_log("Login fail log error: ".$failErr->getMessage());
        }
      }

    }catch(Throwable $e){ $error="System error: ".htmlspecialchars($e->getMessage()); }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HackLink Panel — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#05060a;--neon1:#00ff9d;--neon2:#00e6ff;}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
background:radial-gradient(circle at 20% 20%,rgba(0,255,157,.06),transparent 60%),#000814;
font-family:'Share Tech Mono',monospace;color:#c7f9e8;overflow:hidden;}
.card{background:rgba(255,255,255,.02);border-radius:16px;border:1px solid rgba(255,255,255,.05);
box-shadow:0 0 30px rgba(0,230,255,.08);padding:2rem;width:100%;max-width:420px;}
h1{font-family:'Orbitron',sans-serif;text-align:center;color:var(--neon2);margin-bottom:1.2rem;text-transform:uppercase;letter-spacing:1px;}
.form-control{width:100%;padding:14px;border-radius:10px;border:1px solid rgba(255,255,255,.06);
background:rgba(0,0,0,.45);color:#bfffe9;margin-bottom:12px;}
.btn{width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(90deg,var(--neon1),var(--neon2));
color:#001214;font-weight:700;cursor:pointer;transition:.2s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(0,230,255,.15);}
.alert{padding:10px;border-radius:8px;text-align:center;margin-bottom:10px;font-size:.9rem;}
.alert-danger{background:rgba(255,99,99,.1);border:1px solid rgba(255,99,99,.3);color:#ff6b6b;}
.alert-success{background:rgba(0,255,157,.1);border:1px solid rgba(0,255,157,.3);color:#00ff9d;}
.links{text-align:center;margin-top:1rem;font-size:.9rem;}
.links a{color:#00e6ff;text-decoration:none;margin:0 8px;}
.links a:hover{color:#00ff9d;}
.success-overlay{
 position:fixed;inset:0;background:rgba(0,10,25,.92);
 display:flex;flex-direction:column;align-items:center;justify-content:center;
 z-index:9999;text-align:center;font-family:'Orbitron',sans-serif;color:#00ff9d;
 animation:fadeIn .6s ease forwards;
}
.success-overlay h2{font-size:2rem;text-shadow:0 0 15px #00ff9d;}
.countdown{font-size:1.3rem;color:#00e6ff;margin-top:10px;text-shadow:0 0 10px #00e6ff;}
.bar{margin-top:20px;width:300px;height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;}
.bar-inner{width:0;height:100%;background:linear-gradient(90deg,var(--neon1),var(--neon2));animation:fillBar 3s linear forwards;}
@keyframes fillBar{from{width:0;}to{width:100%;}}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
</style>
</head>
<body>
<div class="card">
  <h1>HackLink Login</h1>
  <?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if(!$success): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="action" value="login">
    <input type="text" name="username" placeholder="Username or Email" class="form-control" required>
    <input type="password" name="password" placeholder="Password" class="form-control" required>
    <button type="submit" class="btn">Sign In</button>
  </form>
  <div class="links">
    <a href="register.php">Register</a> |
    <a href="recover_section.php">Forgot Password?</a>
  </div>
  <?php else: ?>
    <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
  <?php endif; ?>
</div>

<?php if($success): ?>
<!-- ✅ Neon Success Animation + Countdown -->
<div class="success-overlay" id="successOverlay">
  <h2>✅ LOGIN SUCCESSFUL</h2>
  <p>Welcome back, <strong><?=htmlspecialchars($userName)?></strong></p>
  <div class="countdown" id="countdown">Redirecting in 3...</div>
  <div class="bar"><div class="bar-inner"></div></div>
</div>
<script nonce="<?= $nonce ?>">
let sec=3;
const cd=document.getElementById('countdown');
const timer=setInterval(()=>{
  sec--; cd.textContent=`Redirecting in ${sec}...`;
  if(sec<=0){clearInterval(timer);window.location.href='index.php';}
},1000);
</script>
<?php endif; ?>

</body>
</html>
<?php ob_end_flush(); ?>
