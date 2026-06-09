<?php
include __DIR__ . "/_bootstrap.php";

// Admin guard
if (empty($user) || ($user->role ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

/**
 * ---------------------------
 * DELETE ALL LOGS (Admin Only)
 * ---------------------------
 * This block handles a POST request with action=delete_all_logs and a valid CSRF token.
 * It deletes all rows from k_user_login_logs (uses DELETE for safety/compatibility).
 * No other code in the file is removed or modified.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_all_logs') {
    // Simple CSRF protection
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['user_login_logs_msg'] = ['type' => 'danger', 'text' => 'CSRF validation failed.'];
    } else {
        try {
            // Delete all logs
            $db->query("DELETE FROM k_user_login_logs");
            // Optionally, reset AUTO_INCREMENT for neatness (works on MySQL)
            try { $db->query("ALTER TABLE k_user_login_logs AUTO_INCREMENT = 1"); } catch(Throwable $e) {}
            $_SESSION['user_login_logs_msg'] = ['type' => 'success', 'text' => 'All user login logs have been deleted.'];
        } catch (Throwable $e) {
            error_log("Delete all user login logs error: " . $e->getMessage());
            $_SESSION['user_login_logs_msg'] = ['type' => 'danger', 'text' => 'Failed to delete logs. Check server logs.'];
        }
    }
    // Redirect to avoid form re-submission
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// prepare CSRF token for the delete form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Pagination setup
$limit = 10;
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Total logs count
$total_logs = (int) $db->get_var("SELECT COUNT(*) FROM k_user_login_logs");
$total_pages = max(1, ceil($total_logs / $limit));

// First, let's check the actual column names in the table
$debug_columns = $db->get_results("SHOW COLUMNS FROM k_user_login_logs");
$column_names = [];
foreach ($debug_columns as $col) {
    $column_names[] = $col->Field;
}

// Debug: Check what columns exist
error_log("k_user_login_logs columns: " . implode(', ', $column_names));

// Fetch logs for current page with JOIN to get username from k_users table
$logs = $db->get_results("
    SELECT 
        l.*,
        u.username as actual_username,
        u.email as user_email
    FROM k_user_login_logs l
    LEFT JOIN k_users u ON l.user_id = u.id
    ORDER BY l.created_at DESC 
    LIMIT $offset, $limit
");

// Safe string truncation function
function safe_truncate($string, $length = 80) {
    if ($string === null || $string === '') {
        return '-';
    }
    $string = (string)$string;
    $truncated = strlen($string) > $length ? substr($string, 0, $length) . '…' : $string;
    return htmlspecialchars($truncated);
}

// Function to get username from different possible column names
function get_username($row) {
    // First try the joined username from k_users table
    if (isset($row->actual_username) && !empty($row->actual_username)) {
        return $row->actual_username;
    }
    
    // Then try different possible column names for username in login logs table
    if (isset($row->username) && !empty($row->username)) {
        return $row->username;
    }
    if (isset($row->user_name) && !empty($row->user_name)) {
        return $row->user_name;
    }
    if (isset($row->user) && !empty($row->user)) {
        return $row->user;
    }
    if (isset($row->email) && !empty($row->email)) {
        return $row->email;
    }
    if (isset($row->user_email) && !empty($row->user_email)) {
        return $row->user_email;
    }
    
    // If user_id exists but no username, show user_id
    if (isset($row->user_id) && !empty($row->user_id)) {
        return "User#" . $row->user_id;
    }
    
    return '-';
}

// Function to get user agent from different possible column names
function get_user_agent($row) {
    // Try different possible column names for user agent
    if (isset($row->user_agent) && !empty($row->user_agent)) {
        return $row->user_agent;
    }
    if (isset($row->useragent) && !empty($row->useragent)) {
        return $row->useragent;
    }
    if (isset($row->agent) && !empty($row->agent)) {
        return $row->agent;
    }
    if (isset($row->browser) && !empty($row->browser)) {
        return $row->browser;
    }
    return '-';
}

// Function to get IP from different possible column names
function get_ip($row) {
    if (isset($row->ip) && !empty($row->ip)) {
        return $row->ip;
    }
    if (isset($row->ip_address) && !empty($row->ip_address)) {
        return $row->ip_address;
    }
    if (isset($row->client_ip) && !empty($row->client_ip)) {
        return $row->client_ip;
    }
    return '-';
}

// Function to get success status from different possible column names
function get_success_status($row) {
    if (isset($row->success)) {
        return (bool)$row->success;
    }
    if (isset($row->status)) {
        return $row->status === 'success' || $row->status === 1;
    }
    if (isset($row->is_success)) {
        return (bool)$row->is_success;
    }
    return false;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>👤 User Login Logs — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#060717;color:#e6eef8;font-family:'Share Tech Mono',monospace;}
.container{max-width:1200px;margin-top:40px;}
h2{color:#00e6ff;text-shadow:0 0 8px rgba(0,230,255,.6);}
.table thead th{color:#00e6ff;}
.table tbody tr:hover{background:rgba(0,255,157,0.05);}
.badge-ok{background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#001214;padding:6px 12px;border-radius:6px;font-weight:600;}
.badge-fail{background:linear-gradient(90deg,#ff5f5f,#ff9f7f);color:#120000;padding:6px 12px;border-radius:6px;font-weight:600;}
.cardx{background:rgba(255,255,255,0.02);border:1px solid rgba(0,255,157,0.1);border-radius:12px;padding:20px;}
.nav-back{color:#00e6ff;text-decoration:none;}
.nav-back:hover{text-decoration:underline;}
.pagination{display:flex;justify-content:center;margin-top:20px;}
.pagination a{color:#00e6ff;text-decoration:none;margin:0 6px;padding:6px 12px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(0,230,255,0.1);}
.pagination a:hover{background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#000;}
.pagination .active{background:linear-gradient(90deg,#00ff9d,#00e6ff);color:#000;font-weight:700;}

/* 🔥 Hacker Navbar */
.navx{
  display:flex;flex-wrap:wrap;gap:10px;
  background:linear-gradient(90deg,#000,#051024);
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
}
.navx a.active{
  color:#00ff9d;
  border-color:rgba(0,255,157,0.25);
  box-shadow:0 0 12px rgba(0,255,157,0.1);
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
.logout-btn:hover{box-shadow:0 0 15px rgba(239,68,68,.5);color:#fff;}

/* Delete all button */
.btn-delete-all {
  background:linear-gradient(90deg,#ff5f5f,#ff9f7f);
  color:#120000;
  border:1px solid rgba(255,99,99,0.15);
  padding:6px 12px;
  border-radius:8px;
  font-weight:700;
}
.btn-delete-all:hover { box-shadow:0 0 12px rgba(255,99,99,0.12); transform:translateY(-2px); }

/* Debug info */
.debug-info {
  background: rgba(255,0,0,0.1);
  border: 1px solid rgba(255,0,0,0.3);
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 15px;
  font-size: 12px;
}

.username-cell {
  font-weight: 600;
  color: #00ff9d;
}
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
    <a href="user_login_logs.php" style="background:linear-gradient(90deg,#60a5fa,#a855f7);color:#fff;">📜 User Login Logs</a>
    <a href="monitors.php">🔗 URL Monitor</a>
    <a href="deleted_sites.php" style="color:#ff7171;">🚫 Removed Sites</a>
    <a href="refunds.php">💸 Refunds</a>
    <a href="../index.php">🌐 Front</a>

    <!-- ✅ New menu -->
    <a href="add_link.php">➕ Add Links</a>
    <a href="auto_jobs.php">⚙️ Auto Jobs</a>
    <a href="placements.php">📍 Placements</a>
    <a href="payments.php">💳 Payments</a>

    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>📜 User Login Logs</h2>
    <div class="d-flex align-items-center gap-3">
      <a href="index.php" class="nav-back">⬅ Back to Dashboard</a>

      <!-- Delete All Logs form (CSRF protected) -->
      <form id="deleteAllForm" method="post" style="display:inline-block;margin:0;">
        <input type="hidden" name="action" value="delete_all_logs">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
        <button type="button" class="btn-delete-all" onclick="confirmDeleteAll()">🗑️ Delete All Logs</button>
      </form>
    </div>
  </div>

  <?php
    // Flash message (from delete action)
    if (!empty($_SESSION['user_login_logs_msg'])) {
      $m = $_SESSION['user_login_logs_msg'];
      $cls = ($m['type'] === 'success') ? 'alert-success' : 'alert-danger';
      echo '<div class="mb-3"><div class="alert '.$cls.'">'.htmlspecialchars($m['text']).'</div></div>';
      unset($_SESSION['user_login_logs_msg']);
    }

    // Debug: Show available columns (remove in production)
    if (!empty($logs)) {
        echo '<!-- Debug: Available columns in k_user_login_logs: ' . implode(', ', $column_names) . ' -->';
        echo '<!-- Debug: First row data: ' . json_encode($logs[0]) . ' -->';
    }
  ?>

  <div class="cardx">
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
        <?php if(!$logs): ?>
          <tr><td colspan="6" class="text-center text-muted">No login records found.</td></tr>
        <?php else: foreach($logs as $r): ?>
          <tr>
            <td><?=$r->id?></td>
            <td class="username-cell"><?=htmlspecialchars(get_username($r))?></td>
            <td><code><?=htmlspecialchars(get_ip($r))?></code></td>
            <td><small><?=safe_truncate(get_user_agent($r))?></small></td>
            <td>
              <?php if(get_success_status($r)){ ?>
                <span class="badge-ok">SUCCESS</span>
              <?php } else { ?>
                <span class="badge-fail">FAILED</span>
              <?php } ?>
            </td>
            <td><small><?=htmlspecialchars($r->created_at ?? '-')?></small></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- 📄 Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?=($page-1)?>">⬅ Prev</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?=$i?>" class="<?=($i==$page)?'active':''?>"><?=$i?></a>
      <?php endfor; ?>
      <?php if ($page < $total_pages): ?>
        <a href="?page=<?=$page+1?>">Next ➡</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Confirm & submit delete all form
function confirmDeleteAll(){
  if (confirm('Are you sure you want to PERMANENTLY delete ALL user login logs? This action cannot be undone.')) {
    document.getElementById('deleteAllForm').submit();
  }
}
</script>
</body>
</html>