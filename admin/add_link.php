<?php
/**
 * admin/add_link.php
 * - Add Link (AJAX)
 * - Latest list with pagination (25/page)
 * - Search (domain) & Filter (type)
 * - Single & Bulk delete (AJAX)
 * - Updated unified menu (includes: Add Links, Auto Jobs, Placements, Payments)
 * - CSRF protection for state-changing actions
 */

require_once __DIR__ . "/_bootstrap.php";
if (!isset($db)) { die("Database not connected. Ensure admin/_bootstrap.php loads correctly."); }

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_admin_links'])) {
  $_SESSION['csrf_admin_links'] = bin2hex(random_bytes(16));
}
function csrf_ok($t){ return isset($_SESSION['csrf_admin_links']) && hash_equals($_SESSION['csrf_admin_links'], $t ?? ''); }

// ---------- helpers ----------
function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dbx($v){ global $db; return (isset($db) && method_exists($db,'escape')) ? $db->escape($v) : addslashes($v); }
function has_col($table,$col){
  global $db;
  try{
    $c=(int)$db->get_var("SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema=DATABASE() AND table_name='".dbx($table)."' AND column_name='".dbx($col)."'");
    return $c>0;
  }catch(Throwable $e){ return false; }
}
function json_exit($arr){
  if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr); exit;
}

// ---------- optional columns ----------
$col_domain_year = has_col('k_linkdb','domain_year');
$col_sure        = has_col('k_linkdb','sure');
$col_ups         = has_col('k_linkdb','ups');
$col_alexa1      = has_col('k_linkdb','alexa1');
$col_alexa2      = has_col('k_linkdb','alexa2');
$col_country     = has_col('k_linkdb','country');
$col_created_at  = has_col('k_linkdb','created_at');
$col_durum       = has_col('k_linkdb','durum'); // visible flag

// ---------- AJAX: add ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['ajax'] ?? '') === 'add_link')) {
  try{
    if (!csrf_ok($_POST['csrf'] ?? '')) json_exit(['ok'=>0,'msg'=>'Invalid CSRF token. Reload page.']);

    $domain = trim($_POST['domain'] ?? '');
    $type   = (int)($_POST['type'] ?? 1);
    $ups    = (int)($_POST['ups'] ?? 1);
    $pa     = (int)($_POST['alexa1'] ?? 0);
    $da     = (int)($_POST['alexa2'] ?? 0);
    $year   = trim($_POST['domain_year'] ?? '');
    $sure   = trim($_POST['sure'] ?? '');
    $country= trim($_POST['country'] ?? '');

    if ($domain === '') json_exit(['ok'=>0,'msg'=>'Domain required.']);

    // normalize
    $d = preg_replace('#^https?://#i','',$domain);
    $d = rtrim($d,'/');
    $d = strtolower($d);

    // duplicate (case-insensitive)
    $dup = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE LOWER(domain)='".dbx($d)."'");
    if ($dup > 0) json_exit(['ok'=>0,'msg'=>'Domain already exists.']);

    // dynamic insert
    $cols = ["domain","tip"];
    $vals = ["'".dbx($d)."'","'".$type."'"];
    if ($col_ups)         { $cols[]="ups";          $vals[]="'".dbx($ups)."'"; }
    if ($col_alexa1)      { $cols[]="alexa1";       $vals[]="'".dbx($pa)."'"; }
    if ($col_alexa2)      { $cols[]="alexa2";       $vals[]="'".dbx($da)."'"; }
    if ($col_domain_year) { $cols[]="domain_year";  $vals[]="'".dbx($year)."'"; }
    if ($col_sure)        { $cols[]="sure";         $vals[]="'".dbx($sure)."'"; }
    if ($col_country)     { $cols[]="country";      $vals[]="'".dbx($country)."'"; }
    if ($col_durum)       { $cols[]="durum";        $vals[]="'1'"; }            // visible by default
    if ($col_created_at)  { $cols[]="created_at";   $vals[]="NOW()"; }

    $sql = "INSERT INTO k_linkdb(".implode(',',$cols).") VALUES(".implode(',',$vals).")";
    $db->query($sql);
    $id = (int)$db->insert_id;

    // fetch back row
    $cols_fetch = "id, domain, tip";
    if ($col_domain_year) $cols_fetch .= ", domain_year";
    if ($col_sure)        $cols_fetch .= ", sure";
    if ($col_country)     $cols_fetch .= ", country";
    if ($col_alexa1)      $cols_fetch .= ", alexa1";
    if ($col_alexa2)      $cols_fetch .= ", alexa2";
    if ($col_created_at)  $cols_fetch .= ", created_at";
    $row = $db->get_row("SELECT {$cols_fetch} FROM k_linkdb WHERE id=".$id);

    json_exit([
      'ok'=>1,
      'msg'=>'✅ Link added successfully!',
      'row'=>[
        'id'=>(int)$row->id,
        'domain'=>$row->domain,
        'tip'=>(int)$row->tip,
        'alexa1'=>(int)($row->alexa1 ?? 0),
        'alexa2'=>(int)($row->alexa2 ?? 0),
        'domain_year'=>isset($row->domain_year) ? $row->domain_year : '–',
        'sure'=>isset($row->sure) ? $row->sure : '–',
        'country'=>isset($row->country) ? $row->country : '–',
        'created_at'=>$row->created_at ?? '–'
      ]
    ]);
  }catch(Throwable $e){
    json_exit(['ok'=>0,'msg'=>'Server error: '.$e->getMessage()]);
  }
}

