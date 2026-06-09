<?php
session_start();
require_once '../config.php';
require_once '../inc/check_url.php';

// CSRF
if (empty($_SESSION['csrf_mon_key'])) {
    $_SESSION['csrf_mon_key'] = bin2hex(random_bytes(16));
}
function csrf_ok($token) {
    return isset($_SESSION['csrf_mon_key']) && hash_equals($_SESSION['csrf_mon_key'], $token ?? '');
}
function clean($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------------------- ACTIONS ----------------------
$msg = null; $err = null;
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('Invalid CSRF token.');
            $url = trim($_POST['url'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $email = trim($_POST['notify_email'] ?? '');
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) throw new Exception('Valid URL required.');
            $stmt = $pdo->prepare("INSERT INTO monitored_urls (url,label,notify_email) VALUES (?,?,?)");
            $stmt->execute([$url, $label ?: null, $email ?: null]);
            $msg = "Added: {$url}";
        }

        if ($action === 'check') {
            if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('Invalid CSRF token.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id.');
            $row = $pdo->prepare("SELECT * FROM monitored_urls WHERE id=?");
            $row->execute([$id]);
            $mon = $row->fetch(PDO::FETCH_ASSOC);
            if (!$mon) throw new Exception('Monitor row not found.');

            $res = check_url($mon['url'], ['timeout'=>12, 'inspect_body'=>true]);
            $http_code = $res['http_code'] ?? 0;
            $status    = $res['status'] ?? 'ERROR';
            $hash      = $res['content_hash'] ?? null;
            $etag      = $res['etag'] ?? null;
            $lm        = $res['last_modified'] ?? null;
            $snippet   = $res['response_snippet'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO url_checks (url_id,http_code,status,etag,last_modified,content_hash,response_snippet) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$mon['id'],$http_code,$status,$etag,$lm,$hash,$snippet]);
            $u = $pdo->prepare("UPDATE monitored_urls SET last_http_code=?, last_status=?, last_checked=NOW(), last_etag=?, last_modified=?, last_hash=? WHERE id=?");
            $u->execute([$http_code,$status,$etag,$lm,$hash,$mon['id']]);
            $msg = "Checked: {$mon['url']} => {$status} ({$http_code})";
        }

        if ($action === 'delete') {
            if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('Invalid CSRF token.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id.');
            $pdo->prepare("DELETE FROM monitored_urls WHERE id=?")->execute([$id]);
            $msg = "Deleted monitor id={$id}";
        }
    }
} catch (Exception $ex) { $err = $ex->getMessage(); }

