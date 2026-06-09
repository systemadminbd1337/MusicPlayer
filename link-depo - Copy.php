<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// link-depo.php
include "header.php";
if (empty($_SESSION['user'])) { redirect("login.php"); exit(); }
$user = (object) $_SESSION['user'];
$uid  = (int)$user->id;

// ---------- helpers ----------
function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dbx($v){ global $db; return (isset($db) && method_exists($db,'escape')) ? $db->escape($v) : addslashes($v); }
function has_col($table, $col){
  static $cache = [];
  $key = $table.'|'.$col;
  if(isset($cache[$key])) return $cache[$key];
  global $db;
  try{
    $exists = (int)$db->get_var("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".dbx($table)."' AND column_name='".dbx($col)."'");
    return $cache[$key] = ($exists>0);
  }catch(Throwable $e){ return $cache[$key] = false; }
}

// ---------- USER CREDIT ----------
$credit = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");

// ---------- Ensure optional columns on k_orders (safe idempotent) ----------
try {
  if (!has_col('k_orders','target_url')) {
    $db->query("ALTER TABLE k_orders ADD COLUMN target_url VARCHAR(255) NULL AFTER lid");
  }
  if (!has_col('k_orders','anchor')) {
    $db->query("ALTER TABLE k_orders ADD COLUMN anchor VARCHAR(255) NULL AFTER target_url");
  }
  if (!has_col('k_orders','duration_months')) {
    $db->query("ALTER TABLE k_orders ADD COLUMN duration_months INT DEFAULT 1 AFTER anchor");
  }
  if (!has_col('k_orders','created_at')) {
    // কিছু ইন্সটলে tarih আছে; না থাকলে created_at রাখি
    $db->query("ALTER TABLE k_orders ADD COLUMN created_at DATETIME NULL AFTER duration_months");
    $db->query("UPDATE k_orders SET created_at = COALESCE(created_at, tarih)");
  }
} catch(Throwable $e){ /* ignore */ }

// ---------- DA/PA API Placeholder (optional) ----------
/**
 * fetch_da_pa($domain): returns ['da'=>..., 'pa'=>...]
 * NOTE: API KEY/SECRET/ENDPOINT ইচ্ছাকৃতভাবে ফাঁকা রেখেছি — পরে বসিয়ে দেবে।
 * কনফিগ না থাকলে k_linkdb-এর alexa2=DA, alexa1=PA fallback হিসেবে ব্যবহার করবে।
 */
function fetch_da_pa($domain, $fallbackDA, $fallbackPA){
  $API_ENDPOINT = ''; // <- তোমার endpoint
  $API_KEY      = ''; // <- তোমার API key
  $API_SECRET   = ''; // <- তোমার API secret

  if ($API_ENDPOINT==='' || $API_KEY==='' || $API_SECRET==='') {
    return ['da'=>$fallbackDA, 'pa'=>$fallbackPA, 'source'=>'fallback'];
  }

  // যদি তুমি সত্যি কল করতে চাও, এখানে cURL যোগ করো:
  /*
  $ch = curl_init($API_ENDPOINT);
  $payload = json_encode(['domain'=>$domain]);
  curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'X-API-KEY: '.$API_KEY,
      'X-API-SECRET: '.$API_SECRET
    ],
    CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_TIMEOUT=>10
  ]);
  $res = curl_exec($ch);
  if ($res===false){ curl_close($ch); return ['da'=>$fallbackDA,'pa'=>$fallbackPA,'source'=>'fallback']; }
  curl_close($ch);
  $json = json_decode($res,true);
  if (!is_array($json) || !isset($json['da'],$json['pa'])) return ['da'=>$fallbackDA,'pa'=>$fallbackPA,'source'=>'fallback'];
  return ['da'=>$json['da'], 'pa'=>$json['pa'], 'source'=>'api'];
  */
  return ['da'=>$fallbackDA, 'pa'=>$fallbackPA, 'source'=>'fallback'];
}

// ---------- Build SELECT columns (optional columns safe) ----------
$cols = "id, domain, link, tip, durum, ups, alexa1, alexa2";
$has_year  = has_col('k_linkdb','domain_year');
$has_sure  = has_col('k_linkdb','sure');
$has_added = has_col('k_linkdb','created_at');

if ($has_year)  $cols .= ", domain_year";
if ($has_sure)  $cols .= ", sure";
if ($has_added) $cols .= ", created_at";

// ---------- Stats ----------
$totalLinks  = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb");
$totalCredit = (int)$db->get_var("SELECT COALESCE(SUM(ups),0) FROM k_linkdb");
$phpCount    = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE tip=1");
$jsCount     = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE tip=2");