// ---------- AJAX: delete (single or selected) ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['ajax'] ?? '') === 'delete_selected')) {
  try{
    if (!csrf_ok($_POST['csrf'] ?? '')) json_exit(['ok'=>0,'msg'=>'Invalid CSRF token. Reload page.']);

    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || !count($ids)) json_exit(['ok'=>0,'msg'=>'No IDs selected.']);

    $ints = array_map('intval', $ids);
    $ints = array_values(array_filter($ints, fn($x)=>$x>0));
    if (!count($ints)) json_exit(['ok'=>0,'msg'=>'No valid IDs.']);

    $in = implode(',', $ints);
    $db->query("DELETE FROM k_linkdb WHERE id IN ($in)");
    json_exit(['ok'=>1,'msg'=>'🗑️ Deleted '.count($ints).' link(s).','ids'=>$ints]);
  }catch(Throwable $e){
    json_exit(['ok'=>0,'msg'=>'Server error: '.$e->getMessage()]);
  }
}

// ---------- Filters (search + type) ----------
$q_domain = trim($_GET['q'] ?? '');
$f_type   = $_GET['type'] ?? '';
$where    = "1=1";
if ($q_domain !== '') {
  $q_like = dbx('%'.$q_domain.'%');
  $where .= " AND domain LIKE '".$q_like."'";
}
if ($f_type !== '' && in_array($f_type, ['1','2','3'], true)) {
  $where .= " AND tip=".(int)$f_type;
}

// ---------- Pagination ----------
$limit  = 25;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$total  = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE $where");
$pages  = max(1, (int)ceil($total / $limit));

