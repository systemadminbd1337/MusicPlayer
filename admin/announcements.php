<?php
include __DIR__ . "/_bootstrap.php";

$rows = $db->get_results("SELECT id, title, message, author, visible, created_at FROM k_announcements ORDER BY id DESC LIMIT 200", ARRAY_A);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin • Announcements</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
body{
  background:#030611;
  color:#e6eef8;
  font-family:'Share Tech Mono',monospace;
}
.container{max-width:1200px;padding:28px 20px;}
.card{
  background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(96,165,250,0.06);
  border-radius:12px;
  box-shadow:0 12px 36px rgba(0,0,0,0.6);
}
.input-dark{
  background:rgba(255,255,255,0.06);
  border:1px solid rgba(255,255,255,0.1);
  color:#fff;
}
.table td,.table th{
  color:#dfe7ff;
  border-color:rgba(255,255,255,0.06)!important;
}

/* 🔹 Admin Navbar (Hacker / Neon) */
.admin-nav{
  background:linear-gradient(90deg,#000000,#060c1a);
  border:1px solid rgba(0,230,255,0.08);
  box-shadow:0 10px 40px rgba(0,0,0,0.7),0 0 18px rgba(0,230,255,0.02) inset;
  padding:12px 16px;
  border-radius:12px;
  margin-bottom:22px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.admin-nav .links{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}
.admin-nav .links a{
  color:#e6eef8;
  text-decoration:none;
  padding:8px 12px;
  border-radius:10px;
  background:linear-gradient(90deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(96,165,250,0.06);
  transition:all .15s ease;
  font-size:14px;
}
.admin-nav .links a:hover{
  transform:translateY(-3px);
  color:#00e6ff;
  text-shadow:0 0 8px rgba(0,230,255,0.12);
  box-shadow:0 12px 34px rgba(0,230,255,0.06);
}
.admin-nav .links a.special{
  background:linear-gradient(90deg,rgba(0,230,255,0.06),rgba(0,255,157,0.04));
  border:1px solid rgba(0,255,157,0.12);
  color:#00e6ff;
  font-weight:700;
}
.admin-nav .links a.alert{
  background:linear-gradient(90deg,rgba(255,80,80,0.06),rgba(255,80,80,0.03));
  border:1px solid rgba(255,80,80,0.12);
  color:#ff8b8b;
  font-weight:700;
}
.admin-nav .logout{
  background:linear-gradient(90deg,#ef4444,#b91c1c);
  padding:8px 14px;
  border-radius:10px;
  color:#fff;
  text-decoration:none;
  font-weight:700;
  border:1px solid rgba(255,80,80,0.12);
  transition:all .12s;
}
.admin-nav .logout:hover{
  box-shadow:0 10px 30px rgba(239,68,68,.25);
  transform:translateY(-3px);
}
@media(max-width:880px){
  .admin-nav{padding:10px;}
  .admin-nav .links a{font-size:13px;padding:7px 10px;}
}

/* ⚡ Delete Button Style */
.btn-del{
  background:linear-gradient(90deg,#0f172a,#1e293b);
  border:1px solid rgba(255,60,60,0.3);
  color:#ff6b6b;
  font-weight:600;
  transition:all .15s ease;
}
.btn-del:hover{
  background:#000;
  color:#fff;
  box-shadow:0 0 15px rgba(255,60,60,0.2);
}

/* Announcement Header Bar */
.page-header{
  background:#000;
  color:#00e6ff;
  text-shadow:0 0 6px rgba(0,230,255,0.5);
  padding:10px 18px;
  border-radius:10px;
  border:1px solid rgba(0,230,255,0.15);
  margin-bottom:22px;
  box-shadow:0 0 30px rgba(0,230,255,0.06);
}
.page-header h3{margin:0;font-size:20px;}
</style>
</head>
<body>

<!-- 🔥 Admin Navigation -->
<div class="container my-3">
  <div class="admin-nav">
    <div class="links">
      <a href="index.php">🏠 Home</a>
      <a href="users.php">👥 Users</a>
      <a href="announcements.php" class="special">📢 Announcements</a>
      <a href="broken_links.php">🧩 Broken Links</a>
      <a href="login_logs.php">📜 Logs</a>
      <a href="user_login_logs.php">📜 User Login Logs</a>
      <a href="monitors.php">🔗 URL Monitor</a>
      <a href="deleted_sites.php" class="alert">🚫 Removed Sites</a>
      <a href="refunds.php">💸 Refunds</a>
      <a href="../index.php">🌐 Front</a>

      <!-- ✅ New Menus -->
      <a href="add_link.php">➕ Add Links</a>
      <a href="auto_jobs.php">⚙️ Auto Jobs</a>
      <a href="placements.php">📍 Placements</a>
      <a href="payments.php">💳 Payments</a>
    </div>
    <a href="logout.php" class="logout">🔓 Logout</a>
  </div>
</div>

<div class="container">
  <div class="page-header d-flex justify-content-between align-items-center">
    <h3>📢 Announcements</h3>
    <a class="btn btn-outline-info btn-sm" href="index.php">⬅ Back</a>
  </div>

  <!-- Add New Announcement -->
  <div class="card p-3 mb-4">
    <h5 class="mb-3 text-info">➕ New Announcement</h5>
    <form method="post" action="actions.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="ann_create">
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Title</label><input class="form-control input-dark" name="title" required></div>
        <div class="col-md-4"><label class="form-label">Author</label><input class="form-control input-dark" name="author" value="<?= htmlspecialchars($user->username ?? 'admin') ?>"></div>
        <div class="col-md-12"><label class="form-label">Message</label><textarea class="form-control input-dark" name="message" rows="3" required></textarea></div>
        <div class="col-md-12"><button class="btn btn-success">Publish</button></div>
      </div>
    </form>
  </div>

  <!-- Announcement Table -->
  <div class="card p-3">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Visible</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if ($rows) foreach ($rows as $a) { ?>
        <tr>
          <td><?= (int)$a['id'] ?></td>
          <td><?= htmlspecialchars($a['title']) ?></td>
          <td><?= htmlspecialchars($a['author']) ?></td>
          <td><?= $a['visible'] ? '✅' : '❌' ?></td>
          <td><?= htmlspecialchars($a['created_at']) ?></td>
          <td>
            <form method="post" action="actions.php" class="d-inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="ann_toggle">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-sm btn-warning"><?= $a['visible'] ? 'Hide' : 'Show' ?></button>
            </form>

            <button class="btn btn-sm btn-info"
              onclick="editAnn(<?= (int)$a['id'] ?>,'<?= htmlspecialchars(addslashes($a['title'])) ?>','<?= htmlspecialchars(addslashes($a['author'])) ?>','<?= htmlspecialchars(addslashes($a['message'])) ?>')">Edit</button>

            <form method="post" action="actions.php" class="d-inline" onsubmit="return confirm('⚠️ Delete this announcement permanently?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="ann_delete">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-sm btn-del">🗑 Delete</button>
            </form>
          </td>
        </tr>
        <?php } else { ?>
          <tr><td colspan="6" class="text-center text-muted">No announcements yet.</td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Edit Section -->
  <div class="card p-3 mt-4 d-none" id="editWrap">
    <h5 class="mb-3 text-info">✏️ Edit Announcement</h5>
    <form method="post" action="actions.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="ann_update">
      <input type="hidden" name="id" id="e_id">
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Title</label><input class="form-control input-dark" name="title" id="e_title" required></div>
        <div class="col-md-4"><label class="form-label">Author</label><input class="form-control input-dark" name="author" id="e_author"></div>
        <div class="col-md-12"><label class="form-label">Message</label><textarea class="form-control input-dark" name="message" rows="3" id="e_message" required></textarea></div>
        <div class="col-md-12"><button class="btn btn-primary">Save</button> <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button></div>
      </div>
    </form>
  </div>
</div>

<script>
function editAnn(id,title,author,msg){
  document.getElementById('editWrap').classList.remove('d-none');
  document.getElementById('e_id').value=id;
  document.getElementById('e_title').value=title.replaceAll("\\\\'","'");
  document.getElementById('e_author').value=author.replaceAll("\\\\'","'");
  document.getElementById('e_message').value=msg.replaceAll("\\\\'","'");
  window.scrollTo({top:0,behavior:'smooth'});
}
function cancelEdit(){
  document.getElementById('editWrap').classList.add('d-none');
}
</script>
</body>
</html>
