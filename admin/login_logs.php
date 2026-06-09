<?php
include __DIR__ . "/_bootstrap.php";

// ✅ Ensure DB connection
if (!isset($db) || !is_object($db)) {
    die("Error: Database not initialized.");
}

// ✅ Table check and auto-fix schema
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS k_admin_login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            username VARCHAR(190) NULL,
            ip VARCHAR(45) NULL,
            user_agent TEXT NULL,
            success TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(admin_id),
            INDEX(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // ✅ AUTO-FIX: Check and add missing columns
    $columns = $db->get_results("SHOW COLUMNS FROM k_admin_login_logs");
    $column_names = [];
    foreach ($columns as $col) {
        $column_names[] = $col->Field;
    }
    
    if (!in_array('user_agent', $column_names)) {
        $db->query("ALTER TABLE k_admin_login_logs ADD COLUMN user_agent TEXT NULL AFTER ip");
    }
    
    if (!in_array('success', $column_names)) {
        $db->query("ALTER TABLE k_admin_login_logs ADD COLUMN success TINYINT(1) DEFAULT 0 AFTER user_agent");
    }
    
} catch (Throwable $e) {
    die("Error creating table: " . htmlspecialchars($e->getMessage()));
}

// ✅ Safe escape fallback
function safe_escape($db, $val) {
    return method_exists($db, 'escape') ? $db->escape($val) : addslashes($val);
}

// ✅ Filters
$where = "1=1";
$username = trim($_GET['username'] ?? '');
$ip = trim($_GET['ip'] ?? '');
$status = $_GET['success'] ?? '';

if ($username !== '') $where .= " AND username LIKE '%" . safe_escape($db, $username) . "%'";
if ($ip !== '') $where .= " AND ip LIKE '%" . safe_escape($db, $ip) . "%'";
if ($status !== '' && in_array($status, ['0', '1'])) $where .= " AND success = " . (int)$status;

// ✅ DEBUG: Check what's happening
error_log("Login Logs Query: SELECT * FROM k_admin_login_logs WHERE $where ORDER BY id DESC LIMIT 200");

// ✅ IMPROVED: Fetch logs with better error handling
try {
    // First check if table has data
    $test_count = $db->get_var("SELECT COUNT(*) FROM k_admin_login_logs");
    error_log("Total records in k_admin_login_logs: " . $test_count);
    
    // Try different methods to fetch data
    if (method_exists($db, 'get_results')) {
        $rows = $db->get_results("SELECT * FROM k_admin_login_logs WHERE $where ORDER BY id DESC LIMIT 200") ?: [];
    } else {
        // Alternative method if get_results doesn't work
        $result = $db->query("SELECT * FROM k_admin_login_logs WHERE $where ORDER BY id DESC LIMIT 200");
        $rows = [];
        if ($result && method_exists($result, 'fetch_all')) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
        } elseif ($result) {
            while ($row = $result->fetch_object()) {
                $rows[] = $row;
            }
        }
    }
    
    error_log("Fetched " . count($rows) . " rows");
    
} catch (Throwable $e) {
    error_log("Error fetching logs: " . $e->getMessage());
    $rows = [];
}

