<?php
/**
 * Dashboard UI injection (non-destructive).
 * - Does NOT remove or alter existing code/logic
 * - Adds a dark neon dashboard UI with stats, charts, logs, announcements
 * - Safe fallbacks if tables/columns are missing
 *
 * Requirements: a PDO instance ($pdo) or ezSQL-like object ($db)
 */

// Resolve DB adapters
$__pdo = null;
$__db  = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $__pdo = $pdo;
} elseif (isset($db) && is_object($db)) {
    $__db = $db;
}

// Helpers
function _dash_q($sql, $default = null) {
    // Try via $pdo first
    try {
        if (isset($GLOBALS['__pdo']) && $GLOBALS['__pdo'] instanceof PDO) {
            $st = $GLOBALS['__pdo']->query($sql);
            return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : $default;
        }
    } catch (Throwable $e) {}

    // Try via $db (ezSQL)
    try {
        if (isset($GLOBALS['__db']) && is_object($GLOBALS['__db'])) {
            $rows = $GLOBALS['__db']->get_results($sql, ARRAY_A);
            return $rows ?: $default;
        }
    } catch (Throwable $e) {}

    return $default;
}

function _dash_var($sql, $default = 0) {
    $rows = _dash_q($sql, null);
    if (!$rows) return $default;
    $row = current($rows);
    if (!$row) return $default;
    $val = current($row);
    if ($val === null) return $default;
    if (is_numeric($val)) return 0 + $val;
    return $val;
}

// Data fetch with defensive SQL
$total_sites = _dash_var("SELECT COUNT(*) FROM k_sites", 0);

// Purchased links (orders count). Try k_orders first, fallback k_siparis
$purchased_links = _dash_var("SELECT COUNT(*) FROM k_orders", null);
if ($purchased_links === null) {
    $purchased_links = _dash_var("SELECT COUNT(*) FROM k_siparis", 0);
}

// Total expense: attempt a few common schemas
$expense = null;
if ($expense === null) $expense = _dash_var("SELECT COALESCE(SUM(amount),0) FROM k_orders", null);
if ($expense === null) $expense = _dash_var("SELECT COALESCE(SUM(price),0) FROM k_orders", null);
if ($expense === null) $expense = _dash_var("SELECT COALESCE(SUM(total),0) FROM k_orders", null);
if ($expense === null) $expense = _dash_var("SELECT COALESCE(SUM(kredi),0) FROM k_orders", null);
if ($expense === null) $expense = 0;