// ---------- Fetch list ----------
$latest = $db->get_results("
  SELECT id, domain, tip, ".($col_created_at ? "created_at" : "NOW() AS created_at")."
  FROM k_linkdb
  WHERE $where
  ORDER BY id DESC
  LIMIT $offset, $limit
");

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin • Add Links — HackLink</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<style>
:root{ --bg:#030611; --muted:#9aa7be; --neon1:#00e6ff; --neon2:#00ff9d; --warn:#ff9f9f; }
body{
  margin:0;
  background:
    radial-gradient(1000px 300px at 10% 10%,rgba(0,230,255,0.03),transparent 5%),
    radial-gradient(800px 300px at 90% 90%,rgba(0,255,157,0.02),transparent 5%),
    var(--bg);
  color:#e6eef8; font-family:'Share Tech Mono',monospace;
}
.container{padding:28px 20px;max-width:1260px;}
.h-title{font-size:22px;font-weight:800;color:var(--neon1);text-shadow:0 0 8px var(--neon1);}
.small{color:var(--muted);font-size:13px;}
/* unified hacker navbar */
.navx{
  display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:22px;
  background:linear-gradient(90deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02));
  border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:10px 15px;box-shadow:0 8px 30px rgba(0,0,0,.6);
}
.navx a{color:#e6eef8;text-decoration:none;background:rgba(96,165,250,0.1);padding:8px 14px;border-radius:8px;transition:.18s;}
.navx a:hover{background:linear-gradient(90deg,#60a5fa,#a855f7);color:#fff;text-shadow:0 0 8px rgba(96,165,250,.5);}
.navx a.active{color:#00ff9d;border:1px solid rgba(0,255,157,.25);}
.logout{background:linear-gradient(90deg,#ef4444,#b91c1c);color:#fff !important;border:none;}
/* cards & table */
.cardx{
  background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
  border:1px solid rgba(96,165,250,0.08);border-radius:12px;padding:18px;box-shadow:0 12px 36px rgba(0,0,0,.6);
}
.form-control, .form-select{background:rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.08);color:#e6eef8;}
.form-label{color:#9aa7be;font-size:13px;}
.btn-neon{background:linear-gradient(90deg,var(--neon2),var(--neon1));border:0;color:#001214;font-weight:700;}
.table thead th{color:#00e6ff;}
.table tbody tr:hover{background:rgba(0,255,157,0.06);}
.badge-tip{background:rgba(0,230,255,0.15);border:1px solid rgba(0,230,255,0.3);color:#00e6ff;}
/* pagination */
.pagi a{color:#00e6ff;text-decoration:none;margin:0 4px;}
.pagi a.active{font-weight:800;color:#00ff9d;border-bottom:1px solid rgba(0,255,157,.3);}
.pagi span{margin:0 4px;color:#9aa7be;}
/* actions */
.btn-del{border-color:rgba(255,99,99,.4)!important;color:var(--warn)!important}
.btn-del:hover{background:rgba(255,99,99,.15)!important;}
</style>
</head>
<body>
<div class="container">
  <div class="header mb-2 d-flex justify-content-between align-items-center">
    <div>
      <div class="h-title">🧠 HackLink Admin — Add Links</div>
      <div class="small">Welcome, <?= esc($user->username ?? 'admin') ?></div>
    </div>
    <div class="small">CSRF: <code><?= esc($_SESSION['csrf_admin_links']) ?></code></div>
  </div>

  <!-- 🔥 Unified Menu (new items before Logout) -->
  <div class="navx">
    <a href="index.php">🏠 Home</a>
    <a href="users.php">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php">🧩 Broken Links</a>
    <a href="login_logs.php">🧠 Admin Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php">🚫 Removed Sites</a>
    <a href="refunds.php">💸 Refunds</a>

    <!-- ✅ new menus -->
    <a href="add_link.php" class="active">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>

    <a href="../index.php">🌐 Front</a>
    <a href="logout.php" class="logout">Logout</a>
  </div>

  <!-- ▶ Add form -->
  <div class="cardx mb-4">
    <h5 style="color:#00e6ff;">➕ Add New Link</h5>
    <form id="addLinkForm" autocomplete="off" class="mt-2">
      <input type="hidden" name="ajax" value="add_link">
      <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf_admin_links']) ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Domain (example.com)</label>
          <input name="domain" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Type</label>
          <select name="type" class="form-select">
            <option value="1">PHP</option>
            <option value="2">JS</option>
            <option value="3">Other</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Credit (ups)</label>
          <input name="ups" class="form-control" type="number" value="1" min="1">
        </div>
        <div class="col-md-1">
          <label class="form-label">PA</label>
          <input name="alexa1" class="form-control" type="number" value="0">
        </div>
        <div class="col-md-1">
          <label class="form-label">DA</label>
          <input name="alexa2" class="form-control" type="number" value="0">
        </div>
      </div>

      <div class="row g-3 mt-2">
        <div class="col-md-3">
          <label class="form-label">Domain Year</label>
          <input name="domain_year" class="form-control" placeholder="e.g. 2015">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration (days)</label>
          <input name="sure" class="form-control" placeholder="e.g. 365">
        </div>
        <div class="col-md-3">
          <label class="form-label">Country</label>
          <input name="country" class="form-control" placeholder="e.g. US">
        </div>
      </div>

      <div class="mt-3 d-flex gap-2 align-items-center">
        <button id="btnAdd" class="btn btn-neon">Add Link</button>
        <button type="reset" class="btn btn-outline-light">Clear</button>
        <div id="formAlert" class="ms-2 small"></div>
      </div>
    </form>
  </div>

  <!-- 🔎 Search / Filter -->
  <div class="cardx mb-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-4">
        <label class="form-label">Search Domain</label>
        <input type="text" name="q" value="<?= esc($q_domain) ?>" class="form-control" placeholder="e.g. example">
      </div>
      <div class="col-md-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <option value="">All</option>
          <option value="1" <?= $f_type==='1'?'selected':'' ?>>PHP</option>
          <option value="2" <?= $f_type==='2'?'selected':'' ?>>JS</option>
          <option value="3" <?= $f_type==='3'?'selected':'' ?>>Other</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Per Page</label>
        <input type="text" class="form-control" value="25" disabled>
      </div>
      <div class="col-md-3">
        <button class="btn btn-neon w-100">Apply</button>
      </div>
    </form>
  </div>

  <!-- 📜 Latest list (25/page) + Bulk delete -->
  <div class="cardx">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 style="color:#00e6ff;">📜 Latest Added Links</h5>
      <div class="small">Total: <?= (int)$total ?> | Page: <?= (int)$page ?>/<?= (int)$pages ?></div>
    </div>

    <form id="bulkDeleteForm">
      <input type="hidden" name="ajax" value="delete_selected">
      <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf_admin_links']) ?>">
      <div class="table-responsive">
        <table class="table table-sm table-dark align-middle" id="logTable">
          <thead>
            <tr>
              <th style="width:36px"><input type="checkbox" id="selectAll"></th>
              <th style="width:70px">ID</th>
              <th>Domain</th>
              <th style="width:120px">Type</th>
              <th style="width:200px">Created</th>
              <th style="width:120px">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if($latest){ foreach($latest as $r){ ?>
            <tr id="row-<?= (int)$r->id ?>">
              <td><input type="checkbox" name="ids[]" value="<?= (int)$r->id ?>"></td>
              <td><?= (int)$r->id ?></td>
              <td><?= esc($r->domain) ?></td>
              <td><span class="badge badge-tip"><?= (int)$r->tip ?></span></td>
              <td><small class="text-muted"><?= esc($r->created_at) ?></small></td>
              <td><button type="button" class="btn btn-sm btn-outline-danger btn-del" data-id="<?= (int)$r->id ?>">Delete</button></td>
            </tr>
          <?php } } else { ?>
            <tr><td colspan="6" class="text-center text-muted">No links found.</td></tr>
          <?php } ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-2">
        <div class="d-flex gap-2">
          <button type="button" id="deleteSelected" class="btn btn-sm btn-danger">🗑 Delete Selected</button>
        </div>
        <div class="pagi">
          <?php
          // simple pagination window
          $win = 3;
          $start = max(1, $page - $win);
          $end   = min($pages, $page + $win);
          if ($page > 1) {
            $qs = http_build_query(array_filter(['q'=>$q_domain,'type'=>$f_type,'page'=>$page-1]));
            echo '<a href="?'.$qs.'">« Prev</a>';
          } else {
            echo '<span>« Prev</span>';
          }
          for($i=$start;$i<=$end;$i++){
            $qs = http_build_query(array_filter(['q'=>$q_domain,'type'=>$f_type,'page'=>$i]));
            echo '<a href="?'.$qs.'" class="'.($i==$page?'active':'').'">'.$i.'</a>';
          }
          if ($page < $pages) {
            $qs = http_build_query(array_filter(['q'=>$q_domain,'type'=>$f_type,'page'=>$page+1]));
            echo '<a href="?'.$qs.'">Next »</a>';
          } else {
            echo '<span>Next »</span>';
          }
          ?>
        </div>
      </div>
    </form>
  </div>

  <div class="small mt-3 text-center" style="color:#9aa7be;">© HackLink Panel — Admin</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// ====== ADD (AJAX) ======
$('#addLinkForm').on('submit', function(e){
  e.preventDefault();
  const $btn = $('#btnAdd');
  $btn.prop('disabled', true).text('Adding...');
  $('#formAlert').html('');
  $.ajax({
    url:'add_link.php',
    type:'POST',
    data: $(this).serialize(),
    dataType:'json'
  }).done(function(res){
    $btn.prop('disabled', false).text('Add Link');
    if(!res || !res.ok){
      $('#formAlert').html('<span class="text-danger">'+(res?res.msg:'Error')+'</span>');
      return;
    }
    $('#formAlert').html('<span class="text-success">'+res.msg+'</span>');
    if(res.row){
      const r = res.row;
      const safeDomain = $('<div>').text(r.domain).html();
      const safeCreated= $('<div>').text(r.created_at).html();
      const tr = `
        <tr id="row-${r.id}">
          <td><input type="checkbox" name="ids[]" value="${r.id}"></td>
          <td>${r.id}</td>
          <td>${safeDomain}</td>
          <td><span class="badge badge-tip">${r.tip}</span></td>
          <td><small class="text-muted">${safeCreated}</small></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger btn-del" data-id="${r.id}">Delete</button></td>
        </tr>`;
      // If we're on page 1 (latest), prepend new row
      const urlParams = new URLSearchParams(window.location.search);
      const currentPage = parseInt(urlParams.get('page') || '1', 10);
      if (currentPage === 1) {
        const $tb = $('#logTable tbody');
        $tb.prepend(tr);
        // keep 25 rows on this page
        const extra = $tb.find('tr').length - 25;
        if (extra > 0) { $tb.find('tr').slice(-extra).remove(); }
      }
    }
    // Optional: clear input
    // $('#addLinkForm')[0].reset();
  }).fail(function(){
    $btn.prop('disabled', false).text('Add Link');
    $('#formAlert').html('<span class="text-danger">Server error</span>');
  });
});

// ====== SELECT ALL ======
$('#selectAll').on('click', function(){
  $('input[name="ids[]"]').prop('checked', this.checked);
});

// ====== DELETE SINGLE ======
$(document).on('click', '.btn-del', function(){
  if(!confirm('Delete this link?')) return;
  const id = $(this).data('id');
  const csrf = '<?= esc($_SESSION['csrf_admin_links']) ?>';
  $(this).prop('disabled', true).text('Deleting…');
  $.post('add_link.php', {ajax:'delete_selected', csrf:csrf, ids:[id]}, function(res){
    if(res && res.ok){
      $('#row-'+id).fadeOut(150, function(){ $(this).remove(); });
    } else {
      alert(res ? res.msg : 'Delete failed');
    }
  }, 'json').fail(function(){
    alert('Server error');
  });
});

// ====== BULK DELETE ======
$('#deleteSelected').on('click', function(){
  const ids = $('input[name="ids[]"]:checked').map(function(){return this.value}).get();
  if(!ids.length) { alert('No links selected!'); return; }
  if(!confirm('Delete '+ids.length+' selected link(s)?')) return;
  const csrf = '<?= esc($_SESSION['csrf_admin_links']) ?>';
  const $btn = $(this);
  $btn.prop('disabled', true).text('Deleting…');
  $.post('add_link.php', {ajax:'delete_selected', csrf:csrf, ids:ids}, function(res){
    $btn.prop('disabled', false).text('🗑 Delete Selected');
    if(res && res.ok){
      ids.forEach(id => $('#row-'+id).fadeOut(120,function(){ $(this).remove(); }));
      alert(res.msg);
    } else {
      alert(res ? res.msg : 'Delete failed');
    }
  }, 'json').fail(function(){
    $btn.prop('disabled', false).text('🗑 Delete Selected');
    alert('Server error');
  });
});
</script>
</body>
</html>