// ✅ IMPROVED: Stats with fallback
try {
    $total_all = (int)$db->get_var("SELECT COUNT(*) FROM k_admin_login_logs");
    $total_success = (int)$db->get_var("SELECT COUNT(*) FROM k_admin_login_logs WHERE success=1");
    $total_failed = (int)$db->get_var("SELECT COUNT(*) FROM k_admin_login_logs WHERE success=0");
    $success_rate = $total_all ? round(($total_success/$total_all)*100,1) : 0;
} catch (Throwable $e) {
    $total_all = $total_success = $total_failed = 0;
    $success_rate = 0;
    error_log("Error calculating stats: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>🧠 Admin Login Logs</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{background:#060717;color:#e6eef8;font-family:'Share Tech Mono',monospace;}
h2{color:#00e6ff;text-shadow:0 0 6px rgba(0,230,255,.5);}
.card{background:rgba(255,255,255,0.02);border:1px solid rgba(0,255,157,0.1);border-radius:12px;box-shadow:0 0 25px rgba(0,255,157,.05);}
.badge-ok{background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#001214;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:bold;}
.badge-fail{background:linear-gradient(90deg,#ff5f5f,#ff9f7f);color:#120000;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:bold;}
.navx{display:flex;flex-wrap:wrap;gap:10px;background:linear-gradient(90deg,#000,#051024);border:1px solid rgba(0,230,255,0.08);border-radius:12px;padding:12px 16px;justify-content:center;box-shadow:0 8px 30px rgba(0,0,0,0.6);margin-bottom:25px;}
.navx a{text-decoration:none;color:#e6eef8;padding:8px 14px;border-radius:8px;background:rgba(96,165,250,0.08);transition:.2s;}
.navx a:hover{background:linear-gradient(90deg,#00e6ff,#00ff9d);color:#000;}
.logout-btn{background:linear-gradient(90deg,#ef4444,#b91c1c);padding:6px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:600;}
.logout-btn:hover{box-shadow:0 0 15px rgba(239,68,68,.5);}
canvas{max-height:240px!important;margin-top:15px;}
.search-box{background:rgba(255,255,255,0.05);border:1px solid rgba(0,230,255,0.2);border-radius:10px;padding:20px;margin-bottom:20px;}
</style>
</head>
<body class="p-4">

<!-- 🔥 Hacker Navbar -->
<div class="container my-3">
  <div class="navx">
    <a href="index.php">🏠 Home</a>
    <a href="users.php">👥 Users</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="broken_links.php">🧩 Broken Links</a>
    <a href="login_logs.php" style="background:linear-gradient(90deg,#00e6ff,#00ff9d);color:#000;">🧠 Admin Logs</a>
    <a href="user_login_logs.php">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php" style="color:#ff7171;">🚫 Removed Sites</a>
    <a href="refunds.php">💸 Refunds</a>
    <a href="../index.php">🌐 Front</a>
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>
    <a href="logout.php" class="logout-btn">🔓 Logout</a>
  </div>
</div>

<div class="container">
  <h2 class="mb-4">🧠 Admin Login Logs</h2>

  <!-- 🔍 Search Box -->
  <div class="card search-box">
    <form method="get" class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" placeholder="Search username...">
      </div>
      <div class="col-md-3">
        <label class="form-label">IP Address</label>
        <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($ip) ?>" placeholder="Search IP...">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="success" class="form-select">
          <option value="">All Status</option>
          <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Success</option>
          <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Failed</option>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Search</button>
        <a href="login_logs.php" class="btn btn-outline-secondary ms-2">Clear</a>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card p-3 text-center"><h6>Total Attempts</h6><h3><?= $total_all ?></h3></div></div>
    <div class="col-md-3"><div class="card p-3 text-center"><h6>Successful</h6><h3 class="text-success"><?= $total_success ?></h3></div></div>
    <div class="col-md-3"><div class="card p-3 text-center"><h6>Failed</h6><h3 class="text-danger"><?= $total_failed ?></h3></div></div>
    <div class="col-md-3"><div class="card p-3 text-center"><h6>Success Rate</h6><h3 class="text-info"><?= $success_rate ?>%</h3></div></div>
  </div>

  <div class="card p-4 mb-4">
    <h5>Login Success vs Failed</h5>
    <canvas id="loginChart"></canvas>
  </div>

  <div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Login Attempts</h5>
      <small class="text-muted">Showing <?= count($rows) ?> records</small>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Username</th>
            <th>IP</th>
            <th>User Agent</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              <div>📭 No login records found</div>
              <small class="text-warning">Try logging in from admin panel to generate logs</small>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach($rows as $r): 
            $username_display = htmlspecialchars($r->username ?? '-');
            $ip_display = htmlspecialchars($r->ip ?? '-');
            $user_agent = htmlspecialchars($r->user_agent ?? '');
            $created_at = htmlspecialchars($r->created_at ?? '');
          ?>
          <tr>
            <td><strong><?= $r->id ?></strong></td>
            <td><?= $username_display ?></td>
            <td><code class="text-info"><?= $ip_display ?></code></td>
            <td>
              <small title="<?= $user_agent ?>">
                <?= substr($user_agent, 0, 80) ?>
                <?= strlen($user_agent) > 80 ? '…' : '' ?>
              </small>
            </td>
            <td>
              <?php if ($r->success): ?>
                <span class="badge-ok">✅ SUCCESS</span>
              <?php else: ?>
                <span class="badge-fail">❌ FAILED</span>
              <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= $created_at ?></small></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('loginChart'), {
  type: 'doughnut',
  data: {
    labels: ['Success', 'Failed'],
    datasets: [{
      data: [<?= $total_success ?: 0 ?>, <?= $total_failed ?: 0 ?>],
      backgroundColor: ['#00ff9d', '#ff5f5f'],
      borderColor: 'rgba(0,0,0,0.2)',
      borderWidth: 2,
      cutout: '70%'
    }]
  },
  options: { 
    responsive: true,
    plugins: { 
      legend: { 
        labels: { 
          color:'#e6eef8',
          font: { size: 14 }
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            let label = context.label || '';
            let value = context.raw || 0;
            let total = context.dataset.data.reduce((a, b) => a + b, 0);
            let percentage = Math.round((value / total) * 100);
            return `${label}: ${value} (${percentage}%)`;
          }
        }
      }
    }
  }
});
</script>
</body>
</html>