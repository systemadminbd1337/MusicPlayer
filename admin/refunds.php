<?php
include __DIR__ . "/_bootstrap.php";

// Refund history list
$refunds = $db->get_results("
  SELECT r.*, u.username, o.link
  FROM k_refunds r
  LEFT JOIN k_users u ON u.id = r.user_id
  LEFT JOIN k_orders o ON o.id = r.order_id
  ORDER BY r.id DESC
");

// Fetch users for dropdown
$users = $db->get_results("SELECT id, username FROM k_users WHERE deleted_at IS NULL ORDER BY username ASC");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>💸 Refunds — HackLink Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#030611;--neon1:#00ff9d;--neon2:#00e6ff;--muted:#8b9aa3;
}
body{
  background:radial-gradient(1200px 400px at 10% 10%, rgba(0,230,255,0.03), transparent 5%),
             radial-gradient(800px 300px at 90% 90%, rgba(0,255,157,0.02), transparent 5%),
             var(--bg);
  color:#e6eef8;font-family:'Share Tech Mono',monospace;
}
.container{max-width:1150px;padding:32px 20px;}
.h-title{font-size:22px;font-weight:700;color:var(--neon1);text-shadow:0 0 8px var(--neon1);}
.back-link a{color:#00e6ff;text-decoration:none;}
.back-link a:hover{text-shadow:0 0 10px var(--neon2);}
.neon-card{
  background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(255,255,255,0.05);
  border-radius:12px;padding:20px;
  box-shadow:0 0 25px rgba(0,230,255,.06), inset 0 0 10px rgba(0,255,157,.05);
}
.table thead th{color:#00e6ff;border-bottom:1px solid rgba(0,255,157,0.25);}
.table tbody td{color:#e6eef8;font-size:14px;}
.badge-ref{background:linear-gradient(90deg,rgba(0,255,157,0.15),rgba(0,230,255,0.12));border:1px solid rgba(0,255,157,0.25);color:#00ff9d;}
.glow-line{height:1px;background:linear-gradient(90deg,transparent,rgba(0,255,157,0.4),transparent);margin:20px 0;}
.text-muted{color:var(--muted)!important;}
.form-control, .form-select{
  background:rgba(0,0,0,0.4);
  border:1px solid rgba(255,255,255,0.08);
  color:#e6eef8;
}
.form-control:focus, .form-select:focus{
  border-color:var(--neon2);
  box-shadow:0 0 8px rgba(0,230,255,0.2);
}
.btn-neon{
  background:linear-gradient(90deg,var(--neon1),var(--neon2));
  border:0;color:#001214;font-weight:700;
  box-shadow:0 0 15px rgba(0,255,157,0.15);
  transition:all .2s;
}
.btn-neon:hover{transform:translateY(-2px);box-shadow:0 0 25px rgba(0,255,157,0.25);}

/* 🔹 Hacker Navbar */
.navx{
  display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:25px;
  background:linear-gradient(90deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02));
  border:1px solid rgba(255,255,255,0.05);
  border-radius:12px;
  padding:10px 15px;
  box-shadow:0 8px 30px rgba(0,0,0,0.6);
}
.navx a{
  text-decoration:none;
  color:#e6eef8;
  background:rgba(96,165,250,0.1);
  padding:8px 14px;
  border-radius:8px;
  font-weight:500;
  transition:.2s;
}
.navx a:hover{
  background:linear-gradient(90deg,#60a5fa,#a855f7);
  color:#fff;
  text-shadow:0 0 8px rgba(96,165,250,0.5);
}
.logout-btn{
  background:linear-gradient(90deg,#ef4444,#b91c1c);
  padding:6px 14px;
  border-radius:8px;
  color:#fff;
  text-decoration:none;
  font-weight:600;
  transition:.2s;
}
.logout-btn:hover{box-shadow:0 0 15px rgba(239,68,68,.5);}
</style>
</head>
<body>

<!-- 🔥 Hacker Navigation -->
<div class="container my-3">
  <div class="navx">
    <a href="index.php">🏠 Home</a>
    <a href="users.php">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php">🧩 Broken Links</a>
    <a href="login_logs.php">🧠 Admin Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php">🚫 Removed Sites</a>
    <a href="refunds.php" style="color:#00ff9d;font-weight:bold;">💸 Refunds</a>
    <!-- ✅ New Menus -->
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <div class="h-title">💸 Refund Management</div>
    <div class="back-link mt-2"><a href="index.php">⬅ Back to Dashboard</a></div>
  </div>

  <!-- Manual Refund Form -->
  <div class="neon-card mb-4">
    <h5 class="mb-3">➕ Manual Refund</h5>
    <form method="post" action="actions.php" class="row g-3">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
      <input type="hidden" name="action" value="refund_user">

      <div class="col-md-3">
        <label class="form-label">Select User</label>
        <select name="uid" class="form-select" required>
          <option value="">-- choose user --</option>
          <?php foreach($users as $u): ?>
            <option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Amount (₳)</label>
        <input type="number" name="amount" class="form-control" min="1" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Reason</label>
        <input type="text" name="reason" class="form-control" placeholder="Manual refund or adjustment" required>
      </div>

      <div class="col-md-2 d-grid">
        <button class="btn btn-neon mt-4">Refund Now</button>
      </div>
    </form>
  </div>

  <!-- Refund History -->
  <div class="neon-card">
    <h5 class="mb-3">📜 Refund History</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle table-dark table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Order ID</th>
            <th>Amount</th>
            <th>Reason</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$refunds): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">✅ No refunds yet — all systems operational.</td></tr>
        <?php else: foreach($refunds as $r): ?>
          <tr>
            <td><?=$r->id?></td>
            <td><?=htmlspecialchars($r->username ?: 'Unknown')?></td>
            <td><?=$r->order_id?></td>
            <td><span class="badge-ref px-2 py-1">+<?=number_format($r->amount,2)?> ₳</span></td>
            <td><?=htmlspecialchars($r->reason)?></td>
            <td><small class="text-muted"><?=$r->created_at?></small></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="glow-line"></div>
  <p class="text-center text-muted small">
    ⚡ Auto refunds trigger when monitored sites are deleted or missing codes.<br>
    Manual refunds can be issued anytime for specific users from above.
  </p>
</div>
</body>
</html>
