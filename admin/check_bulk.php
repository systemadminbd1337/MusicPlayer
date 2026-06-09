<?php
// admin/check_bulk.php — AJAX Version
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/check_url.php';
if (empty($_SESSION['bulk_csrf'])) $_SESSION['bulk_csrf'] = bin2hex(random_bytes(16));
function clean($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Bulk URL Checker (Live Progress)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#05060a;--neon1:#00ff9d;--neon2:#00e6ff;--muted:#8b9aa3;}
body{background:radial-gradient(1000px 300px at 10% 10%,rgba(0,230,255,0.03),transparent 5%),
radial-gradient(800px 300px at 90% 90%,rgba(0,255,157,0.02),transparent 5%),var(--bg);
color:#c7f9e8;font-family:'Share Tech Mono',monospace;margin:0;padding:20px;}
.container{max-width:1100px;margin:0 auto;}
.hack-title{font-family:'Orbitron',sans-serif;color:var(--neon2);}
.neon-card{background:rgba(255,255,255,0.02);border-radius:12px;border:1px solid rgba(255,255,255,0.05);
padding:16px;box-shadow:0 0 20px rgba(0,255,157,0.05);}
textarea{background:rgba(0,0,0,0.35);color:#dfffee;border:1px solid rgba(255,255,255,0.04);}
.btn-check{background:linear-gradient(90deg,var(--neon1),var(--neon2));border:none;color:#001214;font-weight:700;}
.progress{height:18px;background:#0b1b25;border:1px solid rgba(0,255,157,0.2);}
.progress-bar{background:linear-gradient(90deg,var(--neon1),var(--neon2));font-size:11px;color:#001;}
.badge-ok{background:#00ff9d;color:#001f14;font-weight:700;padding:4px 6px;border-radius:4px;}
.badge-bad{background:#ff6b6b;color:#2b0000;font-weight:700;padding:4px 6px;border-radius:4px;}
</style>
</head>
<body>
<div class="container">
  <h2 class="hack-title mb-3">Bulk URL Checker — Live Progress</h2>

  <div class="neon-card mb-3">
    <label class="form-label small">Paste URLs (one per line)</label>
    <textarea id="urls" class="form-control" rows="8" placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
    <div class="form-check mt-2">
      <input class="form-check-input" type="checkbox" id="addToMonitors">
      <label class="form-check-label small" for="addToMonitors">Add to monitored list after check</label>
    </div>
    <button class="btn btn-check mt-3" id="startCheck">Start Checking</button>
  </div>

  <div class="progress mb-3 d-none" id="progressWrap">
    <div class="progress-bar" id="progressBar" style="width:0%">0%</div>
  </div>

  <div id="resultsArea" class="neon-card d-none">
    <h5>Results</h5>
    <div class="table-responsive">
      <table class="table table-sm table-dark align-middle">
        <thead><tr><th>#</th><th>URL</th><th>Status</th><th>HTTP</th><th>Snippet</th></tr></thead>
        <tbody id="resultsBody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.getElementById('startCheck').addEventListener('click', async ()=>{
  const textarea = document.getElementById('urls');
  let lines = textarea.value.trim().split(/\r?\n/).filter(Boolean);
  if(!lines.length){ alert('Please enter at least one URL.'); return; }

  const addToMonitors = document.getElementById('addToMonitors').checked;
  const total = lines.length;
  const pbWrap = document.getElementById('progressWrap');
  const pb = document.getElementById('progressBar');
  const resArea = document.getElementById('resultsArea');
  const tbody = document.getElementById('resultsBody');
  pbWrap.classList.remove('d-none');
  resArea.classList.remove('d-none');
  tbody.innerHTML = '';
  pb.style.width = '0%'; pb.textContent='0%';

  for(let i=0;i<total;i++){
    const url = lines[i].trim();
    let row = document.createElement('tr');
    row.innerHTML = `<td>${i+1}</td><td>${url}</td><td colspan="3">⏳ Checking...</td>`;
    tbody.appendChild(row);

    try{
      const formData = new FormData();
      formData.append('url', url);
      formData.append('add', addToMonitors ? '1' : '0');
      const res = await fetch('check_single_ajax.php', { method:'POST', body:formData });
      const data = await res.json();

      let badge = data.status === 'OK'
        ? `<span class='badge-ok'>${data.status}</span>`
        : `<span class='badge-bad'>${data.status}</span>`;
      row.innerHTML = `<td>${i+1}</td>
        <td><a href='${data.url}' target='_blank' style='color:#00e6ff'>${data.url}</a></td>
        <td>${badge}</td>
        <td>${data.http_code||'-'}</td>
        <td><small>${(data.response_snippet||'').slice(0,120)}</small></td>`;
    }catch(err){
      row.innerHTML = `<td>${i+1}</td><td>${url}</td><td colspan="3" style="color:#f66">Error: ${err}</td>`;
    }

    let pct = Math.round(((i+1)/total)*100);
    pb.style.width = pct+'%';
    pb.textContent = pct+'%';
  }
  pb.textContent = 'Done ✅';
});
</script>
</body>
</html>