// ---------- Rows ----------
$rows = $db->get_results("SELECT {$cols} FROM k_linkdb ORDER BY id DESC LIMIT 300");

// ---------- Already purchased by this user ----------
$purchased_ids = $db->get_col("SELECT l.id
                               FROM k_orders o
                               LEFT JOIN k_linkdb l ON l.id=o.lid
                               WHERE o.uid='{$uid}'");
$purchased_set = [];
if ($purchased_ids) foreach($purchased_ids as $lid) $purchased_set[(int)$lid] = true;

// ---------- AJAX endpoints ----------
if (!empty($_GET['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');

  // Buy single (cost=1)
  if ($_GET['ajax']==='buy' && $_SERVER['REQUEST_METHOD']==='POST') {
    $lid = (int)($_POST['lid'] ?? 0);
    if ($lid <= 0) { echo json_encode(['ok'=>0,'msg'=>'Invalid link id']); exit; }

    $already = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND lid='{$lid}'");
    if ($already > 0) { echo json_encode(['ok'=>0,'msg'=>'Already purchased']); exit; }

    $exist = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE id='{$lid}'");
    if (!$exist) { echo json_encode(['ok'=>0,'msg'=>'Link not found']); exit; }

    $bal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
    $cost = 1;
    if ($bal < $cost) { echo json_encode(['ok'=>0,'msg'=>'Insufficient credits']); exit; }

    $db->query("INSERT INTO k_orders (uid,lid,duration_months,created_at,tarih) VALUES ('{$uid}','{$lid}',1,NOW(),NOW())");
    $db->query("UPDATE k_users SET kredi = kredi - {$cost} WHERE id='{$uid}'");
    $newBal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
    echo json_encode(['ok'=>1,'msg'=>'Purchased successfully','lid'=>$lid,'new_balance'=>$newBal]);
    exit;
  }

  // Bulk Add Links (with form fields)
  if ($_GET['ajax']==='bulk_add_links' && $_SERVER['REQUEST_METHOD']==='POST') {
    $ids      = $_POST['ids'] ?? [];
    $target   = trim($_POST['target_url'] ?? '');
    $keyword  = trim($_POST['anchor'] ?? '');
    $duration = (int)($_POST['duration'] ?? 1);

    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_map('intval',$ids)));

    if ($target==='' || $keyword==='') {
      echo json_encode(['ok'=>0,'msg'=>'Site address and keyword are required.']); exit;
    }
    if (count($ids)===0) {
      echo json_encode(['ok'=>0,'msg'=>'No sites selected.']); exit;
    }
    if (!filter_var($target, FILTER_VALIDATE_URL)) {
      echo json_encode(['ok'=>0,'msg'=>'Invalid site address URL.']); exit;
    }
    if (!in_array($duration,[1,3,6,12])) $duration=1;

    $bal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");

    $purchased = [];
    $skipped   = [];
    $cost_per  = 1;
    foreach ($ids as $lid) {
      if ($bal < $cost_per) break;

      $exist = (int)$db->get_var("SELECT COUNT(*) FROM k_linkdb WHERE id='{$lid}'");
      if (!$exist) { $skipped[]=$lid; continue; }

      $dup = (int)$db->get_var("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND lid='{$lid}'");
      if ($dup>0){ $skipped[]=$lid; continue; }

      $db->query("INSERT INTO k_orders (uid,lid,target_url,anchor,duration_months,created_at,tarih)
                  VALUES ('{$uid}','{$lid}','".dbx($target)."','".dbx($keyword)."','{$duration}',NOW(),NOW())");
      $db->query("UPDATE k_users SET kredi = kredi - {$cost_per} WHERE id='{$uid}'");
      $bal -= $cost_per;
      $purchased[] = $lid;
    }

    $newBal = (int)$db->get_var("SELECT kredi FROM k_users WHERE id='{$uid}'");
    echo json_encode([
      'ok'=>1,
      'msg'=>'Processed',
      'purchased'=>$purchased,
      'skipped'=>$skipped,
      'new_balance'=>$newBal
    ]);
    exit;
  }

  echo json_encode(['ok'=>0,'msg'=>'Unknown endpoint']); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Link Depot</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    body { background:#0e0c1d; color:#ddd; }
    .card { background:#161329; border:none; border-radius:15px; }
    .card h5 { color:#9f7aea; font-weight:600; }
    .stats-box { font-size:22px; font-weight:700; }
    .badge-php { background:#6b46c1; }
    .badge-js  { background:#dd6b20; }
    table.dataTable { color:#fff; }
    .dataTables_filter input { background:#222; border:none; color:#fff; padding:5px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      background:#222; color:#fff!important; border:none; border-radius:6px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background:#9f7aea!important; color:#fff!important;
    }
    .buy-btn[disabled]{ opacity:.6; cursor:not-allowed; }
    .lock-badge{ display:inline-block; padding:.2rem .45rem; border-radius:6px; background:linear-gradient(90deg,#22c55e,#06b6d4); color:#001a17; font-weight:800; font-size:.8rem; }
    .toolbar{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    /* Bulk Modal style */
    .modal-title .sub{font-size:.85rem;color:#9ac7ff;display:block;margin-top:2px}
    .pill {display:inline-block;padding:.2rem .55rem;border-radius:999px;background:#0b1530;border:1px solid rgba(255,255,255,.06);color:#bbd7ff;font-size:.8rem}
    .list-sel {height:150px;overflow:auto;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:#0b1326;padding:.5rem}
    .list-sel .row-sel {display:flex;align-items:center;gap:.6rem;padding:.35rem .4rem;border-bottom:1px dashed rgba(255,255,255,.06)}
    .list-sel .row-sel:last-child{border-bottom:0}
    .avatar {width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:#0e1b3f;color:#88a8ff;font-weight:800}
    .k-label{font-size:.83rem;color:#cdeafe;margin-bottom:.35rem}
  </style>
</head>
<body>
<div class="container-fluid mt-4">

  <h3 class="mb-3 text-white fw-bold">
    <i class="bi bi-link-45deg"></i> Link Depot
  </h3>

  <!-- Top toolbar: bulk button + credit -->
  <div class="toolbar mb-3">
    <button id="btnBulkAdd" class="btn btn-success btn-sm" disabled>🧺 Bulk Add</button>
    <span class="ms-auto">
      💰 Credits:
      <span id="liveCreditBadge" class="badge bg-info"><?= (int)$credit ?></span>
      <small class="text-muted ms-2">(updates live)</small>
    </span>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <h5>Total Links</h5>
        <div class="stats-box text-info"><?= $totalLinks ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <h5>Total Credit</h5>
        <div class="stats-box text-success"><?= $totalCredit ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <h5>PHP Links</h5>
        <div class="stats-box"><span class="badge badge-php"><?= $phpCount ?></span></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <h5>JS Links</h5>
        <div class="stats-box"><span class="badge badge-js"><?= $jsCount ?></span></div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card p-3">
    <div class="table-responsive">
      <table id="marketTable" class="table table-dark table-striped align-middle" style="width:100%">
        <thead>
          <tr>
            <th style="width:36px;"><input type="checkbox" id="chkAll"></th>
            <th>Domain</th>
            <th>PA</th>
            <th>DA</th>
            <th>Credit</th>
            <th>Type</th>
            <th>Domain Year</th>
            <th>Panel Duration</th>
            <th>Added</th>
            <th style="width:160px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r):
            $lid  = (int)$r->id;
            $isOwned = isset($purchased_set[$lid]);
            $typeBadge = $r->tip==1 ? '<span class="badge badge-php">PHP</span>' : ($r->tip==2 ? '<span class="badge badge-js">JS</span>' : '<span class="badge bg-secondary">Other</span>');
            $domYear   = $has_year  ? esc($r->domain_year) : '–';
            $duration  = $has_sure  ? esc($r->sure)        : '–';
            $addedDate = $has_added ? esc($r->created_at)  : '–';

            // DA/PA (fallback -> API placeholder function)
            $da = (string)($r->alexa2 ?? '');
            $pa = (string)($r->alexa1 ?? '');
            $met = fetch_da_pa($r->domain, $da, $pa);
            $DA = esc($met['da']); $PA = esc($met['pa']);
          ?>
          <tr data-lid="<?=$lid?>" data-domain="<?=esc($r->domain)?>" data-da="<?=$DA?>" data-pa="<?=$PA?>">
            <td>
              <input type="checkbox" class="rowChk" value="<?=$lid?>" <?=$isOwned?'disabled':''?>>
            </td>
            <td><a href="<?=esc($r->link ?: ('https://'.$r->domain))?>" target="_blank" class="link-light text-decoration-none"><?= esc($r->domain) ?></a></td>
            <td><?= $PA!=='' ? $PA : '-' ?></td>
            <td><?= $DA!=='' ? $DA : '-' ?></td>
            <td>1</td>
            <td><?=$typeBadge?></td>
            <td><?=$domYear?></td>
            <td><?=$duration?></td>
            <td><?=$addedDate?></td>
            <td>
              <?php if($isOwned): ?>
                <span class="lock-badge">Purchased 🔒</span>
              <?php else: ?>
                <button class="btn btn-sm btn-primary buy-btn" data-lid="<?=$lid?>" data-domain="<?=esc($r->domain)?>">
                  🪙 Buy (1)
                </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Individual Buy Modal -->
<div class="modal fade" id="buyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#161329;color:#fff;border-radius:12px;">
      <div class="modal-header border-0">
        <h5 class="modal-title">Confirm Purchase</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Domain: <strong id="buyDomain"></strong></p>
        <p>Cost: <strong>1 credit</strong></p>
        <div id="buyAlert"></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmBuyBtn" class="btn btn-success">Confirm Buy</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Add Modal (premium-style form) -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#11172b;color:#fff;border-radius:14px;">
      <div class="modal-header border-0">
        <h5 class="modal-title">Add Link <span class="sub">Add selected links to your site</span></h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Site Address -->
        <div class="mb-3">
          <div class="k-label">Site Address</div>
          <input type="url" id="bl_target" class="form-control bg-dark text-light" placeholder="https://example.com" required>
        </div>
        <!-- Keyword -->
        <div class="mb-3">
          <div class="k-label">Keyword</div>
          <input type="text" id="bl_keyword" class="form-control bg-dark text-light" placeholder="Enter keyword / anchor" required>
        </div>
        <!-- Duration -->
        <div class="mb-3">
          <div class="k-label">Duration</div>
          <select id="bl_duration" class="form-select bg-dark text-light">
            <option value="1" selected>1 Month</option>
            <option value="3">3 Months</option>
            <option value="6">6 Months</option>
            <option value="12">12 Months</option>
          </select>
        </div>

        <!-- Cost summary -->
        <div class="mb-3">
          <span class="pill"><span id="pillSelCount">0</span> link selected × <span id="pillDur">1</span> month</span>
          <span class="pill ms-2"><span id="pillCost">0</span> credit</span>
        </div>

        <!-- Selected links preview with DA/PA -->
        <div class="mb-2"><div class="k-label">Selected Links (<span id="selCountText">0</span>) <a href="#" id="selClear" class="text-danger ms-2" style="text-decoration:none">Clear</a></div></div>
        <div class="list-sel" id="selList"></div>

        <div id="bulkAlert" class="mt-3"></div>
      </div>
      <div class="modal-footer border-0">
        <small class="text-warning me-auto">Site address and keyword are required</small>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button id="confirmBulkBtn" class="btn btn-success">
          <span id="addBtnCount">0</span> Add Link
        </button>
      </div>
    </div>
  </div>
</div>

<?php include "footer.php"; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  // DataTable
  const table = $('#marketTable').DataTable({
    pageLength: 10,
    order: [[9,"desc"]]
  });

  // checkbox logic
  const $chkAll = $('#chkAll');
  const $btnBulk = $('#btnBulkAdd');

  function refreshBulkState(){
    const any = $('.rowChk:enabled:checked').length > 0;
    $btnBulk.prop('disabled', !any);
  }

  $chkAll.on('change', function(){
    const checked = $(this).is(':checked');
    $('.rowChk:enabled').prop('checked', checked);
    refreshBulkState();
  });

  $(document).on('change','.rowChk', refreshBulkState);

  // ---------- Individual Buy ----------
  let currentBuyId = null;
  const buyModal = new bootstrap.Modal(document.getElementById('buyModal'));

  $(document).on('click','.buy-btn', function(){
    currentBuyId = $(this).data('lid');
    $('#buyDomain').text($(this).data('domain'));
    $('#buyAlert').html('');
    buyModal.show();
  });

  $('#confirmBuyBtn').on('click', function(){
    if(!currentBuyId) return;
    $('#buyAlert').html('<div class="alert alert-info">Processing...</div>');
    $.post('link-depo.php?ajax=buy', {lid: currentBuyId}, function(res){
      if(res.ok){
        const row = $('tr[data-lid="'+currentBuyId+'"]');
        row.find('.buy-btn').replaceWith('<span class="lock-badge">Purchased 🔒</span>');
        row.find('.rowChk').prop('checked', false).prop('disabled', true);
        $('#liveCreditBadge').text(res.new_balance);
        const navCredit = $('#navCredit'); if(navCredit.length) navCredit.text(res.new_balance);
        $('#buyAlert').html('<div class="alert alert-success">Purchased!</div>');
        setTimeout(()=>buyModal.hide(), 650);
        refreshBulkState();
      }else{
        $('#buyAlert').html('<div class="alert alert-danger">'+res.msg+'</div>');
      }
    }, 'json').fail(function(){
      $('#buyAlert').html('<div class="alert alert-danger">Network error</div>');
    });
  });

  // ---------- Bulk Add (premium modal) ----------
  const bulkModal = new bootstrap.Modal(document.getElementById('bulkModal'));
  const $selList = $('#selList');
  const $pillSelCount = $('#pillSelCount');
  const $pillDur = $('#pillDur');
  const $pillCost = $('#pillCost');
  const $selCountText = $('#selCountText');
  const $addBtnCount = $('#addBtnCount');

  function gatherSelection(){
    const ids = $('.rowChk:enabled:checked').map((_,el)=>$(el).val()).get();
    const rows = ids.map(id => $('tr[data-lid="'+id+'"]'));
    return { ids, rows };
  }

  function fillPreview(){
    const { ids, rows } = gatherSelection();
    $selList.empty();
    rows.forEach($r=>{
      const d = $r.data('domain');
      const da = $r.data('da') || '-';
      const pa = $r.data('pa') || '-';
      const letter = (d||'?').substring(0,1).toUpperCase();
      $selList.append(
        `<div class="row-sel">
          <div class="avatar">${letter}</div>
          <div>
            <div><strong>${d}</strong></div>
            <small>DA: ${da} &nbsp; PA: ${pa}</small>
          </div>
        </div>`
      );
    });
    const n = ids.length;
    const dur = parseInt($('#bl_duration').val()||'1',10);
    $pillSelCount.text(n);
    $pillDur.text(dur);
    $pillCost.text(n * 1); // প্রতি লিঙ্ক ১
    $selCountText.text(n);
    $addBtnCount.text(n);
  }

  $('#btnBulkAdd').on('click', function(){
    const count = $('.rowChk:enabled:checked').length;
    if(count===0){
      alert('Please select at least one site.'); return;
    }
    fillPreview();
    $('#bulkAlert').html('');
    $('#bl_target, #bl_keyword').val('');
    bulkModal.show();
  });

  $('#bl_duration').on('change', fillPreview);
  $('#selClear').on('click', function(e){ e.preventDefault(); $('.rowChk:enabled:checked').prop('checked', false); refreshBulkState(); fillPreview(); });

  $('#confirmBulkBtn').on('click', function(){
    const ids = $('.rowChk:enabled:checked').map((_,el)=>$(el).val()).get();
    const target = $('#bl_target').val().trim();
    const anchor = $('#bl_keyword').val().trim();
    const duration = $('#bl_duration').val();

    if(ids.length===0){ $('#bulkAlert').html('<div class="alert alert-warning">Select at least one site.</div>'); return; }
    if(target==='' || anchor===''){ $('#bulkAlert').html('<div class="alert alert-danger">Site address and keyword are required.</div>'); return; }

    $('#bulkAlert').html('<div class="alert alert-info">Processing...</div>');
    $.post('link-depo.php?ajax=bulk_add_links', {ids: ids, target_url: target, anchor: anchor, duration: duration}, function(res){
      if(!res.ok){ $('#bulkAlert').html('<div class="alert alert-danger">'+(res.msg||'Failed')+'</div>'); return; }

      // mark purchased rows
      (res.purchased||[]).forEach(function(lid){
        const row = $('tr[data-lid="'+lid+'"]');
        row.find('.buy-btn').replaceWith('<span class="lock-badge">Purchased 🔒</span>');
        row.find('.rowChk').prop('checked', false).prop('disabled', true);
      });
      // update credits
      if(typeof res.new_balance !== 'undefined'){
        $('#liveCreditBadge').text(res.new_balance);
        const navCredit = $('#navCredit'); if(navCredit.length) navCredit.text(res.new_balance);
      }
      const purchasedN = (res.purchased||[]).length;
      const skippedN   = (res.skipped||[]).length;
      $('#bulkAlert').html('<div class="alert alert-success">✅ Added '+purchasedN+' link(s), skipped '+skippedN+' already owned.</div>');
      refreshBulkState();
      setTimeout(()=>bulkModal.hide(), 1100);
    }, 'json').fail(function(){
      $('#bulkAlert').html('<div class="alert alert-danger">Network error</div>');
    });
  });

  // Start — ensure bulk button state
  refreshBulkState();
});
</script>
</body>
</html>
