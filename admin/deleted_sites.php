<?php
// admin/deleted_sites.php
session_start();
require_once '../config.php';
require_once '../inc/check_url.php';

function clean($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$query = "
  SELECT * FROM monitored_urls 
  WHERE last_status IN ('NOT_FOUND','GONE','ERROR','NOT_FOUND_CUSTOM','SERVER_ERROR') 
  ORDER BY last_checked DESC
";
$rows = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>🚫 Removed / Deleted Sites — HackLink Monitor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#040608;--neon1:#00ff9d;--neon2:#00e6ff;
}
body{
  background:
    radial-gradient(1200px 400px at 10% 10%,rgba(0,230,255,.03),transparent 5%),
    radial-gradient(800px 300px at 90% 90%,rgba(0,255,157,.02),transparent 5%),
    var(--bg);
  color:#c7f9e8;font-family:'Share Tech Mono',monospace;
}
.hack-title{
  font-family:'Orbitron',sans-serif;
  text-transform:uppercase;
  letter-spacing:2px;
  color:var(--neon2);
  text-shadow:0 0 10px var(--neon2);
}
.neon-card{
  background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(255,255,255,0.05);
  border-radius:14px;
  box-shadow:0 0 25px rgba(0,255,157,0.08),inset 0 0 10px rgba(0,230,255,0.06);
}
.table thead th{color:#00e6ff;}
.table tbody tr:hover{background:rgba(0,255,157,0.05);}
.badge-del{
  background:linear-gradient(90deg,rgba(255,0,0,0.25),rgba(255,60,60,0.3));
  color:#ffbdbd;border:1px solid rgba(255,0,0,0.3);
}
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
.console-bar{
  position:fixed;bottom:0;left:0;right:0;height:36px;
  background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,0.45));
  color:rgba(140,255,210,0.7);
  display:flex;align-items:center;
  padding:0 16px;
  font-size:12px;
  font-family:'Share Tech Mono',monospace;
  pointer-events:none;
}
.btn-outline-neon{
  color:var(--neon2);
  border:1px solid rgba(0,230,255,0.3);
}
.btn-outline-neon:hover{
  background:linear-gradient(90deg,rgba(0,255,157,0.1),rgba(0,230,255,0.15));
  color:var(--neon1);
}
hr.divider{border-top:1px solid rgba(0,255,157,0.18);}
</style>
</head>
<body class="p-4">

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
    <a href="deleted_sites.php" style="background:linear-gradient(90deg,#ff7171,#b91c1c);color:#fff;">🚫 Removed Sites</a>
    <a href="refunds.php">💸 Refunds</a>
    <a href="../index.php">🌐 Front</a>

    <!-- ✅ New Menus -->
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>

    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="hack-title">🚫 Deleted / Removed Sites</h2>
    <a href="monitors.php" class="btn btn-sm btn-outline-neon">⬅ Back</a>
  </div>

  <div class="neon-card p-3">
    <?php if(!$rows): ?>
      <div class="text-center py-5 text-muted">✅ No deleted or removed sites detected.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-dark align-middle mb-0">
          <thead><tr>
              <th>#</th><th>URL</th><th>Status</th><th>HTTP</th><th>Last Checked</th>
              <th>ETag</th><th>Last Modified</th><th>Snippet</th>
          </tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= clean($r['id']) ?></td>
              <td style="max-width:360px;word-wrap:break-word;">
                <a href="<?= clean($r['url']) ?>" target="_blank" style="color:#00e6ff;"><?= clean($r['url']) ?></a><br>
                <small class="text-muted"><?= clean($r['label'] ?: '') ?></small>
              </td>
              <td><span class="badge badge-del"><?= clean($r['last_status']) ?></span></td>
              <td><?= clean($r['last_http_code'] ?: '-') ?></td>
              <td><small><?= clean($r['last_checked'] ?: '-') ?></small></td>
              <td><small><?= clean($r['last_etag'] ?: '-') ?></small></td>
              <td><small><?= clean($r['last_modified'] ?: '-') ?></small></td>
              <td style="max-width:320px;"><small><?= clean(substr($r['last_hash'] ?: '-',0,20)) ?></small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <hr class="divider my-4"/>
  <p class="text-center small text-muted">
    These URLs were detected as <span style="color:#ff6464;">removed / gone / error</span> during latest checks.<br>
    Automatically updated by <code>monitor.php</code> or manual <b>Check Now</b> actions.
  </p>
</div>

<div class="console-bar"><span id="consoleTxt">scanning for deleted endpoints...</span></div>
<script>
(function(){
  const msgs=[
    'init: scanning for deleted endpoints...',
    'sync: checking remote HTTP status codes...',
    'alert: 404s logged to deleted_sites table',
    'trace: monitoring status change → GONE',
    'done: awaiting next cron cycle...'
  ];
  let i=0;
  const el=document.getElementById('consoleTxt');
  setInterval(()=>{
    el.style.opacity=0;
    setTimeout(()=>{el.textContent=msgs[i%msgs.length];el.style.opacity=1;i++;},180);
  },3500);
})();
</script>
</body>
</html>
