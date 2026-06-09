<?php
include __DIR__ . "/_bootstrap.php";

// Load broken and defunct links
$broken_links  = $db->get_results("SELECT * FROM k_broken_links WHERE status='broken' ORDER BY checked_at DESC LIMIT 200");
$defunct_links = $db->get_results("SELECT * FROM k_broken_links WHERE status='defunct' ORDER BY checked_at DESC LIMIT 200");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin • Broken Links</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#030611;color:#e6eef8;font-family:'Share Tech Mono',monospace;}
.container{max-width:1200px;padding:28px 20px;}
.card{
  background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(96,165,250,0.06);
  border-radius:12px;
  box-shadow:0 12px 36px rgba(0,0,0,0.6);
}
.table td,.table th{color:#dfe7ff;border-color:rgba(255,255,255,0.06)!important;}
.btn-neon{
  background:linear-gradient(90deg,#60a5fa,#a855f7);
  color:#fff;border:0;transition:.2s;
}
.btn-neon:hover{opacity:.85;transform:scale(1.03);}

/* 🔹 Hacker Navbar */
.navx{
  display:flex;flex-wrap:wrap;gap:10px;
  background:linear-gradient(90deg,#000,#050c1a);
  border:1px solid rgba(0,230,255,0.08);
  border-radius:12px;
  padding:12px 16px;
  box-shadow:0 8px 30px rgba(0,0,0,0.6);
  justify-content:center;
  margin-bottom:25px;
}
.navx a{
  text-decoration:none;
  color:#e6eef8;
  background:rgba(96,165,250,0.08);
  padding:8px 14px;
  border-radius:8px;
  font-weight:500;
  transition:.2s;
  border:1px solid rgba(255,255,255,0.05);
}
.navx a:hover{
  background:linear-gradient(90deg,#00e6ff,#00ff9d);
  color:#000;
  text-shadow:0 0 8px rgba(96,165,250,0.6);
}
.navx a.active{color:#00ff9d;border-color:rgba(0,255,157,0.2);box-shadow:0 0 12px rgba(0,255,157,0.1);}
.logout-btn{
  background:linear-gradient(90deg,#ef4444,#b91c1c);
  padding:6px 14px;
  border-radius:8px;
  color:#fff;
  text-decoration:none;
  font-weight:600;
  transition:.2s;
}
.logout-btn:hover{box-shadow:0 0 15px rgba(239,68,68,.5);color:#fff;}

/* 🔸 Delete Button */
.btn-del{
  background:#0f172a;
  border:1px solid rgba(255,80,80,0.3);
  color:#ff7171;
  font-weight:600;
  transition:all .2s ease;
}
.btn-del:hover{
  background:#000;
  color:#fff;
  box-shadow:0 0 10px rgba(255,80,80,0.3);
}

/* Header bar */
.page-header{
  background:#000;
  color:#00e6ff;
  text-shadow:0 0 6px rgba(0,230,255,0.5);
  padding:10px 18px;
  border-radius:10px;
  border:1px solid rgba(0,230,255,0.15);
  margin-bottom:22px;
  box-shadow:0 0 25px rgba(0,230,255,0.06);
}
.page-header h3{margin:0;font-size:20px;}
</style>
</head>
<body>

<!-- 🔥 Hacker Navigation -->
<div class="container my-3">
  <div class="navx">
    <a href="index.php">🏠 Home</a>
    <a href="users.php">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php" class="active">🧩 Broken Links</a>
    <a href="login_logs.php">🧾 Admin Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php" style="color:#ff7171;">🚫 Removed Sites</a>
    <a href="refunds.php">💸 Refunds</a>
    <a href="../index.php">🌐 Front</a>
    <!-- ✅ New Menus -->
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>
    <a href="logout.php" class="logout-btn">🔓 Logout</a>
  </div>
</div>

<div class="container">
  <div class="page-header d-flex justify-content-between align-items-center">
    <h3>🧩 Broken & Defunct Links</h3>
    <a href="index.php" class="btn btn-outline-info btn-sm">⬅ Back</a>
  </div>

  <!-- Add new broken link -->
  <div class="card p-3 mb-4">
    <h5 class="mb-3 text-info">➕ Report Broken Link</h5>
    <form method="post" action="actions.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="broken_add">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Domain</label>
          <input class="form-control" name="domain" placeholder="example.com" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">URL</label>
          <input class="form-control" name="url" placeholder="https://example.com/page" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Reason</label>
          <input class="form-control" name="reason" placeholder="404 / Timeout">
        </div>
        <div class="col-md-12">
          <button class="btn btn-neon">Add Broken Link</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Broken Links Table -->
  <div class="card p-3 mb-4">
    <h5 class="mb-3 text-warning">🚫 Broken Links</h5>
    <div class="table-responsive">
      <table class="table table-sm table-dark align-middle">
        <thead>
          <tr><th>#</th><th>Domain</th><th>URL</th><th>Reason</th><th>Checked</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php if ($broken_links) {
          foreach ($broken_links as $b) { ?>
            <tr>
              <td><?= $b->id ?></td>
              <td><?= htmlspecialchars($b->domain) ?></td>
              <td><a href="<?= htmlspecialchars($b->url) ?>" target="_blank" class="text-warning text-decoration-none"><?= htmlspecialchars($b->url) ?></a></td>
              <td><?= htmlspecialchars($b->reason ?? '-') ?></td>
              <td><?= htmlspecialchars($b->checked_at) ?></td>
              <td>
                <form method="post" action="actions.php" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="broken_move_defunct">
                  <input type="hidden" name="id" value="<?= $b->id ?>">
                  <button class="btn btn-sm btn-outline-warning">→ Defunct</button>
                </form>
                <form method="post" action="actions.php" class="d-inline" onsubmit="return confirm('⚠️ Delete this link?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="broken_delete">
                  <input type="hidden" name="id" value="<?= $b->id ?>">
                  <button class="btn btn-sm btn-del">🗑 Delete</button>
                </form>
              </td>
            </tr>
        <?php } } else { ?>
          <tr><td colspan="6" class="text-center text-muted">No broken links found.</td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Defunct Links Table -->
  <div class="card p-3">
    <h5 class="mb-3 text-danger">💀 Defunct Sites</h5>
    <div class="table-responsive">
      <table class="table table-sm table-dark align-middle">
        <thead>
          <tr><th>#</th><th>Domain</th><th>URL</th><th>Reason</th><th>Checked</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php if ($defunct_links) {
          foreach ($defunct_links as $b) { ?>
            <tr>
              <td><?= $b->id ?></td>
              <td><?= htmlspecialchars($b->domain) ?></td>
              <td><a href="<?= htmlspecialchars($b->url) ?>" target="_blank" class="text-danger text-decoration-none"><?= htmlspecialchars($b->url) ?></a></td>
              <td><?= htmlspecialchars($b->reason ?? '-') ?></td>
              <td><?= htmlspecialchars($b->checked_at) ?></td>
              <td>
                <form method="post" action="actions.php" class="d-inline" onsubmit="return confirm('⚠️ Delete this link?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="broken_delete">
                  <input type="hidden" name="id" value="<?= $b->id ?>">
                  <button class="btn btn-sm btn-del">🗑 Delete</button>
                </form>
              </td>
            </tr>
        <?php } } else { ?>
          <tr><td colspan="6" class="text-center text-muted">No defunct links found.</td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
