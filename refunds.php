<?php
include "header.php"; // ধরে নিচ্ছি এটা সেশন+DB+UI setup করে

if (empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
$uid = (int)$user->id;

// ইউজারের রিফান্ড ইতিহাস আনো
$refunds = $db->get_results("
  SELECT r.*, o.link
  FROM k_refunds r
  LEFT JOIN k_orders o ON o.id = r.order_id
  WHERE r.user_id = {$uid}
  ORDER BY r.id DESC
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>💸 Refunds — My Account</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@600&display=swap" rel="stylesheet">
<style>
:root{--bg:#05060a;--neon1:#00ff9d;--neon2:#00e6ff;--muted:#8b9aa3;}
body{
  background:
    radial-gradient(1000px 300px at 10% 10%,rgba(0,230,255,0.03),transparent 5%),
    radial-gradient(800px 300px at 90% 90%,rgba(0,255,157,0.02),transparent 5%),
    var(--bg);
  color:#dfffee;
  font-family:'Share Tech Mono',monospace;
}
.container{max-width:1100px;padding:30px 18px;}
.h-title{font-size:22px;font-weight:700;color:var(--neon2);text-shadow:0 0 8px var(--neon2);}
.back-link a{color:#00e6ff;text-decoration:none;}
.back-link a:hover{text-shadow:0 0 10px var(--neon1);}
.neon-card{
  background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(255,255,255,0.05);
  border-radius:12px;padding:20px;
  box-shadow:0 0 25px rgba(0,230,255,.06), inset 0 0 10px rgba(0,255,157,.05);
}
.table thead th{color:#00e6ff;border-bottom:1px solid rgba(0,255,157,0.25);}
.table tbody td{color:#e6eef8;font-size:14px;}
.badge-ref{background:linear-gradient(90deg,rgba(0,255,157,0.15),rgba(0,230,255,0.12));border:1px solid rgba(0,255,157,0.25);color:#00ff9d;}
.text-muted{color:var(--muted)!important;}
.footer-note{text-align:center;color:var(--muted);margin-top:20px;font-size:13px;}
</style>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="h-title">💸 My Refunds</div>
    <div class="back-link"><a href="index.php">⬅ Back to Dashboard</a></div>
  </div>

  <div class="neon-card">
    <table class="table table-sm align-middle table-striped">
      <thead>
        <tr>
          <th>#</th>
          <th>Order ID</th>
          <th>Amount</th>
          <th>Reason</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$refunds): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">✅ No refunds found.</td></tr>
      <?php else: foreach($refunds as $r): ?>
        <tr>
          <td><?= $r->id ?></td>
          <td><?= $r->order_id ?></td>
          <td><span class="badge-ref px-2 py-1">+<?= number_format($r->amount,2) ?> ₳</span></td>
          <td><?= htmlspecialchars($r->reason) ?></td>
          <td><small class="text-muted"><?= $r->created_at ?></small></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="footer-note">
    ⚡ Refunds are automatically added to your balance if a site or link becomes invalid or deleted.
  </div>
</div>
</body>
</html>
