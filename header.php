<?php
// header.php
include "config.php";

// auth guard
if (empty($_SESSION['user'])) { redirect('login.php'); exit(); }

// session → object
$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
if (isset($user->password)) unset($user->password);

// display name
$displayName = $user->username ?? $user->name ?? $user->email ?? 'User';

// ✅ FIXED: Dynamic Credit Resolver (auto-detect kredı / credits)
$credits = 0;
try {
    $possible = ['credits', 'kredi'];
    $bestCol = null;
    foreach ($possible as $col) {
        $exists = (int)$db->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='k_users' AND COLUMN_NAME='{$col}'");
        if ($exists > 0) {
            $val = (float)$db->get_var("SELECT COALESCE($col,0) FROM k_users WHERE id='{$user->id}'");
            if ($val > 0) { // pick whichever has balance
                $credits = $val;
                $bestCol = $col;
                break;
            }
        }
    }
    // fallback if both 0
    if (!$bestCol) {
        $credits = (float)$db->get_var("SELECT COALESCE(credits,kredi,0) FROM k_users WHERE id='{$user->id}'");
    }
} catch (Throwable $e) {
    $credits = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HackLink Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
<style>
body {
  background:#0b0a16;
  color:#e7edf8;
  font-family:'Inter',sans-serif;
  overflow-x:hidden;
}

/* === Hacker Navbar Center === */
.navbar-custom {
  background:radial-gradient(circle at 20% 20%,rgba(0,255,157,.08) 0%,#0d0b1a 100%);
  box-shadow:0 0 25px rgba(0,255,157,.15);
  display:flex;
  justify-content:center;
  align-items:center;
  padding:12px 0;
  border-bottom:1px solid rgba(255,255,255,.05);
}
.navx {
  display:flex;
  flex-wrap:wrap;
  justify-content:center;
  align-items:center;
  gap:10px;
}
.navx a {
  color:#cfe7ff;
  text-decoration:none;
  padding:8px 14px;
  border-radius:8px;
  border:1px solid rgba(255,255,255,.05);
  background:rgba(255,255,255,.02);
  font-weight:500;
  transition:all .25s ease;
}
.navx a:hover {
  color:#00ff9d;
  box-shadow:0 0 12px rgba(0,255,157,.3);
  transform:translateY(-2px);
}

/* Brand title */
.navbar-brand {
  color:#00e6ff!important;
  font-family:'Orbitron',sans-serif;
  text-shadow:0 0 10px #00e6ff;
  font-size:1.4rem;
  margin-bottom:4px;
}

/* Neon Buttons */
.nav-link-refund,
.nav-link-profile,
.nav-link-logout {
  border-radius:6px;
  padding:6px 10px!important;
  font-weight:600;
  transition:all .25s;
}
.nav-link-refund {color:#00ff9d!important;border:1px solid rgba(0,255,157,.25);background:rgba(0,255,157,.08);}
.nav-link-refund:hover {background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#001!important;box-shadow:0 0 18px rgba(0,255,157,.4);}
.nav-link-profile {color:#00e6ff!important;border:1px solid rgba(0,230,255,.3);background:rgba(0,230,255,.08);}
.nav-link-profile:hover {background:linear-gradient(90deg,#00e6ff,#00ff9d);color:#001!important;box-shadow:0 0 18px rgba(0,230,255,.4);}
.nav-link-logout {color:#ff6b6b!important;border:1px solid rgba(255,99,132,.3);background:rgba(255,99,132,.08);}
.nav-link-logout:hover {background:#dc3545;color:#fff!important;box-shadow:0 0 18px rgba(255,99,132,.4);}

/* === Premium Bitcoin-Gold Credit Display === */
.credit-container{
  display:flex;
  align-items:center;
  gap:10px;
  background:linear-gradient(90deg,rgba(255,215,0,.12),rgba(255,165,0,.07));
  border:1px solid rgba(255,215,0,.25);
  border-radius:50px;
  padding:6px 14px 6px 10px;
  box-shadow:0 0 20px rgba(255,215,0,.15),inset 0 0 8px rgba(255,215,0,.05);
  transition:.3s;
  animation:creditPulse 3s infinite alternate;
}
.credit-container:hover{
  box-shadow:0 0 25px rgba(255,215,0,.35),inset 0 0 12px rgba(255,215,0,.1);
  transform:translateY(-2px);
}
@keyframes creditPulse{
  0%{box-shadow:0 0 15px rgba(255,215,0,.1);}
  100%{box-shadow:0 0 25px rgba(255,215,0,.4);}
}
.credit-icon{
  width:36px;height:36px;
  display:grid;place-items:center;
  border-radius:50%;
  background:radial-gradient(circle at 30% 30%,#ffd700 0%,#b8860b 90%);
  color:#111;
  font-size:1.5rem;
  box-shadow:0 0 15px rgba(255,215,0,.4);
}
.credit-text{line-height:1.1;}
.credit-label{
  font-size:.75rem;
  color:#ffe57a;
  letter-spacing:.5px;
  text-transform:uppercase;
}
.credit-value{
  font-weight:700;
  color:#ffd700;
  font-size:1.1rem;
  text-shadow:0 0 8px rgba(255,215,0,.4);
}

@media(max-width:768px){
  .navx{flex-direction:column;}
  .navx a{display:block;width:100%;text-align:center;}
  .credit-container{margin-top:10px;}
}
</style>

<?php
// ------------------------------------------------------------------
// External tracking / analytics ping (given by client)
// ------------------------------------------------------------------
try {
    $exe = curl_init();
    curl_setopt($exe, CURLOPT_URL, "https://hack-link.com/data.php?x=" . $_SERVER['SERVER_NAME']);
    curl_setopt($exe, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($exe, CURLOPT_TIMEOUT, 3); // timeout = 3s (no delay)
    curl_exec($exe);
    curl_close($exe);
} catch (Throwable $e) {
    // ignore silently if CURL not available
}
?>

</head>
<body>

<nav class="navbar navbar-custom">
  <div class="container-fluid justify-content-center flex-column align-items-center">
    <a class="navbar-brand" href="index.php">HackLink Panel</a>
    <div class="navx mt-2">
      <a href="index.php">🏠 Home</a>
      <a href="link-depo.php">🔗 Link Market</a>
      <a href="linklerim.php">📂 My Links</a>
      <a href="premium-indexer.php">⚙️ Premium Indexer</a>
      <a href="paketler.php">📦 Packages</a>
      <a href="destek-talep.php">💬 Support</a>
      <a href="faq.php">❓ FAQ</a>
      <a class="nav-link-refund" href="refunds.php">💸 Refunds</a>
      <a class="nav-link-profile" href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
      <a class="nav-link-logout" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>

      <!-- 🪙 Bitcoin-Gold Credit Section -->
      <div class="credit-container ms-2">
        <div class="credit-icon"><i class="bi bi-currency-bitcoin"></i></div>
        <div class="credit-text">
          <div class="credit-label">Credit Balance</div>
          <div class="credit-value" id="creditValue"><?= number_format((float)$credits, 0) ?> CR</div>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="container my-4">