<?php
include __DIR__ . "/_bootstrap.php";

// Load users
$rows = $db->get_results("SELECT * FROM k_users WHERE deleted_at IS NULL ORDER BY id DESC");

// Load last 20 credit transactions (admin logs)
$credit_logs = $db->get_results("
  SELECT l.*, u.username AS target_name, a.username AS admin_name
  FROM k_credits_log l
  LEFT JOIN k_users u ON l.uid = u.id
  LEFT JOIN k_users a ON l.admin_id = a.id
  ORDER BY l.id DESC
  LIMIT 20
");

// === Mini Dashboard Stats ===
$total_users   = (int)$db->get_var("SELECT COUNT(*) FROM k_users WHERE deleted_at IS NULL");
$banned_users  = (int)$db->get_var("SELECT COUNT(*) FROM k_users WHERE is_banned=1 AND deleted_at IS NULL");
$active_users  = $total_users - $banned_users;
$total_credit  = (int)$db->get_var("SELECT COALESCE(SUM(kredi),0) FROM k_users WHERE deleted_at IS NULL");
$new_today     = (int)$db->get_var("SELECT COUNT(*) FROM k_users WHERE DATE(created_at)=CURDATE()");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Users Management - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#060717;color:#e6eef8;font-family:'Share Tech Mono',monospace;margin:0;padding:0;}
.container{max-width:1250px;}
.card{background:rgba(255,255,255,0.02);border:1px solid rgba(96,165,250,0.08);border-radius:14px;box-shadow:0 0 20px rgba(96,165,250,0.1);}
.table-dark{--bs-table-bg:transparent;--bs-table-color:#cbd5e1;}
.btn-neon{background:linear-gradient(90deg,#60a5fa,#a855f7);color:#fff;border:0;transition:.2s;}
.btn-neon:hover{opacity:.85;transform:scale(1.03);}
.btn-hack{background:linear-gradient(90deg,#22c55e,#16a34a);color:#fff;border:0;}
.btn-hack:hover{opacity:.85;}
.btn-ban{background:linear-gradient(90deg,#ef4444,#b91c1c);color:#fff;border:0;}
.btn-ban:hover{opacity:.85;}
.btn-remove{background:linear-gradient(90deg,#facc15,#eab308);color:#000;font-weight:600;border:0;}
.btn-remove:hover{opacity:.9;}
.input-credit{width:100px;text-align:center;background:#0b0d21;color:#fff;border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:4px;}
h2.title{color:#38bdf8;font-weight:700;margin-bottom:20px;}
.table th, .table td{vertical-align:middle!important;}
.section-title{color:#94a3b8;margin-top:40px;margin-bottom:12px;font-weight:600;}
hr.divider{border:0;border-top:1px solid rgba(255,255,255,0.08);margin:30px 0;}
.badge-exp{background:rgba(96,165,250,0.1);border:1px solid rgba(96,165,250,0.2);}
.badge-exp.red{background:rgba(255,40,40,0.1);border-color:rgba(255,80,80,0.4);color:#f87171;}

/* Nav */
.navx{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;padding:10px 6px;}
.navx a{color:#e6eef8;text-decoration:none;background:linear-gradient(90deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));border:1px solid rgba(96,165,250,0.06);border-radius:10px;padding:8px 12px;transition:all .18s ease;font-size:14px;}
.navx a:hover{transform:translateY(-3px);color:#00e6ff;box-shadow:0 0 20px rgba(0,230,255,0.08);}
.navx .logout{background:linear-gradient(90deg,#ef4444,#b91c1c);color:#fff!important;border:none;}
.navx a[aria-current="page"], .navx a.active{border-color:rgba(0,255,157,0.18);color:#00ff9d;box-shadow:0 0 15px rgba(0,255,157,0.08);}

/* KPI & Chart */
.kpi-box{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;}
.kpi-card{background:rgba(255,255,255,0.03);border:1px solid rgba(96,165,250,0.08);border-radius:10px;padding:14px;text-align:center;box-shadow:0 0 12px rgba(0,230,255,0.06);}
.kpi-title{font-size:13px;color:#9ca3af;margin-bottom:6px;}
.kpi-value{font-size:22px;font-weight:700;color:#00ff9d;text-shadow:0 0 8px rgba(0,255,157,0.2);}
.chart-wrap{background:rgba(255,255,255,0.03);border:1px solid rgba(96,165,250,0.08);border-radius:12px;box-shadow:0 0 18px rgba(0,230,255,0.05);padding:20px;margin-bottom:25px;text-align:center;}
.chart-wrap canvas{max-width:360px;margin:0 auto;display:block;filter:drop-shadow(0 0 10px rgba(0,230,255,0.08));}

/* responsive */
@media (max-width:900px){ .kpi-box{grid-template-columns:repeat(2,1fr);} }
@media (max-width:520px){ .kpi-box{grid-template-columns:1fr;} .navx{justify-content:center;} }
</style>
</head>
<body>

<!-- 🔥 Hacker Navigation Menu -->
<div class="container my-3">
  <div class="navx">
    <a href="index.php">🏠 Home</a>
    <a href="users.php" aria-current="page">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php">🧩 Broken Links</a>
    <a href="login_logs.php">📜 Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php" style="color:#ff7171;">🚫 Removed Sites</a>
    <a href="refunds.php">💸 Refunds</a>
    <a href="../index.php">🌐 Front</a>

    <!-- Systemadminbd Rule: no Actions menu -->
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>

    <a href="logout.php" class="logout">🔓 Logout</a>
  </div>
</div>

<!-- 🔹 Main -->
<div class="container my-4">
  <div class="card p-4">
    <h2 class="title">👥 User Management</h2>

    <!-- 🔸 Mini Dashboard -->
    <div class="kpi-box">
      <div class="kpi-card">
        <div class="kpi-title">👤 Total Users</div>
        <div class="kpi-value"><?=$total_users?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-title">🟢 Active Users</div>
        <div class="kpi-value"><?=$active_users?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-title">🚫 Banned Users</div>
        <div class="kpi-value"><?=$banned_users?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-title">💳 Total Credit</div>
        <div class="kpi-value"><?=$total_credit?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-title">🆕 New Today</div>
        <div class="kpi-value"><?=$new_today?></div>
      </div>
    </div>

    <!-- 🧭 Slim Donut Chart -->
    <div class="chart-wrap">
      <h5 style="color:#00e6ff;font-family:'Orbitron',sans-serif;text-transform:uppercase;font-size:15px;margin-bottom:8px;">
        👥 Active vs Banned Users
      </h5>
      <canvas id="userChart" height="220"></canvas>
    </div>

    <!-- Add new user -->
    <form class="row g-3 mb-4" method="post" action="actions.php">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
      <input type="hidden" name="action" value="user_add">
      <div class="col-md-3"><input class="form-control" name="username" placeholder="Username" required></div>
      <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
      <div class="col-md-3"><input class="form-control" type="password" name="password" placeholder="Password" required></div>
      <div class="col-md-2">
        <select class="form-select" name="role"><option value="user">User</option><option value="admin">Admin</option></select>
      </div>
      <div class="col-md-1"><button class="btn btn-neon w-100">Add</button></div>
    </form>

    <!-- Users Table -->
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle">
        <thead><tr>
          <th>ID</th><th>User</th><th>Email</th><th>Role</th><th>Credit</th><th>Expiry</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if ($rows) {
          foreach ($rows as $r) {
            $exp = $r->expiry_date ?? null;
            $expText = '—';
            $isExpired = false;
            if ($exp) {
              $dt = strtotime($exp);
              if ($dt < time()) { $isExpired = true; $expText = "Expired (" . date('d M Y', $dt) . ")"; }
              else { $expText = date('d M Y', $dt); }
            }
        ?>
          <tr>
            <td><?=$r->id?></td>
            <td><?=htmlspecialchars($r->username)?></td>
            <td><?=htmlspecialchars($r->email)?></td>
            <td><span class="badge bg-info"><?=htmlspecialchars($r->role)?></span></td>
            <td><strong style="color:#facc15"><?= (int)$r->kredi ?></strong></td>
            <td>
              <span class="badge badge-exp <?=$isExpired?'red':''?>">
                <?=$expText?>
              </span>
              <form method="post" action="actions.php" class="d-inline">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                <input type="hidden" name="action" value="renew_access">
                <input type="hidden" name="uid" value="<?=$r->id?>">
                <button class="btn btn-sm btn-hack ms-1">+30d</button>
              </form>
            </td>
            <td>
              <?php if ($r->is_banned) { ?>
                <span class="badge bg-danger">Banned</span>
              <?php } else { ?>
                <span class="badge bg-success">Active</span>
              <?php } ?>
            </td>
            <td>
              <!-- Credit Add -->
              <form method="post" action="actions.php" class="d-inline-flex align-items-center me-1">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                <input type="hidden" name="action" value="credit_add">
                <input type="hidden" name="uid" value="<?=$r->id?>">
                <input type="number" name="amount" class="input-credit me-1" placeholder="+10" required>
                <input type="text" name="reason" placeholder="Reason" class="input-credit me-1" style="width:120px">
                <button class="btn btn-hack btn-sm">+</button>
              </form>

              <!-- Ban / Unban -->
              <form method="post" action="actions.php" class="d-inline">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                <input type="hidden" name="uid" value="<?=$r->id?>">
                <?php if ($r->is_banned) { ?>
                  <input type="hidden" name="action" value="user_unban">
                  <button class="btn btn-neon btn-sm">Unban</button>
                <?php } else { ?>
                  <input type="hidden" name="action" value="user_ban">
                  <button class="btn btn-ban btn-sm">Ban</button>
                <?php } ?>
              </form>

              <!-- Delete -->
              <form method="post" action="actions.php" class="d-inline">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                <input type="hidden" name="action" value="user_delete">
                <input type="hidden" name="uid" value="<?=$r->id?>">
                <button class="btn btn-remove btn-sm">✖</button>
              </form>
            </td>
          </tr>
        <?php } } else { ?>
          <tr><td colspan="8" class="text-center text-muted">No users found.</td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>

    <hr class="divider">

    <!-- Credit Logs -->
    <h4 class="section-title">💰 Credit History (last 20 changes)</h4>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle small">
        <thead><tr><th>ID</th><th>User</th><th>Change</th><th>Reason</th><th>Admin</th><th>Date</th></tr></thead>
        <tbody>
        <?php if ($credit_logs) {
          foreach ($credit_logs as $log) { ?>
            <tr>
              <td><?=$log->id?></td>
              <td><?=htmlspecialchars($log->target_name ?? 'Unknown')?></td>
              <td><?=($log->delta>=0?'<span class="text-success fw-bold">+'.$log->delta.'</span>':'<span class="text-danger fw-bold">'.$log->delta.'</span>')?></td>
              <td><?=htmlspecialchars($log->reason ?? '-')?></td>
              <td><?=htmlspecialchars($log->admin_name ?? 'system')?></td>
              <td><?=htmlspecialchars($log->created_at)?></td>
            </tr>
        <?php } } else { ?>
          <tr><td colspan="6" class="text-center text-muted">No credit changes yet.</td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('userChart');
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Active Users', 'Banned Users'],
    datasets: [{
      data: [<?=$active_users?>, <?=$banned_users?>],
      backgroundColor: ['#00ff9d', '#ff6b6b'],
      borderColor: '#0b1620',
      borderWidth: 2,
      hoverBorderColor: '#00e6ff',
      hoverBorderWidth: 2,
      cutout: '70%'
    }]
  },
  options: {
    aspectRatio: 2.2,
    layout: { padding: 5 },
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          color: '#b7c3d0',
          font: { family: 'Share Tech Mono', size: 13 },
          boxWidth: 16
        }
      },
      tooltip: {
        backgroundColor: 'rgba(3, 6, 17, 0.9)',
        titleColor: '#00e6ff',
        bodyColor: '#e6eef8',
        borderWidth: 1,
        borderColor: 'rgba(0,230,255,0.2)'
      }
    }
  }
});
</script>
</body>
</html>