// TLD breakdown: prefer explicit tld column, else parse from domain
$tld_rows = _dash_q("SELECT tld AS keyy, COUNT(*) AS c FROM k_sites GROUP BY tld ORDER BY c DESC", null);
if ($tld_rows === null) {
    $tld_rows = _dash_q("
        SELECT
            LOWER(SUBSTRING_INDEX(domain, '.', -1)) AS keyy,
            COUNT(*) AS c
        FROM k_sites
        GROUP BY LOWER(SUBSTRING_INDEX(domain, '.', -1))
        ORDER BY c DESC
    ", []);
}

// Last 14 days link history (orders by date)
$link_history_rows = _dash_q("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM k_orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
", null);
if ($link_history_rows === null) {
    $link_history_rows = []; // fallback
}

// Last 10 login records: prefer k_logs (ip, location/country, created_at)
$log_rows = _dash_q("
    SELECT ip, IFNULL(location, country) AS country, created_at
    FROM k_logs
    ORDER BY created_at DESC
    LIMIT 10
", null);
if ($log_rows === null) {
    // fallback from k_users
    $log_rows = _dash_q("
        SELECT last_login_ip AS ip, last_login_country AS country, last_login_time AS created_at
        FROM k_users
        WHERE last_login_ip IS NOT NULL
        ORDER BY last_login_time DESC
        LIMIT 10
    ", []);
}

// Announcements
$ann_rows = _dash_q("
    SELECT id, title, message, author, created_at
    FROM k_announcements
    WHERE COALESCE(visible,1)=1
    ORDER BY created_at DESC
    LIMIT 10
", []);

// Prepare PHP->JS payloads
$tld_labels = [];
$tld_counts = [];
foreach ($tld_rows as $r) {
    $k = isset($r['keyy']) ? $r['keyy'] : (isset($r['tld']) ? $r['tld'] : 'other');
    $c = isset($r['c']) ? (int)$r['c'] : 0;
    if ($k === '' || $k === null) $k = 'other';
    $tld_labels[] = $k;
    $tld_counts[] = $c;
}

$hist_labels = [];
$hist_counts = [];
// build a continuous 14-day series
try {
    $map = [];
    foreach ($link_history_rows as $r) { $map[$r['d']] = (int)$r['c']; }
    $start = new DateTime('-13 days');
    for ($i=0; $i<14; $i++) {
        $day = clone $start; $day->modify("+{$i} day");
        $k = $day->format('Y-m-d');
        $hist_labels[] = $k;
        $hist_counts[] = isset($map[$k]) ? $map[$k] : 0;
    }
} catch (Throwable $e) {}

?>
<!-- ======== DASHBOARD INJECT: START ======== -->
<style>
:root{
  --bg:#0b0d21;
  --panel:#111432;
  --panel-2:#0f1230;
  --text:#e5e7eb;
  --muted:#9ca3af;
  --neon:#60a5fa;
  --neon-2:#a855f7;
  --card-grad: linear-gradient(90deg, rgba(168,85,247,.12), rgba(96,165,250,.12));
}
.dash-wrap{
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  padding: 24px;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, "Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol";
}
.dash-grid{
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 16px;
}
.card{
  background: var(--panel);
  border-radius: 16px;
  box-shadow: 0 10px 25px rgba(0,0,0,.35), 0 0 0 1px rgba(96,165,250,.15) inset;
  position: relative;
  overflow: hidden;
}
.card::before{
  content: "";
  position:absolute; inset:0;
  background: var(--card-grad);
  opacity:.6; pointer-events:none;
}
.card-header{
  padding: 16px 18px 0 18px;
  display:flex; align-items:center; justify-content:space-between;
}
.card-title{
  font-weight:700; letter-spacing:.3px; font-size: 16px;
}
.card-body{ padding: 16px 18px 18px 18px; }
.stat-cards{ grid-column: span 12; display:grid; gap:16px; grid-template-columns: repeat(12, 1fr); }
.stat-card{ grid-column: span 4; display:flex; align-items:center; gap:12px; padding:16px; background: var(--panel-2); border-radius: 14px; box-shadow: 0 0 24px rgba(96,165,250,.15) inset; }
.stat-icon{ width:42px; height:42px; border-radius: 10px; background: radial-gradient(circle at 30% 30%, rgba(96,165,250,.35), rgba(168,85,247,.15)); display:grid; place-items:center; font-size:18px; }
.stat-value{ font-size: 24px; font-weight:800; }
.stat-label{ color: var(--muted); font-size:12px; text-transform: uppercase; letter-spacing:.8px;}
.section{ grid-column: span 12; }
.section.half{ grid-column: span 6; }
.table{
  width:100%; border-collapse:separate; border-spacing:0 8px; font-size:14px;
}
.table thead th{ text-align:left; color:var(--muted); font-weight:600; padding:8px 12px; }
.table tbody tr{ background:#0f1336; }
.table tbody td{ padding:10px 12px; border-top:1px solid rgba(255,255,255,.06); border-bottom:1px solid rgba(255,255,255,.06); }
.table tbody tr td:first-child{ border-left:1px solid rgba(255,255,255,.06); border-top-left-radius:10px; border-bottom-left-radius:10px; }
.table tbody tr td:last-child{ border-right:1px solid rgba(255,255,255,.06); border-top-right-radius:10px; border-bottom-right-radius:10px; }
.ann-list{ max-height: 320px; overflow:auto; display:flex; flex-direction:column; gap:10px; }
.ann-item{ background:#0f1336; padding:12px; border-radius:12px; }
.ann-title{ font-weight:700; margin-bottom:4px; }
.ann-meta{ font-size:12px; color:var(--muted); }
.hide-original{
  /* Keep original content in DOM but visually hide to prioritize the dashboard look */
  position:absolute !important; width:1px !important; height:1px !important; overflow:hidden !important;
  clip: rect(1px,1px,1px,1px) !important; white-space:nowrap !important; border:0 !important; padding:0 !important; margin:-1px !important;
}
@media (max-width: 1024px){
  .section.half{ grid-column: span 12; }
  .stat-card{ grid-column: span 12; }
}
</style>

<!-- Optionally hide original index markup to avoid visual duplication without deleting anything -->
<script>
try{
  // Comment the next line if you want to keep the original visible
  document.addEventListener('DOMContentLoaded', function(){
    var bodyChildren=[].slice.call(document.body.children||[]);
    for (var i=0;i<bodyChildren.length;i++){
      var el=bodyChildren[i];
      if(!el.matches('.dash-wrap')) el.classList.add('hide-original');
    }
  });
}catch(e){}
</script>

<link rel="preconnect" href="https://cdn.jsdelivr.net" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dash-wrap">
  <div class="dash-grid">
    <!-- STAT CARDS -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-icon">🌐</div>
        <div>
          <div class="stat-value"><?=(int)$total_sites?></div>
          <div class="stat-label">Total Sites</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💳</div>
        <div>
          <div class="stat-value"><?=(0+$expense)?></div>
          <div class="stat-label">Total Expense (Kredi)</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🔗</div>
        <div>
          <div class="stat-value"><?=(int)$purchased_links?></div>
          <div class="stat-label">Purchased Links</div>
        </div>
      </div>
    </div>

    <!-- LINK POOL (TLD) -->
    <div class="section half card">
      <div class="card-header"><div class="card-title">Link Pool by TLD</div></div>
      <div class="card-body">
        <canvas id="tldChart" height="220"></canvas>
      </div>
    </div>

    <!-- LINK HISTORY -->
    <div class="section half card">
      <div class="card-header"><div class="card-title">Link History (14 days)</div></div>
      <div class="card-body">
        <canvas id="histChart" height="220"></canvas>
      </div>
    </div>

    <!-- LOGIN TABLE -->
    <div class="section half card">
      <div class="card-header"><div class="card-title">Last 10 Login Records</div></div>
      <div class="card-body">
        <table class="table">
          <thead><tr><th>#</th><th>IP</th><th>Country</th><th>Date</th></tr></thead>
          <tbody>
          <?php $i=1; foreach($log_rows as $r){ ?>
            <tr>
              <td><?=$i++?></td>
              <td><?=htmlspecialchars($r['ip']??'-')?></td>
              <td><?=htmlspecialchars($r['country']??'-')?></td>
              <td><?=htmlspecialchars($r['created_at']??'-')?></td>
            </tr>
          <?php } if(empty($log_rows)){ ?>
            <tr><td colspan="4">No login records found.</td></tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ANNOUNCEMENTS -->
    <div class="section half card">
      <div class="card-header"><div class="card-title">Announcements</div></div>
      <div class="card-body">
        <div class="ann-list">
          <?php foreach($ann_rows as $a){ ?>
            <div class="ann-item">
              <div class="ann-title"><?=htmlspecialchars($a['title'])?></div>
              <div class="ann-meta">
                By <?=htmlspecialchars($a['author']??'system')?> • <?=htmlspecialchars($a['created_at']??'')?>
              </div>
              <div class="ann-msg"><?=nl2br(htmlspecialchars($a['message']??''))?></div>
            </div>
          <?php } if(empty($ann_rows)){ ?>
            <div class="ann-item">
              <div class="ann-title">No announcements</div>
              <div class="ann-meta">—</div>
              <div class="ann-msg">Announcements will appear here.</div>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  var tldLabels = <?php echo json_encode($tld_labels); ?>;
  var tldCounts = <?php echo json_encode($tld_counts); ?>;
  var ctx1 = document.getElementById('tldChart').getContext('2d');
  try{
    new Chart(ctx1, {
      type: 'doughnut',
      data: {
        labels: tldLabels,
        datasets: [{ data: tldCounts }]
      },
      options: {
        plugins:{ legend:{ labels:{ color:'#cbd5e1' } } },
        cutout: '62%'
      }
    });
  }catch(e){}

  var histLabels = <?php echo json_encode($hist_labels); ?>;
  var histCounts = <?php echo json_encode($hist_counts); ?>;
  var ctx2 = document.getElementById('histChart').getContext('2d');
  try{
    new Chart(ctx2, {
      type: 'line',
      data: {
        labels: histLabels,
        datasets: [{ data: histCounts, tension: .35, fill: true }]
      },
      options: {
        scales:{
          x:{ ticks:{ color:'#94a3b8' }, grid:{ color:'rgba(148,163,184,.15)' } },
          y:{ ticks:{ color:'#94a3b8' }, grid:{ color:'rgba(148,163,184,.15)' } }
        },
        plugins:{ legend:{ display:false } }
      }
    });
  }catch(e){}
})();
</script>
<!-- ======== DASHBOARD INJECT: END ======== -->
