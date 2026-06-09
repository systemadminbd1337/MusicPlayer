<?php
include __DIR__ . "/_bootstrap.php";

// === KPI Counters ===
$total_users   = (int) $db->get_var("SELECT COUNT(*) FROM k_users WHERE deleted_at IS NULL");
$banned_users  = (int) $db->get_var("SELECT COUNT(*) FROM k_users WHERE is_banned=1 AND deleted_at IS NULL");
$total_credit  = (int) $db->get_var("SELECT COALESCE(SUM(kredi),0) FROM k_users WHERE deleted_at IS NULL");
$total_ann     = (int) $db->get_var("SELECT COUNT(*) FROM k_announcements");
$broken_links  = (int) $db->get_var("SELECT COUNT(*) FROM k_broken_links WHERE status='broken'");
$defunct_sites = (int) $db->get_var("SELECT COUNT(*) FROM k_broken_links WHERE status='defunct'");

// === Chart Data ===
$status_data = $db->get_results("SELECT last_status, COUNT(*) AS cnt FROM monitored_urls GROUP BY last_status", ARRAY_A);
$chart_labels = []; $chart_counts = [];
foreach($status_data as $row){ $chart_labels[] = $row['last_status']; $chart_counts[] = (int)$row['cnt']; }

$daily = $db->get_results("
    SELECT DATE(checked_at) as d, COUNT(*) as c
    FROM url_checks
    WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(checked_at)
    ORDER BY d ASC
", ARRAY_A);
$chart_days = []; $chart_day_count = [];
foreach($daily as $d){ $chart_days[] = $d['d']; $chart_day_count[] = (int)$d['c']; }

// === Live Alerts ===
$alerts = $db->get_results("
  SELECT id, url, last_status, last_http_code, last_checked
  FROM monitored_urls
  WHERE last_status IN ('NOT_FOUND','GONE','ERROR','SERVER_ERROR','NOT_FOUND_CUSTOM')
  ORDER BY last_checked DESC
  LIMIT 10
", ARRAY_A);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin • BlackHat HackLink</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-dark: #000000;
  --bg-panel: #0a0a0a;
  --bg-card: #111111;
  --neon-green: #00ff41;
  --neon-cyan: #00ffff;
  --neon-purple: #ff00ff;
  --neon-red: #ff003c;
  --neon-orange: #ff6b00;
  --text-primary: #00ff41;
  --text-secondary: #00ffff;
  --text-muted: #00ff88;
  --border-glow: rgba(0, 255, 65, 0.3);
}
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
body{
  background: 
    radial-gradient(ellipse at 20% 20%, rgba(0, 255, 65, 0.03) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 80%, rgba(0, 255, 255, 0.02) 0%, transparent 50%),
    linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
  color: var(--text-primary);
  font-family: 'JetBrains Mono', monospace;
  min-height: 100vh;
  overflow-x: hidden;
  position: relative;
}
body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: 
    repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(0, 255, 65, 0.02) 2px,
      rgba(0, 255, 65, 0.02) 4px
    );
  pointer-events: none;
  z-index: -1;
}
.container{
  padding: 20px;
  max-width: 1400px;
  position: relative;
  z-index: 1;
}

/* 🔥 Header Styles */
.header{
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding: 20px;
  background: rgba(10, 10, 10, 0.8);
  border: 1px solid var(--border-glow);
  border-radius: 12px;
  box-shadow: 
    0 0 30px rgba(0, 255, 65, 0.1),
    inset 0 0 20px rgba(0, 255, 65, 0.05);
  backdrop-filter: blur(10px);
  position: relative;
  overflow: hidden;
}
.header::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--neon-green), transparent);
  animation: scanline 3s linear infinite;
}
@keyframes scanline {
  0% { left: -100%; }
  100% { left: 100%; }
}
.h-title{
  font-family: 'Orbitron', sans-serif;
  font-size: 28px;
  font-weight: 700;
  background: linear-gradient(135deg, var(--neon-green), var(--neon-cyan));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  text-shadow: 0 0 20px rgba(0, 255, 65, 0.5);
  letter-spacing: 1px;
}
.h-subtitle{
  font-size: 14px;
  color: var(--text-muted);
  margin-top: 5px;
  font-weight: 300;
}

