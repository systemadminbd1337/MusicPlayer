<?php
// ===== User Dashboard (index.php) =====
// Drop-in replacement. Assumes header.php sets up $db, session, $__t, etc.

include "header.php";

// --- Ensure $user is set from session ---
if (empty($user) && !empty($_SESSION['user'])) {
    $user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
}
if (empty($user) || empty($user->id)) {
    header("Location: login.php");
    exit;
}
$uid = (int)$user->id;

// ---------- Safe helpers ----------
function safe_get_var($sql, $def = 0){ global $db;
  try { $v = $db->get_var($sql); return $v === null ? $def : $v; } catch(Throwable $e){ return $def; }
}
function safe_get_results($sql){ global $db;
  try { $r = $db->get_results($sql, ARRAY_A); return $r ?: []; } catch(Throwable $e){ return []; }
}

// ---------- User-specific counts ----------
$singleLinks   = (int)safe_get_var("SELECT COUNT(*) FROM k_single WHERE uid={$uid}", 0);
$multiLinks    = (int)safe_get_var("SELECT COUNT(*) FROM k_multi  WHERE uid={$uid} AND tip='1'", 0); // backlinks
$multiAnchors  = (int)safe_get_var("SELECT COUNT(*) FROM k_multi  WHERE uid={$uid} AND tip='2'", 0); // anchors

