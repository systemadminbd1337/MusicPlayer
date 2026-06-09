<?php
// admin/auto_jobs.php — HackLink Admin: Auto Backlink Jobs Monitor

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '_bootstrap.php';
require_once '../db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user']) || !isset($_SESSION['user']->id)) {
  redirect("login.php");
  exit();
}
function esc($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$uid = (int)$_SESSION['user']->id;

// ===== AJAX actions =====
if (!empty($_GET['ajax']) && $_SERVER['REQUEST_METHOD']==='POST') {
  header('Content-Type: application/json; charset=utf-8');
  $oid = (int)($_POST['oid'] ?? 0);
  if ($oid<=0){ echo json_encode(['ok'=>0,'msg'=>'Invalid order ID']); exit; }

  if ($_GET['ajax']==='retry') {
    $db->query("UPDATE k_orders SET status='pending', attempts=0 WHERE id='$oid'");
    echo json_encode(['ok'=>1,'msg'=>'Retry queued']); exit;
  }
  if ($_GET['ajax']==='force') {
    $db->query("UPDATE k_orders SET status='processing' WHERE id='$oid'");
    include "../inc/auto_functions.php";
    process_order($oid);
    echo json_encode(['ok'=>1,'msg'=>'Force processed']); exit;
  }
  echo json_encode(['ok'=>0,'msg'=>'Unknown action']); exit;
}

// ===== Stats =====
$pending    = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE status='pending'");
$processing = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE status='processing'");
$processed  = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE status='processed'");
$failed     = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE status='failed'");
$total      = $pending + $processing + $processed + $failed;
$success_rate = $total>0 ? round(($processed/$total)*100,1) : 0;

// ===== Table data =====
$rows = $db->get_results("
SELECT o.id,o.uid,o.lid,o.status,o.attempts,o.notes,o.processed_at,
u.username AS user_name,l.domain
FROM k_orders o
LEFT JOIN k_users u ON u.id=o.uid
LEFT JOIN k_linkdb l ON l.id=o.lid
WHERE o.status!='processed'
ORDER BY o.id DESC LIMIT 100
");
if(empty($rows)) $rows = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>⚙️ Auto Backlink Jobs • HackLink</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
:root{
 --bg:#0b0f1e;--neon:#00e6ff;--neon2:#00ff9d;--accent:#9f7aea;
}
body{background:linear-gradient(135deg,#0b0f1e 0%,#111827 100%);
color:#e2e8f0;font-family:'Share Tech Mono',monospace;}
.container-fluid{max-width:1400px;padding:1.2rem;}
.navx a{padding:8px 12px;border-radius:8px;text-decoration:none;
color:#e6eef8;background:rgba(255,255,255,0.03);
border:1px solid rgba(96,165,250,0.08);transition:all .18s;}
.navx a:hover{transform:translateY(-3px);box-shadow:0 0 12px rgba(0,255,157,0.25);
color:var(--neon2);}
.navx a.active{color:var(--neon2)!important;box-shadow:0 0 14px rgba(0,255,157,0.3);}
.header-title{font-size:1.6rem;font-weight:700;color:var(--neon);margin:20px 0;}
.card{background:#161329;border:1px solid rgba(159,122,234,.3);border-radius:12px;
box-shadow:0 4px 12px rgba(0,0,0,.3);}
.card h5{color:var(--accent);}
.stats-box{font-size:1.4rem;font-weight:700;}
.table-container{background:#161329;border:1px solid rgba(159,122,234,.3);
border-radius:12px;padding:1rem;margin-top:1rem;}
table.dataTable{color:#fff;border-collapse:separate;border-spacing:0;}
table.dataTable th{background:rgba(159,122,234,0.1);border-bottom:1px solid rgba(159,122,234,0.3);
font-weight:600;text-transform:uppercase;font-size:.8rem;color:#b7a4ff;}
.dataTables_wrapper .dataTables_paginate .paginate_button{
background:linear-gradient(90deg,#0f172a,#1e293b);
border:1px solid rgba(0,255,157,0.25);border-radius:6px;color:#00ff9d!important;
box-shadow:0 0 8px rgba(0,255,157,0.15);}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{
background:linear-gradient(90deg,#00e6ff,#00ff9d)!important;color:#000!important;
box-shadow:0 0 12px rgba(0,255,157,0.4);}
.btn-retry{background:linear-gradient(90deg,#f59e0b,#d97706);
border:none;border-radius:6px;padding:.4rem .8rem;font-weight:600;}
.btn-force{background:linear-gradient(90deg,#10b981,#059669);
border:none;border-radius:6px;padding:.4rem .8rem;font-weight:600;}
.badge.bg-warning{background:#f59e0b;}
.badge.bg-info{background:#06b6d4;}
.badge.bg-danger{background:#ef4444;}
@media(max-width:768px){
.navx{flex-wrap:wrap;gap:.4rem;}
}
</style>
</head>
<body>
<div class="container-fluid">

  <!-- 🌐 HACKLINK ADMIN MENU -->
  <div class="navx mb-4 d-flex flex-wrap gap-2">
    <a href="index.php">🏠 Home</a>
    <a href="users.php">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php">⚠️ Broken Links</a>
    <a href="login_logs.php">🧾 Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php">🚫 Removed Sites</a>
    <a href="../index.php">🌐 Front</a>
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php" class="active">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>
    <a href="actions.php">🧩 Actions</a>
    <a href="logout.php" style="color:#f87171;">🚪 Logout</a>
  </div>

  <h3 class="header-title"><i class="fas fa-cogs"></i> Auto Backlink Jobs</h3>

  <!-- KPI -->
  <div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6"><div class="card p-3 text-center"><h5>Pending</h5><div class="stats-box text-warning"><?= $pending ?></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card p-3 text-center"><h5>Processing</h5><div class="stats-box text-info"><?= $processing ?></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card p-3 text-center"><h5>Processed</h5><div class="stats-box text-success"><?= $processed ?></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card p-3 text-center"><h5>Failed</h5><div class="stats-box text-danger"><?= $failed ?></div></div></div>
    <div class="col-md-12"><div class="card p-3 text-center"><h5>Success Rate</h5><div class="stats-box text-primary"><?= $success_rate ?>%</div></div></div>
  </div>

  <!-- TABLE -->
  <div class="table-container">
    <div class="table-responsive">
      <table id="jobsTable" class="table table-dark table-striped align-middle w-100">
        <thead><tr>
          <th>ID</th><th>User</th><th>Domain</th><th>Status</th>
          <th>Attempts</th><th>Processed At</th><th>Notes</th><th>Action</th>
        </tr></thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?= esc($r->id) ?></td>
            <td><?= esc($r->user_name ?? 'Unknown') ?></td>
            <td><?= esc($r->domain ?? 'Unknown') ?></td>
            <td><span class="badge <?= $r->status=='failed'?'bg-danger':($r->status=='pending'?'bg-warning':'bg-info') ?>">
              <?= esc(strtoupper($r->status)) ?></span></td>
            <td><?= esc($r->attempts) ?></td>
            <td><?= esc($r->processed_at ?? '-') ?></td>
            <td><?= nl2br(esc(substr($r->notes ?? '',0,100))) ?>...</td>
            <td>
              <button class="btn btn-sm btn-retry retry-btn" data-oid="<?= esc($r->id) ?>">Retry</button>
              <button class="btn btn-sm btn-force force-btn" data-oid="<?= esc($r->id) ?>">Force</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include "../footer.php"; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  $('#jobsTable').DataTable({
    pageLength:10,
    responsive:true,
    columnDefs:[{targets:-1,orderable:false}]
  });
  $(document).on('click','.retry-btn',function(){
    const oid=$(this).data('oid');
    $.post('auto_jobs.php?ajax=retry',{oid:oid},res=>{
      alert(res.ok?'Retry queued ✅':res.msg||'Failed'); location.reload();
    },'json');
  });
  $(document).on('click','.force-btn',function(){
    const oid=$(this).data('oid');
    $.post('auto_jobs.php?ajax=force',{oid:oid},res=>{
      alert(res.ok?'Force processed ✅':res.msg||'Failed'); location.reload();
    },'json');
  });
});
</script>
</body>
</html>
