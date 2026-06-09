<?php
// recover_section.php — Standalone Neon Recovery Terminal (Systemadminbd Edition)
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config.php";

// --- Security Headers ---
$nonce = base64_encode(random_bytes(12));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self';");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");

// --- Logic ---
$msg='';$type='info';
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['forgot_username','forgot_password'])){
  $act=$_POST['action'];
  $em=filter_var(trim($_POST['fu_email']??$_POST['fp_email']??''),FILTER_VALIDATE_EMAIL);
  $msgU='If that email exists, we’ve sent your username.';$msgP='If that email exists, we’ve sent a reset link.';
  if($act==='forgot_username' && $em){
    try{$u=$pdo->query("SELECT username,email FROM k_users WHERE email=".$pdo->quote($em))->fetch(PDO::FETCH_ASSOC);
      if($u){@mail($u['email'],'Username Recovery',"Your username: {$u['username']}");}}catch(Throwable $e){}
    $msg=$msgU;$type='success';
  }elseif($act==='forgot_password' && $em){
    try{$u=$pdo->query("SELECT id,email FROM k_users WHERE email=".$pdo->quote($em))->fetch(PDO::FETCH_ASSOC);
      if($u){
        $t=bin2hex(random_bytes(24));$exp=date('Y-m-d H:i:s',time()+3600);
        $pdo->exec("CREATE TABLE IF NOT EXISTS k_password_resets (
          id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(255),token VARCHAR(255),expires_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $pdo->prepare("DELETE FROM k_password_resets WHERE email=:e")->execute([':e'=>$em]);
        $pdo->prepare("INSERT INTO k_password_resets(email,token,expires_at)VALUES(:e,:t,:x)")
             ->execute([':e'=>$em,':t'=>$t,':x'=>$exp]);
        $url="https://hack-link.com/reset_password.php?token=$t&email=".urlencode($em);
        @mail($u['email'],'Password Reset',"Reset your password: $url (valid 1h)");
      }}catch(Throwable $e){}
    $msg=$msgP;$type='success';
  } else {$msg='Invalid request.';$type='danger';}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Account Recovery — HackLink</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#05060a;--neon1:#00ff9d;--neon2:#00e6ff;}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
background:#000814;font-family:'Share Tech Mono',monospace;color:#c7f9e8;}
.card{background:rgba(255,255,255,.02);border-radius:14px;
border:1px solid rgba(0,255,157,.15);box-shadow:0 0 30px rgba(0,230,255,.1);
padding:2rem;max-width:600px;width:95%;}
h2{font-family:'Orbitron',sans-serif;color:var(--neon2);text-align:center;margin-bottom:1rem;text-transform:uppercase;}
.section{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;}
.form-box{background:rgba(0,15,25,.5);padding:1rem 1.2rem;border-radius:10px;border:1px solid rgba(0,230,255,.1);}
.form-box h4{color:var(--neon1);font-size:1rem;text-transform:uppercase;letter-spacing:.6px;margin-bottom:.8rem;}
.form-control{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,.06);
background:rgba(0,0,0,.5);color:#eafef4;margin-bottom:10px;}
.btn{width:100%;padding:10px;border:none;border-radius:8px;background:linear-gradient(90deg,var(--neon1),var(--neon2));
color:#001214;font-weight:700;cursor:pointer;transition:.2s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(0,230,255,.15);}
.alert{padding:10px;border-radius:8px;text-align:center;margin-bottom:10px;font-size:.9rem;}
.alert-success{background:rgba(0,255,157,.1);border:1px solid rgba(0,255,157,.3);color:#00ff9d;}
.alert-danger{background:rgba(255,99,99,.1);border:1px solid rgba(255,99,99,.3);color:#ff6b6b;}
.footer{text-align:center;margin-top:20px;}
.footer a{color:var(--neon2);text-decoration:none;border:1px solid rgba(0,230,255,.3);padding:6px 14px;border-radius:6px;}
.footer a:hover{background:rgba(0,230,255,.1);color:#00ff9d;}
</style>
</head>
<body>
<div class="card">
  <h2>🔐 Account Recovery</h2>
  <?php if($msg): ?>
    <div class="alert alert-<?=htmlspecialchars($type)?>"><?=htmlspecialchars($msg)?></div>
  <?php endif; ?>

  <div class="section">
    <!-- Username -->
    <div class="form-box">
      <h4>Recover Username</h4>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="forgot_username">
        <input type="email" name="fu_email" class="form-control" placeholder="Registered Email" required>
        <button type="submit" class="btn">Send Username</button>
      </form>
    </div>

    <!-- Password -->
    <div class="form-box">
      <h4 style="color:#ffe166;">Reset Password</h4>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="forgot_password">
        <input type="email" name="fp_email" class="form-control" placeholder="Email for Reset Link" required>
        <button type="submit" class="btn" style="background:linear-gradient(90deg,#ffe166,#ffb700);color:#111;">Send Reset Link</button>
      </form>
    </div>
  </div>

  <div class="footer">
    <a href="https://hack-link.com/login.php">⬅ Back to Login</a>
  </div>
</div>
</body>
</html>
<?php ob_end_flush(); ?>