// 💾 Link Database = user added total (k_single + k_multi)
$orderCount = (int)safe_get_var("
  SELECT COUNT(*) FROM (
    SELECT id FROM k_single WHERE uid={$uid}
    UNION ALL
    SELECT id FROM k_multi  WHERE uid={$uid}
  ) AS all_user_links
", 0);

// Package / Kota (0 => Unlimited)
$user->kota = isset($user->kota) ? (int)$user->kota : 0;
$quotaUsedPct = 0;
if ($user->kota > 0) {
  $quotaUsedPct = min(100, round(($singleLinks / max(1,$user->kota)) * 100));
}

// ---------- Site-wide stats ----------
$total_sites     = (int)safe_get_var("SELECT COUNT(*) FROM k_sites", 0);
$purchased_links = (int)safe_get_var("SELECT COUNT(*) FROM k_orders", 0);
$total_expense   = safe_get_var("SELECT COALESCE(SUM(price),0) FROM k_orders", 0);

// TLD distribution (fallback to suffix if tld empty)
$tld_rows = safe_get_results("
  SELECT COALESCE(NULLIF(tld,''), LOWER(SUBSTRING_INDEX(domain,'.',-1))) AS tld,
         COUNT(*) AS c
  FROM k_sites
  GROUP BY tld
  ORDER BY c DESC
");

// 14-day order history
$link_history_rows = safe_get_results("
  SELECT DATE(created_at) AS d, COUNT(*) AS c
  FROM k_orders
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");

// ---------- Last logins (robust columns detection) ----------
$log_rows = [];
try {
  $cols = safe_get_results("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='k_logs'");
  $cn = array_map(fn($r)=>strtolower($r['COLUMN_NAME']), $cols);

  $ipC = null; $ctSelect = []; $dtC = null;
  foreach(['ip','ip_address','login_ip','remote_addr'] as $c){ if(in_array($c,$cn)){$ipC=$c;break;} }
  foreach(['country_name','country','location','city','country_code','region'] as $c){ if(in_array($c,$cn)) $ctSelect[]="`$c`"; }
  foreach(['created_at','logged_at','timestamp','time','date'] as $c){ if(in_array($c,$cn)){$dtC=$c;break;} }

  $sel_ip = $ipC ? "`$ipC` AS ip" : "NULL AS ip";
  $sel_dt = $dtC ? "`$dtC` AS created_at" : "NULL AS created_at";
  $sel_ct = !empty($ctSelect) ? "NULLIF(TRIM(CONCAT_WS(', ',".implode(',',$ctSelect).")),'') AS country" : "NULL AS country";
  $order = $dtC ? "`$dtC` DESC" : "1";
  $log_rows = safe_get_results("SELECT $sel_ip, $sel_ct, $sel_dt FROM k_logs ORDER BY $order LIMIT 10");
} catch(Throwable $e) {}
if (empty($log_rows)) {
  // fallback from users table
  $log_rows = safe_get_results("
    SELECT last_login_ip AS ip, last_login_country AS country, last_login_time AS created_at
    FROM k_users
    ORDER BY last_login_time DESC
    LIMIT 10
  ");
}

// Announcements
$ann_rows = safe_get_results("
  SELECT id, title, message, author, created_at
  FROM k_announcements
  WHERE COALESCE(visible,1)=1
  ORDER BY created_at DESC
  LIMIT 10
");

// ---------- Prep chart arrays ----------
$tld_labels=[]; $tld_counts=[];
foreach($tld_rows as $r){ $tld_labels[] = $r['tld'] ?: 'other'; $tld_counts[] = (int)$r['c']; }

$total_tlds_sum = array_sum($tld_counts);
$tld_percentages = [];
if ($total_tlds_sum > 0) {
  foreach ($tld_rows as $r) {
    $label = $r['tld'] ?: 'other';
    $count = (int)$r['c'];
    $perc  = round(($count/$total_tlds_sum)*100, 1);
    $tld_percentages[$label] = $perc;
  }
}

$hist_labels=[]; $hist_counts=[]; $map=[];
foreach($link_history_rows as $r){ $map[$r['d']] = $r['c']; }
$start = new DateTime('-13 days');
for($i=0;$i<14;$i++){
  $d = (clone $start)->modify("+{$i} day");
  $k = $d->format('Y-m-d'); $hist_labels[] = $k; $hist_counts[] = $map[$k] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - HackLink</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{
  background: radial-gradient(circle at 20% 20%, #0a0f1e 0%, #030611 40%, #000 100%) !important;
  color:#fff !important;
}
body::before{
  content:""; position:fixed; inset:0; pointer-events:none; z-index:0;
  background:
    radial-gradient(circle at 50% 0%, rgba(96,165,250,.12), transparent 60%),
    radial-gradient(circle at 100% 100%, rgba(239,68,68,.1), transparent 60%),
    radial-gradient(circle at 0% 100%, rgba(250,204,21,.1), transparent 70%);
  mix-blend-mode:screen;
}
.container{position:relative;z-index:1;}

.card.neon, .procard{
  background: rgba(8,12,24,.65);
  border:1px solid rgba(255,255,255,.06);
  border-radius:14px;
  box-shadow:0 8px 30px rgba(0,0,0,.6);
  backdrop-filter: blur(8px);
}
.procard h5{color:#fff;font-weight:600;margin-bottom:.35rem}
.procard p.stat{
  font-size:2.1rem; font-weight:800; color:#ef4444;
  text-shadow:0 0 10px rgba(239,68,68,.6); margin:0;
}
.progress{height:8px;background:#17172a;border-radius:6px;overflow:hidden;margin-top:8px;}
.progress-bar{background:linear-gradient(90deg,#00ff9d,#00e6ff);}
.panel-title{color:#fff;text-shadow:0 0 8px rgba(255,255,255,.3);}
.text-muted{color:#ccc !important;}

.table.table-dark thead th{color:#fff;border-bottom:1px solid rgba(255,255,255,.1);}
.table.table-dark tbody td{color:#ddd;border-color:rgba(255,255,255,.05) !important;}

#tldChart{max-height:180px !important; filter:drop-shadow(0 0 10px rgba(96,165,250,.4));}

/* announcement style */
.ann-item{padding:.5rem .75rem; border:1px solid rgba(255,255,255,.08); border-radius:10px; margin-bottom:.5rem; background:rgba(0,0,0,0.25);}
</style>
</head>
<body>

<div class="container my-4">
  <div class="mb-2">
    <h2 class="mb-0">📊 Dashboard</h2>
    <p class="text-muted mb-0">Welcome back, <?=htmlspecialchars($user->username ?? 'User')?> — overview of your links & activity.</p>
  </div>

  <!-- Top Stats -->
  <div class="row g-4 mb-4">

    <!-- Package / Kota -->
    <div class="col-md-6 col-xl-3">
      <div class="procard p-3 text-center h-100">
        <h5>📦 Package</h5>
        <?php if($user->kota === 0): ?>
          <p class="stat">Unlimited</p>
          <small class="text-muted">No usage limit.</small>
        <?php else:
          $remain = max(0, $user->kota - $singleLinks); ?>
          <p class="stat"><?=$remain?> / <?=$user->kota?></p>
          <div class="progress"><div class="progress-bar" style="width:<?=$quotaUsedPct?>%"></div></div>
          <small class="text-muted"><?=$quotaUsedPct?>% used (counts single)</small>
        <?php endif; ?>
      </div>
    </div>

    <!-- Your Backlinks -->
    <div class="col-md-6 col-xl-3">
      <div class="procard p-3 text-center h-100">
        <h5>🔗 Your Backlinks</h5>
        <p class="stat"><?=$multiLinks?></p>
        <small class="text-muted">k_multi.tip='1'</small>
      </div>
    </div>

    <!-- Your Anchors -->
    <div class="col-md-6 col-xl-3">
      <div class="procard p-3 text-center h-100">
        <h5>🏷️ Your Anchors</h5>
        <p class="stat"><?=$multiAnchors?></p>
        <small class="text-muted">k_multi.tip='2'</small>
      </div>
    </div>

    <!-- Link Database (user total) -->
    <div class="col-md-6 col-xl-3">
      <div class="procard p-3 text-center h-100">
        <h5>💾 Link Database</h5>
        <p class="stat"><?=$orderCount?></p>
        <small class="text-muted">Total you added (single + multi)</small>
      </div>
    </div>

  </div>

  <!-- General Stats -->
  <div class="card neon p-3 mb-4">
    <h5 class="panel-title mb-1">📈 General Statistics</h5>
    <small>Overview of site & order metrics</small>
    <div class="mt-3 row g-3">
      <div class="col-md-4">
        <div class="p-3" style="border:1px solid rgba(255,255,255,.06);border-radius:12px;">
          <div class="d-flex gap-3 align-items-center">
            <div style="width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-size:18px;background:linear-gradient(135deg,#60a5fa,#a855f7);color:#061025;">🌐</div>
            <div>
              <div class="panel-title">Total Sites</div>
              <div class="h4 mb-0"><?=$total_sites?></div>
              <small class="text-muted">Tracked domains</small>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3" style="border:1px solid rgba(255,255,255,.06);border-radius:12px;">
          <div class="d-flex gap-3 align-items-center">
            <div style="width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-size:18px;background:linear-gradient(135deg,#60a5fa,#a855f7);color:#061025;">💳</div>
            <div>
              <div class="panel-title">Total Expense</div>
              <div class="h4 mb-0"><?=$total_expense?></div>
              <small class="text-muted">Sum of orders</small>
            </div>
          </div>
        </div>
      </div>      
      <div class="col-md-4">
        <div class="p-3" style="border:1px solid rgba(255,255,255,.06);border-radius:12px;">
          <div class="d-flex gap-3 align-items-center">
            <div style="width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-size:18px;background:linear-gradient(135deg,#60a5fa,#a855f7);color:#061025;">🔗</div>
            <div>
              <div class="panel-title">Purchased Links</div>
              <div class="h4 mb-0"><?=$purchased_links?></div>
              <small class="text-muted">Orders count</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts + Lists -->
  <div class="row g-4">
    <!-- TLD Chart -->
    <div class="col-md-6">
      <div class="card neon p-3 h-100">
        <strong class="panel-title">Link Pool by TLD</strong>
        <canvas id="tldChart" height="180"></canvas>
      </div>
    </div>
    <!-- History Chart -->
    <div class="col-md-6">
      <div class="card neon p-3 h-100">
        <strong class="panel-title">Link History (14 days)</strong>
        <canvas id="histChart" height="180"></canvas>
      </div>
    </div>

    <!-- Last 10 Login Records -->
    <div class="col-md-6">
      <div class="card neon p-3 h-100">
        <strong class="panel-title">Last 10 Login Records</strong>
        <table class="table table-sm table-dark mt-2">
          <thead><tr><th>#</th><th>IP</th><th>Country</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($log_rows){ $i=1; foreach($log_rows as $r){ ?>
            <tr>
              <td><?=$i++?></td>
              <td><?=htmlspecialchars($r['ip'] ?? '-')?></td>
              <td><?=htmlspecialchars($r['country'] ?? '-')?></td>
              <td><?=htmlspecialchars($r['created_at'] ?? '-')?></td>
            </tr>
          <?php } } else { ?>
            <tr><td colspan="4">No login records found.</td></tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Announcements -->
    <div class="col-md-6">
      <div class="card neon p-3 h-100">
        <strong class="panel-title">Announcements</strong>
        <div class="mt-2">
          <?php if ($ann_rows){
            foreach($ann_rows as $a){ ?>
              <div class="ann-item">
                <strong><?=htmlspecialchars($a['title'])?></strong><br>
                <small class="text-muted"><?=htmlspecialchars($a['created_at'])?></small>
                <div class="mt-1"><?=nl2br(htmlspecialchars($a['message']))?></div>
              </div>
            <?php }
          } else { ?>
            <div>No announcements</div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include "footer.php"; ?>

<script>
(() => {
  // ----- TLD Donut -----
  const tldLabels = <?=json_encode($tld_labels)?>;
  const tldCounts = <?=json_encode($tld_counts)?>;
  const tldPerc   = <?=json_encode($tld_percentages)?>;
  const tldCtx = document.getElementById('tldChart')?.getContext('2d');
  if (tldCtx && tldLabels.length) {
    const labelText = tldLabels.map(l => {
      const p = (tldPerc[l] !== undefined) ? ` (${tldPerc[l]}%)` : '';
      return l + p;
    });
    new Chart(tldCtx, {
      type: 'doughnut',
      data: {
        labels: labelText,
        datasets: [{
          data: tldCounts,
          backgroundColor: [
            'rgba(239,68,68,.9)','rgba(96,165,250,.9)','rgba(168,85,247,.9)',
            'rgba(16,185,129,.9)','rgba(249,115,22,.9)','rgba(236,72,153,.9)',
            'rgba(99,102,241,.9)','rgba(14,165,233,.9)'
          ],
          hoverBackgroundColor: [
            'rgba(239,68,68,1)','rgba(96,165,250,1)','rgba(168,85,247,1)',
            'rgba(16,185,129,1)','rgba(249,115,22,1)','rgba(236,72,153,1)',
            'rgba(99,102,241,1)','rgba(14,165,233,1)'
          ],
          borderColor:'rgba(255,255,255,0.05)',
          borderWidth:1,
          hoverOffset:6,
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom', labels: { color:'#fff', font:{ size:11 } } },
          tooltip: { callbacks: {
            label: (ctx) => `${ctx.label}: ${ctx.formattedValue} sites`
          } }
        },
        cutout: '80%',
      }
    });
  }

  // ----- 14-day history -----
  const hLabels = <?=json_encode($hist_labels)?>;
  const hCounts = <?=json_encode($hist_counts)?>;
  const hCtx = document.getElementById('histChart')?.getContext('2d');
  if (hCtx) {
    const gradient = hCtx.createLinearGradient(0,0,0,220);
    gradient.addColorStop(0,'rgba(239,68,68,0.9)');
    gradient.addColorStop(1,'rgba(168,85,247,0.25)');
    new Chart(hCtx, {
      type: 'line',
      data: {
        labels: hLabels,
        datasets: [{
          label: 'Purchases',
          data: hCounts,
          fill: true,
          tension: .35,
          borderWidth: 2.5,
          borderColor: 'rgba(239,68,68,0.95)',
          backgroundColor: gradient,
          pointRadius: 3.5,
          pointBackgroundColor: '#ef4444'
        }]
      },
      options: {
        scales: {
          x: { ticks: { color:'#e0e0e0' }, grid: { color:'rgba(148,163,184,0.05)' } },
          y: { ticks: { color:'#e0e0e0' }, grid: { color:'rgba(148,163,184,0.05)' }, beginAtZero:true }
        },
        plugins: { legend: { display:false } },
        animation: { duration: 900, easing: 'easeOutQuart' }
      }
    });
  }
})();
</script>
</body>
</html>