/* 🔥 Navigation */
.navx{
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.navx a{
  padding: 8px 16px;
  border-radius: 6px;
  color: var(--text-secondary);
  text-decoration: none;
  background: rgba(0, 255, 65, 0.05);
  border: 1px solid rgba(0, 255, 65, 0.2);
  font-size: 12px;
  font-weight: 500;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}
.navx a::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(0, 255, 65, 0.2), transparent);
  transition: left 0.5s;
}
.navx a:hover::before {
  left: 100%;
}
.navx a:hover{
  background: rgba(0, 255, 65, 0.1);
  border-color: var(--neon-green);
  box-shadow: 0 0 15px rgba(0, 255, 65, 0.3);
  transform: translateY(-2px);
}

/* 🔥 KPI Grid */
.grid{
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}
.cardx{
  background: linear-gradient(135deg, var(--bg-card), #1a1a1a);
  border-radius: 12px;
  padding: 24px;
  border: 1px solid var(--border-glow);
  box-shadow: 
    0 0 20px rgba(0, 255, 65, 0.1),
    inset 0 0 20px rgba(0, 255, 65, 0.05);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}
.cardx::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, var(--neon-green), var(--neon-cyan), var(--neon-green));
  opacity: 0;
  transition: opacity 0.3s;
}
.cardx:hover::before {
  opacity: 1;
}
.cardx:hover{
  transform: translateY(-5px);
  box-shadow: 
    0 10px 30px rgba(0, 255, 65, 0.2),
    inset 0 0 30px rgba(0, 255, 65, 0.1);
  border-color: var(--neon-green);
}
.kpi{
  font-size: 36px;
  font-weight: 700;
  margin: 10px 0 5px;
  background: linear-gradient(135deg, var(--neon-green), var(--neon-cyan));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  text-shadow: 0 0 20px rgba(0, 255, 65, 0.3);
}
.small{
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* 🔥 Chart Containers */
.chart-container{
  background: linear-gradient(135deg, var(--bg-card), #1a1a1a);
  border-radius: 12px;
  padding: 24px;
  border: 1px solid var(--border-glow);
  box-shadow: 
    0 0 20px rgba(0, 255, 65, 0.1),
    inset 0 0 20px rgba(0, 255, 65, 0.05);
  margin-bottom: 30px;
  position: relative;
  overflow: hidden;
}
.chart-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, var(--neon-green), var(--neon-cyan));
}
.chart-title{
  font-family: 'Orbitron', sans-serif;
  font-size: 16px;
  font-weight: 600;
  color: var(--neon-cyan);
  margin-bottom: 20px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* 🔥 Alert Box */
.alert-box{
  background: linear-gradient(135deg, #1a0000, #0a0a0a);
  border-radius: 12px;
  padding: 24px;
  border: 1px solid rgba(255, 0, 60, 0.3);
  box-shadow: 
    0 0 20px rgba(255, 0, 60, 0.1),
    inset 0 0 20px rgba(255, 0, 60, 0.05);
  margin-bottom: 30px;
}
.alert-title{
  font-family: 'Orbitron', sans-serif;
  font-size: 16px;
  font-weight: 600;
  color: var(--neon-red);
  margin-bottom: 16px;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.alert-row{
  padding: 12px 0;
  border-bottom: 1px solid rgba(255, 0, 60, 0.2);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.3s ease;
}
.alert-row:last-child{
  border-bottom: none;
}
.alert-row:hover{
  background: rgba(255, 0, 60, 0.05);
  padding-left: 10px;
  padding-right: 10px;
  margin: 0 -10px;
  border-radius: 6px;
}
.alert-status{
  padding: 4px 12px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  background: var(--neon-red);
  color: black;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.alert-url{
  flex: 1;
  color: var(--neon-cyan);
  text-decoration: none;
  font-size: 13px;
  font-family: 'JetBrains Mono', monospace;
}
.alert-url:hover{
  color: var(--neon-green);
  text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
}
.alert-meta{
  color: var(--text-muted);
  font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
}

/* 🔥 Footer */
.footer-note{
  text-align: center;
  margin-top: 30px;
  color: var(--text-muted);
  font-size: 12px;
  padding: 15px;
  border-top: 1px solid var(--border-glow);
}

/* 🔥 Compact Chart */
.chart-compact{
  max-width: 280px;
  margin: 0 auto;
}

/* 🔥 Form Elements */
.form-control{
  background: rgba(0, 0, 0, 0.5);
  border: 1px solid var(--border-glow);
  color: var(--neon-green);
  font-family: 'JetBrains Mono', monospace;
}
.form-control:focus{
  background: rgba(0, 0, 0, 0.7);
  border-color: var(--neon-green);
  box-shadow: 0 0 15px rgba(0, 255, 65, 0.3);
  color: var(--neon-green);
}
.form-check-input:checked{
  background-color: var(--neon-green);
  border-color: var(--neon-green);
}
.btn-primary{
  background: linear-gradient(135deg, var(--neon-green), var(--neon-cyan));
  border: none;
  color: black;
  font-weight: 600;
  font-family: 'Orbitron', sans-serif;
}
.btn-primary:hover{
  background: linear-gradient(135deg, var(--neon-cyan), var(--neon-green));
  box-shadow: 0 0 20px rgba(0, 255, 65, 0.5);
}

/* 🔥 Progress Bar */
.progress{
  background: rgba(0, 0, 0, 0.5);
  border: 1px solid var(--border-glow);
  height: 8px;
}
.progress-bar{
  background: linear-gradient(90deg, var(--neon-green), var(--neon-cyan));
}

/* 🔥 Table Styles */
.table{
  color: var(--neon-green);
  border-color: var(--border-glow);
}
.table thead th{
  border-bottom: 2px solid var(--border-glow);
  color: var(--neon-cyan);
  font-weight: 600;
}
.table tbody tr:hover{
  background: rgba(0, 255, 65, 0.05);
}

/* 🔥 Badge Styles */
.badge{
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
}
</style>
</head>
<body>
<div class="container">
  <!-- 🔥 HEADER -->
  <div class="header">
    <div>
      <div class="h-title">HACKLINK ADMIN</div>
      <div class="h-subtitle">[ROOT ACCESS GRANTED] Welcome, <?= htmlspecialchars($user->username ?? 'admin') ?> — SYSTEM ONLINE</div>
    </div>
    <div class="navx">
      <a href="index.php">🏠 HOME</a>
      <a href="users.php">👥 USERS</a>
      <a href="announcements.php">📢 ANNOUNCE</a>
      <a href="broken_links.php">🔗 BROKEN</a>
      <a href="login_logs.php">📊 LOGS</a>
      <a href="user_login_logs.php">👤 USER LOGS</a>
      <a href="monitors.php">🌐 MONITOR</a>
      <a href="deleted_sites.php">💀 REMOVED</a>
      <a href="../index.php">🚪 FRONT</a>
      <a href="add_link.php">➕ ADD LINK</a>
      <a href="auto_jobs.php">⚙️ AUTO JOBS</a>
      <a href="placements.php">📍 PLACEMENTS</a>
      <a href="payments.php">💳 PAYMENTS</a>
      <a href="actions.php">🎯 ACTIONS</a>
      <a href="logout.php" style="background:rgba(255,0,60,0.1);color:var(--neon-red);border-color:var(--neon-red);">🚪 LOGOUT</a>
    </div>
  </div>

  <!-- 🔥 KPI GRID -->
  <div class="grid">
    <div class="cardx">
      <div class="small">ACTIVE USERS</div>
      <div class="kpi"><?= $total_users ?></div>
    </div>
    <div class="cardx">
      <div class="small">BANNED USERS</div>
      <div class="kpi" style="background:linear-gradient(135deg, var(--neon-red), var(--neon-orange));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <?= $banned_users ?>
      </div>
    </div>
    <div class="cardx">
      <div class="small">TOTAL CREDIT</div>
      <div class="kpi" style="background:linear-gradient(135deg, var(--neon-cyan), var(--neon-green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <?= number_format($total_credit) ?>
      </div>
    </div>
    <div class="cardx">
      <div class="small">ANNOUNCEMENTS</div>
      <div class="kpi"><?= $total_ann ?></div>
    </div>
    <div class="cardx">
      <div class="small">BROKEN LINKS</div>
      <div class="kpi" style="background:linear-gradient(135deg, var(--neon-orange), var(--neon-red));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <?= $broken_links ?>
      </div>
    </div>
    <div class="cardx">
      <div class="small">DEFUNCT SITES</div>
      <div class="kpi" style="background:linear-gradient(135deg, var(--neon-red), #ff0000);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <?= $defunct_sites ?>
      </div>
    </div>
  </div>

  <!-- 🔥 CHARTS -->
  <div class="chart-container">
    <div class="row align-items-center">
      <div class="col-md-4">
        <div class="chart-compact">
          <div class="chart-title">SYSTEM STATUS</div>
          <canvas id="statusChart" height="250"></canvas>
        </div>
      </div>
      <div class="col-md-8">
        <div class="chart-title">MONITORING ACTIVITY (14 DAYS)</div>
        <canvas id="dailyChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <!-- 🔥 LIVE ALERTS -->
  <div class="alert-box">
    <div class="alert-title">🚨 CRITICAL ALERTS — LINK NOT WORKING</div>
    <?php if(!$alerts): ?>
      <div style="text-align:center;padding:20px;color:var(--text-muted);">
        ✅ SYSTEM SECURE — NO THREATS DETECTED
      </div>
    <?php else: foreach($alerts as $a): ?>
      <div class="alert-row">
        <span class="alert-status">🚨 <?= htmlspecialchars($a['last_status']) ?></span>
        <a href="<?= htmlspecialchars($a['url']) ?>" target="_blank" class="alert-url">
          <?= htmlspecialchars($a['url']) ?>
        </a>
        <span class="alert-meta">
          HTTP <?= (int)$a['last_http_code'] ?> • <?= htmlspecialchars($a['last_checked']) ?>
        </span>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- 🔥 BULK URL CHECKER -->
  <div class="chart-container">
    <div class="chart-title">⚡ BULK URL SCANNER</div>
    <div class="row">
      <div class="col-md-8">
        <textarea id="urls" class="form-control" rows="5" placeholder="https://target.com/page1&#10;https://target.com/page2" 
                  style="font-family: 'JetBrains Mono', monospace;"></textarea>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="addToMonitors">
          <label class="form-check-label" style="color:var(--text-muted);font-size:12px;">ADD TO MONITORING SYSTEM</label>
        </div>
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary w-100 mb-2" id="startCheck" style="padding:12px;">INITIATE SCAN</button>
        <div class="progress mb-3 d-none" id="progressWrap">
          <div class="progress-bar" id="progressBar"></div>
        </div>
      </div>
    </div>
    
    <div id="resultsArea" class="mt-3 d-none">
      <div class="chart-title">SCAN RESULTS</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>TARGET URL</th>
              <th>STATUS</th>
              <th>HTTP</th>
              <th>RESPONSE</th>
            </tr>
          </thead>
          <tbody id="resultsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="footer-note">
    [SYSTEM] Monitoring active. Use terminal commands for advanced operations.
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// 🔥 Compact Donut chart
new Chart(document.getElementById('statusChart'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode($chart_labels) ?>,
    datasets:[{
      data:<?= json_encode($chart_counts) ?>,
      backgroundColor:['#00ff41','#00ffff','#ff6b00','#ff003c','#ff00ff','#8b5cf6'],
      borderWidth:0,
      cutout:'75%'
    }]
  },
  options:{
    plugins:{
      legend:{
        position:'bottom',
        labels:{
          color:'#00ffff',
          font:{size:10, family: 'JetBrains Mono'},
          padding:10
        }
      }
    },
    layout:{ padding:5 }
  }
});

// 🔥 Line chart
new Chart(document.getElementById('dailyChart'),{
  type:'line',
  data:{
    labels:<?= json_encode($chart_days) ?>,
    datasets:[{
      label:'SCAN ACTIVITY',
      data:<?= json_encode($chart_day_count) ?>,
      borderColor:'#00ff41',
      backgroundColor:'rgba(0, 255, 65, 0.1)',
      fill:true,
      tension:0.4,
      borderWidth:3,
      pointRadius:4,
      pointBackgroundColor:'#00ff41',
      pointBorderColor:'#000000',
      pointBorderWidth:2
    }]
  },
  options:{
    scales:{
      x:{
        grid:{color:'rgba(0, 255, 65, 0.1)', borderColor:'#00ff41'},
        ticks:{color:'#00ff88', font:{family: 'JetBrains Mono', size:10}}
      },
      y:{
        grid:{color:'rgba(0, 255, 65, 0.1)', borderColor:'#00ff41'},
        ticks:{color:'#00ff88', font:{family: 'JetBrains Mono', size:10}}
      }
    },
    plugins:{
      legend:{
        labels:{
          color:'#00ffff',
          font:{family: 'JetBrains Mono', size:11}
        }
      }
    }
  }
});

// 🔥 Bulk scanner JS
document.getElementById('startCheck').addEventListener('click', async ()=>{
  const lines=document.getElementById('urls').value.trim().split(/\r?\n/).filter(Boolean);
  if(!lines.length){alert('ENTER TARGET URLs');return;}
  const add=document.getElementById('addToMonitors').checked;
  const total=lines.length;
  const wrap=document.getElementById('progressWrap'),bar=document.getElementById('progressBar');
  const area=document.getElementById('resultsArea'),tbody=document.getElementById('resultsBody');
  wrap.classList.remove('d-none');area.classList.remove('d-none');tbody.innerHTML='';
  bar.style.width='0%';
  
  for(let i=0;i<total;i++){
    const url=lines[i];
    let row=document.createElement('tr');
    row.innerHTML=`<td>${i+1}</td><td>${url}</td><td colspan="3">🔄 SCANNING...</td>`;
    tbody.appendChild(row);
    
    try{
      const fd=new FormData();fd.append('url',url);fd.append('add',add?'1':'0');
      const res=await fetch('check_single_ajax.php',{method:'POST',body:fd});
      const d=await res.json();
      let badge=d.status==='OK'?
        `<span style="background:#00ff41;color:black;padding:3px 8px;border-radius:4px;font-size:11px;font-family: JetBrains Mono;">SECURE</span>`:
        `<span style="background:#ff003c;color:black;padding:3px 8px;border-radius:4px;font-size:11px;font-family: JetBrains Mono;">THREAT</span>`;
      row.innerHTML=`<td>${i+1}</td>
        <td><a href='${d.url}' target='_blank' style='color:#00ffff;font-size:12px;font-family: JetBrains Mono;'>${d.url}</a></td>
        <td>${badge}</td>
        <td style="font-family: JetBrains Mono;">${d.http_code||'-'}</td>
        <td><small style="color:#00ff88;font-family: JetBrains Mono;">${(d.response_snippet||'').slice(0,80)}</small></td>`;
    }catch(e){
      row.innerHTML=`<td>${i+1}</td><td>${url}</td><td colspan='3' style='color:#ff003c;font-size:12px;font-family: JetBrains Mono;'>ERROR: ${e}</td>`;
    }
    
    let pct=Math.round(((i+1)/total)*100);
    bar.style.width=pct+'%';
    window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});
  }
});
</script>
</body>
</html>