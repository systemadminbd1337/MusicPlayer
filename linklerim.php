<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error logging function
function debug_log($message) {
    $log_file = 'linkkkkkkkkkerr.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Log start
debug_log("=== MY LINKS PAGE STARTED ===");

include "header.php";

// Check if user is logged in properly
if (empty($_SESSION['user'])) {
    debug_log("User not logged in - redirecting to login");
    redirect("login.php"); 
    exit();
}

debug_log("User session found: " . print_r($_SESSION['user'], true));

$user = (object)$_SESSION['user'];
$uid  = (int)$user->id;

debug_log("User ID: $uid");

// ✅ Force kredi column since it has credits
$creditCol = 'kredi';
$credits = (float)$db->get_var("SELECT COALESCE(kredi,0) FROM k_users WHERE id='{$uid}'");
debug_log("Credit column: $creditCol");
debug_log("User credits: $credits");

// Also update session credits
$_SESSION['user']->credits = $credits;

// === Helpers ===
function esc($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

function safe_rows($sql){ 
    global $db; 
    try{
        return $db->get_results($sql);
    }catch(Throwable $e){
        debug_log("SQL Error in safe_rows: " . $e->getMessage() . " - Query: " . $sql);
        return [];
    } 
}

function safe_val($sql,$def=0){ 
    global $db; 
    try{
        $v=$db->get_var($sql);
        return $v??$def;
    }catch(Throwable $e){
        debug_log("SQL Error in safe_val: " . $e->getMessage() . " - Query: " . $sql);
        return $def;
    } 
}

// === FIX: Check if k_orders has target_url and keyword columns ===
function has_column($table, $column) {
    global $db;
    try {
        $exists = (int)$db->get_var(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='{$table}' AND column_name='{$column}'"
        );
        return $exists > 0;
    } catch (Throwable $e) {
        debug_log("Error checking column $column in $table: " . $e->getMessage());
        return false;
    }
}

// Ensure columns exist
$has_target_url = has_column('k_orders', 'target_url');
$has_keyword_col = has_column('k_orders', 'keyword');

debug_log("target_url column exists: " . ($has_target_url ? 'YES' : 'NO'));
debug_log("keyword column exists: " . ($has_keyword_col ? 'YES' : 'NO'));

if (!$has_target_url) {
    try {
        $db->query("ALTER TABLE k_orders ADD COLUMN target_url VARCHAR(500) DEFAULT ''");
        debug_log("Added target_url column to k_orders");
    } catch (Throwable $e) {
        debug_log("Error adding target_url column: " . $e->getMessage());
    }
}

if (!$has_keyword_col) {
    try {
        $db->query("ALTER TABLE k_orders ADD COLUMN keyword VARCHAR(255) DEFAULT ''");
        debug_log("Added keyword column to k_orders");
    } catch (Throwable $e) {
        debug_log("Error adding keyword column: " . $e->getMessage());
    }
}

// === FIX: Add expiry_date column if not exists ===
$has_expiry_date = has_column('k_orders', 'expiry_date');
if (!$has_expiry_date) {
    try {
        $db->query("ALTER TABLE k_orders ADD COLUMN expiry_date DATE NULL");
        debug_log("Added expiry_date column to k_orders");
        
        // Set initial expiry dates for existing orders
        $db->query("UPDATE k_orders o 
                   INNER JOIN k_linkdb l ON l.id=o.lid 
                   SET o.expiry_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                   WHERE o.expiry_date IS NULL OR o.expiry_date = '0000-00-00'");
        debug_log("Updated existing orders with expiry dates");
    } catch (Throwable $e) {
        debug_log("Error adding expiry_date column: " . $e->getMessage());
    }
}

// Fix any remaining NULL or invalid expiry dates
$db->query("UPDATE k_orders SET expiry_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
           WHERE expiry_date IS NULL OR expiry_date = '0000-00-00'");

// === Pagination ===
$page = max(1,(int)($_GET['page'] ?? 1));
$limit = 15; 
$offset = ($page-1)*$limit;

debug_log("Pagination - Page: $page, Limit: $limit, Offset: $offset");

$totalLinks = (int)safe_val("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}'");
debug_log("Total links for user $uid: $totalLinks");

// === FIXED QUERY: Calculate actual remaining days WITHOUT forcing 30 days ===
$query = "
  SELECT 
    o.id AS oid,
    o.credit AS order_credit,
    o.auto_renew,
    o.target_url,
    o.keyword AS user_keyword,
    o.expiry_date,
    l.domain,
    l.link,
    l.keyword AS default_keyword,
    l.durum,
    l.added_date,
    -- FIX: Show actual remaining days without forcing to 30
    CASE 
      WHEN l.durum = 1 AND o.expiry_date > CURDATE() THEN 
        DATEDIFF(o.expiry_date, CURDATE())
      WHEN l.durum = 1 AND (o.expiry_date IS NULL OR o.expiry_date <= CURDATE()) THEN 0
      ELSE 0 
    END as remaining_days
  FROM k_orders o
  INNER JOIN k_linkdb l ON l.id=o.lid
  WHERE o.uid='{$uid}'
  ORDER BY o.id DESC LIMIT {$offset},{$limit}
";

debug_log("Executing FIXED query: " . $query);
$list = safe_rows($query);
debug_log("Query returned " . count($list) . " rows");

// Debug first row data
if (!empty($list)) {
    debug_log("First row data: " . print_r($list[0], true));
}

// Update any orders with wrong expiry dates
foreach($list as $r) {
    if (empty($r->expiry_date) || $r->expiry_date == '0000-00-00') {
        $new_expiry = date('Y-m-d', strtotime('+30 days'));
        $db->query("UPDATE k_orders SET expiry_date = '{$new_expiry}' WHERE id = '{$r->oid}'");
        debug_log("Fixed expiry_date for order {$r->oid}: {$new_expiry}");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>📂 My Links</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#0b0f1a;color:#e2e8f0;font-family:'Inter',sans-serif;}
.panel{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:22px;margin-bottom:20px;}
h4{color:#38bdf8;text-shadow:0 0 8px rgba(56,189,248,.4);}
.btnx{border:none;border-radius:10px;padding:10px 18px;font-weight:600;color:#fff;transition:.3s;cursor:pointer;}
.btnx:hover{transform:translateY(-2px);filter:brightness(1.1);}
.btn-extend{background:linear-gradient(90deg,#059669,#10b981);}
.btn-renew{background:linear-gradient(90deg,#0ea5e9,#38bdf8);}
.btn-refund{background:linear-gradient(90deg,#b45309,#f59e0b);}
.btn-edit{background:linear-gradient(90deg,#334155,#64748b);}
.table-dark{background:none;color:#e2e8f0;}
.table-dark thead{background:#0f172a;color:#60a5fa;text-transform:uppercase;font-size:13px;}
.table-dark td{vertical-align:middle;}
.status-ok{color:#22c55e;font-weight:600;}
.status-exp{color:#ef4444;font-weight:600;}
.badge-day{background:rgba(245,158,11,.15);color:#fbbf24;padding:4px 10px;border-radius:20px;font-size:.8rem;font-weight:600;}
.toast{display:none;position:fixed;bottom:20px;right:20px;background:#38bdf8;color:#fff;padding:12px 18px;border-radius:8px;font-weight:600;}

/* New Styles for Enhanced UI */
.bulk-modal .modal-content{background:#111827;border:1px solid rgba(255,255,255,.1);border-radius:16px;}
.bulk-modal .modal-header{border-bottom:1px solid rgba(255,255,255,.1);}
.bulk-modal .modal-footer{border-top:1px solid rgba(255,255,255,.1);}
.bulk-modal .form-control,.bulk-modal .form-select{background:#0f172a;border:1px solid #1e293b;color:#e2e8f0;}
.bulk-modal .form-control:focus,.bulk-modal .form-select:focus{background:#0f172a;border-color:#38bdf8;color:#e2e8f0;box-shadow:0 0 0 0.2rem rgba(56,189,248,.25);}
.bulk-modal .form-label{color:#94a3b8;font-weight:500;margin-bottom:8px;}
.bulk-modal .days-counter{background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:12px;padding:20px;text-align:center;margin-bottom:20px;}
.bulk-modal .days-counter .days{font-size:2.5rem;font-weight:700;color:#fbbf24;text-shadow:0 0 10px rgba(251,191,36,.3);}
.bulk-modal .days-counter .label{font-size:.9rem;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;}
.bulk-modal .end-date{font-size:1.1rem;color:#38bdf8;font-weight:600;margin-top:10px;}
.bulk-modal .btn-apply{background:linear-gradient(90deg,#059669,#10b981);border:none;padding:10px 24px;border-radius:10px;font-weight:600;transition:.3s;}
.bulk-modal .btn-apply:hover{transform:translateY(-2px);filter:brightness(1.1);}
.bulk-modal .btn-cancel{background:rgba(255,255,255,.1);border:none;padding:10px 24px;border-radius:10px;font-weight:600;transition:.3s;color:#e2e8f0;}
.bulk-modal .btn-cancel:hover{background:rgba(255,255,255,.15);transform:translateY(-2px);}
.bulk-modal .selected-count{background:rgba(56,189,248,.15);color:#38bdf8;padding:8px 16px;border-radius:20px;font-size:.9rem;font-weight:600;display:inline-block;margin-bottom:15px;}
.remaining-days{font-size:0.85rem;color:#fbbf24;font-weight:600;}
.expiry-date{font-size:0.85rem;color:#94a3b8;}
.user-keyword{color:#10b981;font-weight:600;}
.default-keyword{color:#94a3b8;font-style:italic;}
.remaining-30-days{color:#fbbf24;font-weight:700;font-size:0.9rem;}

/* Edit Form Styles */
.edit-form-container{background:rgba(15,23,42,0.8);border-radius:12px;padding:20px;margin-bottom:20px;}
.edit-form-group{margin-bottom:15px;}
.edit-form-label{color:#94a3b8;font-weight:500;margin-bottom:8px;display:block;}
.edit-form-input{width:100%;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:10px 12px;color:#e2e8f0;transition:all 0.3s;}
.edit-form-input:focus{outline:none;border-color:#38bdf8;box-shadow:0 0 0 2px rgba(56,189,248,0.2);}
.edit-form-btn{background:linear-gradient(90deg,#059669,#10b981);border:none;border-radius:8px;padding:10px 20px;color:white;font-weight:600;cursor:pointer;transition:all 0.3s;}
.edit-form-btn:hover{transform:translateY(-2px);filter:brightness(1.1);}
.edit-form-btn:disabled{background:#64748b;cursor:not-allowed;transform:none;}

/* Day status colors */
.days-high { color: #22c55e; }
.days-medium { color: #fbbf24; }
.days-low { color: #ef4444; }
</style>
</head>
<body>

<div class="container-fluid">

  <!-- Bulk Buttons -->
  <div class="panel d-flex flex-wrap align-items-center gap-3">
    <h4 class="m-0 flex-grow-1">⚙️ Bulk Operations</h4>
    <button class="btnx btn-extend" data-bs-toggle="modal" data-bs-target="#extendModal"><i class="bi bi-plus-circle"></i> Extend</button>
    <button class="btnx btn-renew"  onclick="doAction('renew')"><i class="bi bi-arrow-repeat"></i> Renew</button>
    <button class="btnx btn-refund" onclick="doAction('refund')"><i class="bi bi-arrow-counterclockwise"></i> Refund</button>
    <button class="btnx btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil-square"></i> Edit Keywords & URLs</button>
    <span class="ms-auto text-info">Credits: <span class="credit-value"><?=$credits?></span> CR</span>
    <span id="count" class="ms-2">0 selected</span>
  </div>

  <!-- Links Table -->
  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="text-info"><i class="bi bi-list"></i> Link Details</h5>
      <input type="search" id="q" class="form-control w-auto" style="background:#0f172a;color:#e2e8f0;border:1px solid #1e293b;" placeholder="Search domain or keyword...">
    </div>

    <div class="table-responsive">
      <table class="table table-dark align-middle">
        <thead><tr>
          <th><input type="checkbox" id="checkAll"></th>
          <th>Link</th><th>Domain</th><th>Keyword</th>
          <th>Target URL</th>
          <th>Remaining Days</th>
          <th>End Date</th>
          <th>Status</th>
          <th>Auto Renew</th>
          <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if(!$list): ?>
          <tr><td colspan='10' class='text-center text-muted'>No links found</td></tr>
        <?php else: ?>
          <?php foreach($list as $r): 
            // FIX: Use actual calculated remaining days WITHOUT forcing to 30
            $remaining_days = (int)$r->remaining_days;
            $is_expired = $remaining_days === 0;
            $expiry_date = date('Y-m-d', strtotime($r->expiry_date));
            
            // Determine day color
            $day_class = 'days-high';
            if ($remaining_days <= 10) $day_class = 'days-low';
            elseif ($remaining_days <= 20) $day_class = 'days-medium';
            
            // FIX: Use user's keyword from k_orders, fallback to default
            $display_keyword = !empty($r->user_keyword) ? $r->user_keyword : $r->default_keyword;
            $target_url = !empty($r->target_url) ? $r->target_url : $r->link;
          ?>
            <tr>
              <td><input type="checkbox" class="rowCheck" value="<?=$r->oid?>"></td>
              <td><a href="<?=esc($target_url)?>" target="_blank" style="color:#38bdf8"><?=strlen($target_url)>40?substr($target_url,0,40).'…':$target_url?></a></td>
              <td><?=esc($r->domain)?></td>
              <td>
                <span class="<?=!empty($r->user_keyword)?'user-keyword':'default-keyword'?>" title="<?=!empty($r->user_keyword)?'Your Custom Keyword':'Default Keyword'?>">
                  <?=esc($display_keyword)?>
                  <?php if(!empty($r->user_keyword)): ?>
                    <small class="text-warning" title="Custom Keyword">✓</small>
                  <?php endif; ?>
                </span>
              </td>
              <td><small class="text-muted"><?=strlen($target_url)>50?substr($target_url,0,50).'…':$target_url?></small></td>
              <td>
                <span class="remaining-days <?=$day_class?>">
                  <?=$remaining_days?> days
                </span>
              </td>
              <td class="expiry-date"><?=$expiry_date?></td>
              <td class="<?=$r->durum && !$is_expired?'status-ok':'status-exp'?>">
                <?=$r->durum && !$is_expired?'Active':'Expired'?>
              </td>
              <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" <?=$r->auto_renew?'checked':''?> onchange="toggleRenew(<?=$r->oid?>,this.checked)"></div></td>
              <td>
                <button class="btn btn-sm btn-outline-info" onclick="editSingleLink(<?=$r->oid?>, '<?=esc($display_keyword)?>', '<?=esc($target_url)?>')" title="Edit Keyword & URL">
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php
      $pages = max(1, ceil($totalLinks / $limit));
      if ($pages > 1) {
        echo '<ul class="pagination justify-content-center mt-3">';
        for ($i = 1; $i <= $pages; $i++) {
          $active = $i == $page ? ' class="page-item active"' : ' class="page-item"';
          echo "<li{$active}><a class='page-link' href='?page=$i'>$i</a></li>";
        }
        echo '</ul>';
      }
    ?>
  </div>
</div>

<!-- Extend Modal -->
<div class="modal fade bulk-modal" id="extendModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-info"><i class="bi bi-plus-circle me-2"></i>Extend Selected Links</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="selected-count" id="extendSelectedCount">0 links selected</div>
        
        <div class="days-counter">
          <div class="days" id="extendDaysDisplay">30</div>
          <div class="label">EXTENSION DAYS</div>
          <div class="end-date" id="extendEndDate">NEW END DATE: <?=date('Y-m-d', strtotime('+30 days'))?></div>
        </div>
        
        <div class="mb-3">
          <label for="extendDays" class="form-label">Extend Duration (Days)</label>
          <input type="number" class="form-control" id="extendDays" value="30" min="30" max="365">
        </div>
        
        <div class="mb-3">
          <label for="extendCost" class="form-label">Total Cost</label>
          <div class="input-group">
            <input type="text" class="form-control" id="extendCost" value="0" readonly>
            <span class="input-group-text" style="background:#0f172a;color:#94a3b8;border:1px solid #1e293b;">CR</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-apply" onclick="processExtend()">Apply Extension</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal - ENHANCED: Keyword AND Target URL Update -->
<div class="modal fade bulk-modal" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-info"><i class="bi bi-pencil-square me-2"></i>Update Keywords & Target URLs</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="selected-count" id="editSelectedCount">0 links selected</div>
        
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label for="newKeyword" class="form-label">New Keyword</label>
              <input type="text" class="form-control" id="newKeyword" placeholder="Enter new keyword for selected links">
              <div class="form-text text-muted">Leave empty to keep current keywords</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label for="newTargetUrl" class="form-label">New Target URL</label>
              <input type="url" class="form-control" id="newTargetUrl" placeholder="https://example.com">
              <div class="form-text text-muted">Leave empty to keep current URLs</div>
            </div>
          </div>
        </div>
        
        <div class="alert alert-info" style="background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.3);color:#38bdf8;">
          <i class="bi bi-info-circle me-2"></i>
          Updating <span id="linkCount">0</span> selected links. 
          You can update both keyword and URL, or just one of them.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-apply" onclick="processEdit()">Update Links</button>
      </div>
    </div>
  </div>
</div>

<!-- Single Link Edit Modal -->
<div class="modal fade bulk-modal" id="singleEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-info"><i class="bi bi-pencil me-2"></i>Edit Link</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="singleEditId">
        <div class="mb-3">
          <label for="singleEditKeyword" class="form-label">Keyword</label>
          <input type="text" class="form-control" id="singleEditKeyword" placeholder="Enter keyword">
        </div>
        <div class="mb-3">
          <label for="singleEditTargetUrl" class="form-label">Target URL</label>
          <input type="url" class="form-control" id="singleEditTargetUrl" placeholder="https://example.com">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-apply" onclick="processSingleEdit()">Update Link</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toast(m){$('#toast').text(m).fadeIn(200).delay(1200).fadeOut(300);}
function updateSel(){
  const count = $('.rowCheck:checked').length;
  $('#count').text(count+' selected');
  $('#extendSelectedCount').text(count+' links selected');
  $('#editSelectedCount').text(count+' links selected');
  $('#linkCount').text(count);
}
$('#checkAll').on('change',()=>{$('.rowCheck').prop('checked',$('#checkAll').prop('checked'));updateSel();});
$(document).on('change','.rowCheck',updateSel);

// Update days counter and end date when extend days change
$('#extendDays').on('input', function() {
  const days = $(this).val();
  $('#extendDaysDisplay').text(days);
  
  // Calculate new end date
  const today = new Date();
  const endDate = new Date(today);
  endDate.setDate(today.getDate() + parseInt(days));
  const formattedDate = endDate.toISOString().split('T')[0];
  $('#extendEndDate').text('NEW END DATE: ' + formattedDate);
  
  // Calculate cost (you can adjust the cost calculation logic)
  const costPerLink = 1; // 1 credit per link per 30 days
  const totalCost = Math.ceil((days / 30) * costPerLink * $('.rowCheck:checked').length);
  $('#extendCost').val(totalCost);
});

// Update cost when selection changes
$(document).on('change', '.rowCheck', function() {
  const days = $('#extendDays').val();
  const costPerLink = 1;
  const totalCost = Math.ceil((days / 30) * costPerLink * $('.rowCheck:checked').length);
  $('#extendCost').val(totalCost);
});

function processExtend() {
  const ids = $('.rowCheck:checked').map(function(){return $(this).val();}).get();
  if(!ids.length){toast('⚠️ No items selected');return;}
  
  const days = $('#extendDays').val();
  if(!days || days < 30){toast('⚠️ Please enter valid days (minimum 30)');return;}
  
  $.ajax({
    url:'ajax_bulk_action.php',
    type:'POST',
    dataType:'json',
    data:{
      action: 'extend',
      ids: ids.join(','),
      days: days
    },
    success:function(res){
      if(res.success){
        toast('✅ '+res.message);
        if(res.new_credit!==undefined)$('.credit-value').text(res.new_credit.toFixed(0)+' CR');
        $('#extendModal').modal('hide');
        setTimeout(()=>location.reload(),800);
      }else toast('❌ '+res.error);
    },
    error:function(xhr){toast('❌ Request failed');console.log('ERR:',xhr.responseText);}
  });
}

function processEdit() {
  const ids = $('.rowCheck:checked').map(function(){return $(this).val();}).get();
  if(!ids.length){toast('⚠️ No items selected');return;}
  
  const newKeyword = $('#newKeyword').val().trim();
  const newTargetUrl = $('#newTargetUrl').val().trim();
  
  // At least one field should be filled
  if(!newKeyword && !newTargetUrl){
    toast('⚠️ Please enter either keyword or target URL');
    $('#newKeyword').focus();
    return;
  }
  
  // Show loading state
  $('.btn-apply').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Updating...');
  
  $.ajax({
    url:'ajax_bulk_action.php',
    type:'POST',
    dataType:'json',
    data:{
      action: 'edit_keyword_url', // Updated action name
      ids: ids.join(','),
      keyword: newKeyword,
      target_url: newTargetUrl
    },
    success:function(res){
      $('.btn-apply').prop('disabled', false).html('Update Links');
      
      if(res.success){
        toast('✅ '+res.message);
        $('#editModal').modal('hide');
        // Clear form fields
        $('#newKeyword').val('');
        $('#newTargetUrl').val('');
        setTimeout(()=>location.reload(),800);
      }else {
        toast('❌ '+res.error);
        console.log('Error:', res);
      }
    },
    error:function(xhr){
      $('.btn-apply').prop('disabled', false).html('Update Links');
      toast('❌ Request failed');
      console.log('ERR:',xhr.responseText);
    }
  });
}

// Single link edit functions
function editSingleLink(id, currentKeyword, currentUrl) {
  $('#singleEditId').val(id);
  $('#singleEditKeyword').val(currentKeyword);
  $('#singleEditTargetUrl').val(currentUrl);
  $('#singleEditModal').modal('show');
}

function processSingleEdit() {
  const id = $('#singleEditId').val();
  const newKeyword = $('#singleEditKeyword').val().trim();
  const newTargetUrl = $('#singleEditTargetUrl').val().trim();
  
  if(!newKeyword && !newTargetUrl){
    toast('⚠️ Please enter either keyword or target URL');
    return;
  }
  
  $('.btn-apply').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Updating...');
  
  $.ajax({
    url:'ajax_bulk_action.php',
    type:'POST',
    dataType:'json',
    data:{
      action: 'edit_single_link',
      id: id,
      keyword: newKeyword,
      target_url: newTargetUrl
    },
    success:function(res){
      $('.btn-apply').prop('disabled', false).html('Update Link');
      
      if(res.success){
        toast('✅ '+res.message);
        $('#singleEditModal').modal('hide');
        setTimeout(()=>location.reload(),800);
      }else {
        toast('❌ '+res.error);
      }
    },
    error:function(xhr){
      $('.btn-apply').prop('disabled', false).html('Update Link');
      toast('❌ Request failed');
      console.log('ERR:',xhr.responseText);
    }
  });
}

function doAction(a){
  const ids=$('.rowCheck:checked').map(function(){return $(this).val();}).get();
  if(!ids.length){toast('⚠️ No items selected');return;}

  let data={action:a,ids:ids.join(',')};
  
  $.ajax({
    url:'ajax_bulk_action.php',type:'POST',dataType:'json',data:data,
    success:function(res){
      if(res.success){
        toast('✅ '+res.message);
        if(res.new_credit!==undefined)$('.credit-value').text(res.new_credit.toFixed(0)+' CR');
        setTimeout(()=>location.reload(),800);
      }else toast('❌ '+res.error);
    },
    error:function(xhr){toast('❌ Request failed');console.log('ERR:',xhr.responseText);}
  });
}

function toggleRenew(id,on){
  $.ajax({
    url:'ajax_bulk_action.php',type:'POST',dataType:'json',
    data:{action:'toggle_renew',id:id,status:on?1:0},
    success:function(r){toast(r.success?r.message:r.error);},
    error:function(x){toast('❌ Request failed');console.log(x.responseText);}
  });
}

// Search filter
$('#q').on('input',function(){
  const q=$(this).val().toLowerCase();
  $('tbody tr').each(function(){
    const text=$(this).text().toLowerCase();
    $(this).toggle(text.indexOf(q)!==-1);
  });
});

// Initialize extend modal values
$('#extendModal').on('show.bs.modal', function() {
  const days = $('#extendDays').val();
  $('#extendDaysDisplay').text(days);
  
  const today = new Date();
  const endDate = new Date(today);
  endDate.setDate(today.getDate() + parseInt(days));
  const formattedDate = endDate.toISOString().split('T')[0];
  $('#extendEndDate').text('NEW END DATE: ' + formattedDate);
  
  const costPerLink = 1;
  const totalCost = Math.ceil((days / 30) * costPerLink * $('.rowCheck:checked').length);
  $('#extendCost').val(totalCost);
});

// Clear edit modal when closed and update link count when shown
$('#editModal').on('show.bs.modal', function() {
  const count = $('.rowCheck:checked').length;
  $('#linkCount').text(count);
});

$('#editModal').on('hidden.bs.modal', function () {
  $('#newKeyword').val('');
  $('#newTargetUrl').val('');
  $('.btn-apply').prop('disabled', false).html('Update Links');
});

$('#singleEditModal').on('hidden.bs.modal', function () {
  $('#singleEditId').val('');
  $('#singleEditKeyword').val('');
  $('#singleEditTargetUrl').val('');
  $('.btn-apply').prop('disabled', false).html('Update Link');
});
</script>
</body>
</html>
<?php
debug_log("=== MY LINKS PAGE COMPLETED ===");
?>