// ---------------------- FETCH ----------------------
$list = $pdo->query("SELECT * FROM monitored_urls ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$histories = [];
if ($list) {
    $ids = array_map(fn($r)=> (int)$r['id'], $list);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $hstmt = $pdo->prepare("SELECT * FROM url_checks WHERE url_id IN ($ph) ORDER BY checked_at DESC");
    $hstmt->execute($ids);
    while ($h = $hstmt->fetch(PDO::FETCH_ASSOC)) {
        $histories[$h['url_id']][] = $h;
        if (count($histories[$h['url_id']]) > 10) array_pop($histories[$h['url_id']]);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>HackLink — URL Monitors</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<style>
body{background:#030607;color:#c7f9e8;font-family:'Share Tech Mono',monospace;}
.hack-title{font-family:'Orbitron',sans-serif;text-transform:uppercase;letter-spacing:2px;color:#00e6ff;text-shadow:0 0 10px #00e6ff;}
.neon-card{background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));
border:1px solid rgba(255,255,255,0.05);border-radius:14px;box-shadow:0 0 25px rgba(0,255,157,0.08),inset 0 0 10px rgba(0,230,255,0.06);}
.btn-checknow{background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#002015;font-weight:700;border:none;}
.btn-checknow:hover{opacity:.9;transform:scale(1.03);}
.table thead th{color:#00e6ff;}
.table tbody tr:hover{background:rgba(0,255,157,0.05);}
.btn-outline-neon{border:1px solid rgba(0,230,255,0.4);color:#00e6ff;}
.btn-outline-neon:hover{background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#001;}
.navx{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:25px;
background:linear-gradient(90deg,rgba(255,255,255,0.04),rgba(255,255,255,0.02));
border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:10px 15px;box-shadow:0 8px 30px rgba(0,0,0,0.6);}
.navx a{text-decoration:none;color:#e6eef8;background:rgba(96,165,250,0.1);padding:8px 14px;border-radius:8px;font-weight:500;transition:.2s;}
.navx a:hover{background:linear-gradient(90deg,#60a5fa,#a855f7);color:#fff;text-shadow:0 0 8px rgba(96,165,250,0.5);}
.logout-btn{background:linear-gradient(90deg,#ef4444,#b91c1c);padding:6px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:600;transition:.2s;}
.logout-btn:hover{box-shadow:0 0 15px rgba(239,68,68,.5);}
.console-bar{margin-top:40px;text-align:center;font-size:13px;color:#00e6ff;opacity:.9;}
</style>
</head>
<body class="p-4">

<!-- 🔥 HACKER NAVIGATION -->
<div class="container my-3">
  <div class="navx">
    <a href="index.php">🏠 Home</a>
    <a href="users.php">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php">🧩 Broken Links</a>
    <a href="login_logs.php">🧠 Admin Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php" style="background:linear-gradient(90deg,#60a5fa,#a855f7);color:#fff;">🔗 URL Monitor</a>
    <a href="deleted_sites.php" style="color:#ff7171;">🚫 Removed Sites</a>
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
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="hack-title">⚙ URL Monitors</h2>
  </div>

  <?php if($msg): ?><div class="alert alert-success"><?=clean($msg)?></div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-danger"><?=clean($err)?></div><?php endif; ?>

  <div class="neon-card p-4 mb-4">
    <h5>➕ Add New URL Monitor</h5>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=clean($_SESSION['csrf_mon_key'])?>">
      <input type="hidden" name="action" value="add">
      <div class="row g-3">
        <div class="col-md-5"><input type="url" class="form-control" name="url" placeholder="https://example.com" required></div>
        <div class="col-md-3"><input type="text" class="form-control" name="label" placeholder="Label (optional)"></div>
        <div class="col-md-3"><input type="email" class="form-control" name="notify_email" placeholder="Notify Email"></div>
        <div class="col-md-1"><button class="btn btn-checknow w-100">Add</button></div>
      </div>
    </form>
  </div>

  <div class="neon-card p-4">
    <h5>🔍 Monitored URLs</h5>
    <div class="table-responsive">
      <table class="table table-dark table-sm align-middle">
        <thead><tr><th>ID</th><th>URL</th><th>Label</th><th>Last Status</th><th>Last Checked</th><th>Action</th></tr></thead>
        <tbody>
          <?php if(!$list): ?>
            <tr><td colspan="6" class="text-center text-muted">No URLs added yet.</td></tr>
          <?php else: foreach($list as $r): ?>
            <tr>
              <td><?=$r['id']?></td>
              <td><a href="<?=clean($r['url'])?>" target="_blank" style="color:#00e6ff;"><?=clean($r['url'])?></a></td>
              <td><?=clean($r['label'] ?? '-')?></td>
              <td><?=clean($r['last_status'] ?? '-')?></td>
              <td><?=clean($r['last_checked'] ?? '-')?></td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf" value="<?=clean($_SESSION['csrf_mon_key'])?>">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <input type="hidden" name="action" value="check">
                  <button class="btn btn-sm btn-outline-neon">Check</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this monitor?');">
                  <input type="hidden" name="csrf" value="<?=clean($_SESSION['csrf_mon_key'])?>">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php if(!empty($histories[$r['id']])): ?>
              <tr>
                <td colspan="6">
                  <div class="small text-muted">
                    <?php foreach($histories[$r['id']] as $h): ?>
                      <div>🕓 <?=clean($h['checked_at'])?> — <?=clean($h['status'])?> (HTTP <?=clean($h['http_code'])?>)</div>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="console-bar">🧠 Monitor Active • Tracking <?=count($list)?> URLs • Updated <?=date('H:i:s')?> ⚡</div>
</body>
</html>
