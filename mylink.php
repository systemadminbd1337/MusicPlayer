<?php
// my_links.php
include "header.php";
if (empty($_SESSION['user'])) { redirect("login.php"); exit(); }
$user = (object) $_SESSION['user'];
$uid = (int)$user->id;

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function safe_val($sql,$def=0){ global $db; try{ $v=$db->get_var($sql); return $v ?? $def; }catch(Throwable $e){ return $def; } }
function safe_rows($sql){ global $db; try{ return $db->get_results($sql); }catch(Throwable $e){ return []; } }

// Pagination
$page = max(1,(int)($_GET['page']??1));
$limit = 10;
$offset=($page-1)*$limit;

// Stats
$totalSites=(int)safe_val("SELECT COUNT(DISTINCT l.domain)
  FROM k_orders o INNER JOIN k_linkdb l ON l.id=o.lid WHERE o.uid='{$uid}'");
$totalLinks=(int)safe_val("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}'");
$expired=(int)safe_val("SELECT COUNT(*)
  FROM k_orders o INNER JOIN k_linkdb l ON l.id=o.lid
  WHERE o.uid='{$uid}' AND l.durum='0'");

// Data - DIRECT QUERY TEST
$list = safe_rows("
  SELECT
    o.id,
    o.target_url,
    o.anchor,
    o.credit AS order_credit,
    o.auto_renew,
    l.domain,
    l.keyword,
    l.link,
    l.credit AS link_credit,
    l.durum,
    l.added_date
  FROM k_orders o
  INNER JOIN k_linkdb l ON l.id=o.lid
  WHERE o.uid='{$uid}'
  ORDER BY o.id DESC
  LIMIT {$offset},{$limit}
");

// Debug: Check database structure
error_log("=== DATABASE DEBUG ===");
error_log("User ID: " . $uid);
error_log("Total rows: " . count($list));

// Test direct database query to check actual data
$test_query = $db->get_results("SHOW COLUMNS FROM k_orders");
error_log("k_orders columns:");
foreach($test_query as $col) {
    error_log(" - " . $col->Field);
}

if($list) {
    foreach($list as $index => $row) {
        error_log("Row $index:");
        foreach($row as $key => $value) {
            error_log("   $key: " . (string)$value);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Links</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{
  --primary:#38bdf8;--success:#10b981;--danger:#ef4444;
  --bg:#0b1120;--card:#0f172a;--txt:#e2e8f0;--muted:#94a3b8;--br:#334155;
}
body{background:var(--bg);color:var(--txt);font-family:'Inter',sans-serif;margin:0}
.container{max-width:1200px;margin:0 auto;padding:24px}
.card{background:var(--card);border:1px solid var(--br);border-radius:12px;padding:16px;margin-bottom:18px;box-shadow:0 0 20px rgba(56,189,248,.06)}
.row{display:flex;gap:12px;flex-wrap:wrap}
.col{flex:1 1 0}
.stat{text-align:center}
.stat .n{font-size:2em;font-weight:700;color:var(--primary)}
.stat .l{font-size:.85em;color:var(--muted);text-transform:uppercase}
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px 0}
.input{background:#091022;border:1px solid var(--br);color:var(--txt);padding:8px 10px;border-radius:8px}
.btn{border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}
.btn-extend{background:#d1fae5;color:#065f46}
.btn-renew{background:#dbeafe;color:#1e3a8a}
.btn-refund{background:#fef3c7;color:#92400e}
.btn-edit{background:#e2e8f0;color:#111827}
.count{margin-left:auto;background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.08);color:var(--primary);padding:6px 12px;border-radius:20px;font-weight:700}
.table{width:100%;border-collapse:collapse;margin-top:12px}
.table th,.table td{padding:12px;border-bottom:1px solid var(--br);text-align:left}
.table thead th{background:#0c142b;text-transform:uppercase;font-size:12px}
.badge-ok{background:#10b98122;color:#10b981;padding:6px 10px;border-radius:999px;font-weight:700}
.badge-bad{background:#ef444422;color:#ef4444;padding:6px 10px;border-radius:999px;font-weight:700}
.pagination{display:flex;justify-content:center;gap:6px;list-style:none;padding:12px 0}
.pagination a{color:var(--txt);padding:6px 12px;border:1px solid var(--br);border-radius:8px;text-decoration:none}
.pagination .active a{background:var(--primary);color:#fff}
.toast{display:none;position:fixed;bottom:18px;right:18px;background:var(--primary);color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 0 18px rgba(56,189,248,.4);font-weight:700;z-index:9999}
</style>
</head>
<body>

<div class="container">

  <div class="card">
    <div class="row">
      <div class="col"><div class="stat"><div class="n"><?= $totalSites ?></div><div class="l">Total Sites</div></div></div>
      <div class="col"><div class="stat"><div class="n"><?= $totalLinks ?></div><div class="l">Total Links</div></div></div>
      <div class="col"><div class="stat"><div class="n" style="color:var(--danger)"><?= $expired ?></div><div class="l">Expired</div></div></div>
    </div>
  </div>

  <div class="card toolbar" role="toolbar" aria-label="Bulk actions">
    <button class="btn btn-extend" onclick="doExtend()">Bulk Extend</button>
    <button class="btn btn-renew" onclick="doRenew()">Enable Renewal</button>
    <button class="btn btn-refund" onclick="doRefund()">Bulk Refund</button>
    <button class="btn btn-edit" onclick="doEdit()">Bulk Edit</button>
    <div id="selCount" class="count">0 selected</div>
  </div>

  <div class="card">
    <table class="table" role="table">
      <thead>
        <tr>
          <th><input type="checkbox" id="checkAll" aria-label="Select all"></th>
          <th>Live Link</th>
          <th>Domain</th>
          <th>Keyword</th>
          <th>Target URL</th>
          <th>Status</th>
          <th>Auto Renew</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$list){ ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted)">No links found</td></tr>
        <?php } else { 
          $row_index = 0;
          foreach($list as $r){
          $row_index++;
          
          // Get order ID - try multiple possible field names
          $oid = 0;
          if(isset($r->id) && $r->id > 0) {
              $oid = (int)$r->id;
          } elseif(isset($r->order_id) && $r->order_id > 0) {
              $oid = (int)$r->order_id;
          } elseif(isset($r->oid) && $r->oid > 0) {
              $oid = (int)$r->oid;
          }
          
          $link = esc($r->link ?? '');
          $domain = esc($r->domain ?? '');
          $keyword = esc($r->keyword ?? '');
          $target = esc($r->target_url ?? '');
          $auto = (int)($r->auto_renew ?? 0);
          $status = (int)($r->durum ?? 0);
        ?>
        <tr>
          <td>
            <input type="checkbox" class="rowCheck" value="<?= $oid ?>" 
                   aria-label="Select link <?= $oid ?>"
                   data-row="<?= $row_index ?>"
                   data-domain="<?= $domain ?>"
                   data-debug="<?= $oid ?>|<?= $domain ?>|<?= $keyword ?>">
            <!-- Debug: Row=<?= $row_index ?>, OID=<?= $oid ?>, Domain=<?= $domain ?> -->
          </td>
          <td>
            <?php if($link){ ?>
              <a href="<?= $link ?>" target="_blank" style="color:var(--primary);text-decoration:none"><?= strlen($link)>50?substr($link,0,50).'…':$link ?></a>
            <?php } else { echo '—'; } ?>
          </td>
          <td><?= $domain ?: '—' ?></td>
          <td><?= $keyword ?: '—' ?></td>
          <td><?= $target ?: '—' ?></td>
          <td><?= $status ? '<span class="badge-ok">Active</span>' : '<span class="badge-bad">Expired</span>' ?></td>
          <td>
            <label style="display:inline-flex;align-items:center;gap:8px">
              <input type="checkbox" <?= $auto ? 'checked' : '' ?> onchange="toggleRenew(<?= $oid ?>, this.checked)">
              <span style="font-weight:700;color:<?= $auto ? '#10b981' : '#94a3b8' ?>"><?= $auto ? 'ON' : 'OFF' ?></span>
            </label>
          </td>
        </tr>
        <?php }} ?>
      </tbody>
    </table>

    <?php
      $pages = max(1, ceil($totalLinks / $limit));
      if ($pages > 1) {
        echo '<ul class="pagination">';
        for ($i = 1; $i <= $pages; $i++) {
          $active = $i == $page ? ' class="active"' : '';
          // FIXED: Use the correct filename 'my_links.php' instead of 'mylink.php'
          $q = $_GET; $q['page'] = $i; $qs = http_build_query($q);
          echo "<li{$active}><a href='my_links.php?{$qs}'>$i</a></li>";
        }
        echo '</ul>';
      }
    ?>
  </div>

</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function toast(msg){ 
    const t = document.getElementById('toast'); 
    t.textContent = msg; 
    $(t).stop(true,true).fadeIn(150).delay(1400).fadeOut(300); 
}

// Initialize checkbox functionality
$(document).ready(function(){
    console.log("Page loaded. Total checkboxes: " + $('.rowCheck').length);
    
    // Log all checkbox values on load
    $('.rowCheck').each(function(i){
        console.log("Checkbox " + i + " on load - value:", $(this).val(), "data-debug:", $(this).data('debug'));
    });
    
    // Select all functionality
    $('#checkAll').on('change', function(){
        const isChecked = this.checked;
        console.log("Select all changed to:", isChecked);
        $('.rowCheck').prop('checked', isChecked);
        updateSel();
    });
    
    // Individual checkbox changes
    $(document).on('change', '.rowCheck', function(){
        const value = $(this).val();
        const debug = $(this).data('debug');
        console.log("Checkbox changed - Value:", value, "Debug:", debug, "Checked:", this.checked);
        updateSel();
    });
    
    // Initial selection count
    updateSel();
});

// Update selection counter
function updateSel(){
    const count = $('.rowCheck:checked').length;
    $('#selCount').text(count + ' selected');
}

// Get selected IDs - FIXED VERSION
function pickIds(){
    const ids = [];
    
    console.log("=== Starting pickIds ===");
    console.log("Total checkboxes:", $('.rowCheck').length);
    console.log("Checked checkboxes:", $('.rowCheck:checked').length);
    
    $('.rowCheck:checked').each(function(index){
        const $this = $(this);
        const value = $this.val();
        const debug = $this.data('debug');
        const row = $this.data('row');
        
        console.log("Processing checkbox " + index + ":", {
            value: value,
            debug: debug,
            row: row,
            checked: $this.is(':checked')
        });
        
        // FIXED: Extract ID from debug string first
        let actualId = 0;
        if (debug) {
            const parts = debug.split('|');
            if (parts.length > 0) {
                const debugId = parts[0];
                actualId = parseInt(debugId);
                console.log("Parsed ID from debug string:", actualId);
            }
        }
        
        // FIXED: If debug ID is valid, use it regardless of value
        if (!isNaN(actualId) && actualId > 0) {
            ids.push(actualId);
            console.log("✅ Valid ID added from debug:", actualId);
        } 
        // FIXED: If value is valid, use it
        else if (value && !isNaN(value) && parseInt(value) > 0) {
            actualId = parseInt(value);
            ids.push(actualId);
            console.log("✅ Valid ID added from value:", actualId);
        }
        // FIXED: Last resort - use row number
        else if (row > 0) {
            actualId = row;
            ids.push(actualId);
            console.log("✅ Using row number as ID:", actualId);
        }
        else {
            console.log("❌ Invalid ID skipped. Value:", value, "Debug:", debug, "Row:", row);
        }
    });
    
    console.log("Final IDs array:", ids);
    console.log("=== Ending pickIds ===");
    
    if(ids.length === 0){
        toast('⚠️ No items selected');
        return false;
    }
    
    return ids.join(',');
}

// Bulk actions - ADDED DEBUGGING
function doExtend(){
    console.log("=== EXTEND ACTION ===");
    const ids = pickIds(); 
    if(!ids) return;
    
    console.log("Sending extend request with IDs:", ids);
    
    $.post('ajax_bulk_action.php', {
        action: 'extend', 
        ids: ids,
        days: 30
    }, function(res){
        console.log("Extend response:", res);
        if(res.success) { 
            toast('✅ '+res.message); 
            setTimeout(()=>location.reload(),700); 
        } else {
            toast('❌ '+res.error);
        }
    }, 'json').fail(function(xhr, status, error){
        console.log("Extend request failed:", error);
        toast('❌ Request failed');
    });
}

function doRenew(){
    console.log("=== RENEW ACTION ===");
    const ids = pickIds(); 
    if(!ids) return;
    
    console.log("Sending renew request with IDs:", ids);
    
    $.post('ajax_bulk_action.php', {
        action: 'renew', 
        ids: ids
    }, function(res){
        console.log("Renew response:", res);
        if(res.success) { 
            toast('✅ '+res.message); 
            setTimeout(()=>location.reload(),700); 
        } else {
            toast('❌ '+res.error);
        }
    }, 'json').fail(function(xhr, status, error){
        console.log("Renew request failed:", error);
        toast('❌ Request failed');
    });
}

function doRefund(){
    console.log("=== REFUND ACTION ===");
    const ids = pickIds(); 
    if(!ids) return;
    
    console.log("Sending refund request with IDs:", ids);
    
    $.post('ajax_bulk_action.php', {
        action: 'refund', 
        ids: ids
    }, function(res){
        console.log("Refund response:", res);
        if(res.success) { 
            toast('✅ '+res.message); 
            setTimeout(()=>location.reload(),700); 
        } else {
            toast('❌ '+res.error);
        }
    }, 'json').fail(function(xhr, status, error){
        console.log("Refund request failed:", error);
        toast('❌ Request failed');
    });
}

function doEdit(){
    console.log("=== EDIT ACTION ===");
    const ids = pickIds(); 
    if(!ids) return;
    
    const idArray = ids.split(',');
    console.log("Edit - IDs array:", idArray, "Length:", idArray.length);
    
    if(idArray.length !== 1){ 
        toast('Select exactly 1 item'); 
        return; 
    }
    
    const kw = prompt('New keyword:'); 
    if(!kw) return;
    
    console.log("Sending edit request with ID:", ids, "keyword:", kw);
    
    $.post('ajax_bulk_action.php', {
        action: 'edit', 
        id: ids,
        keyword: kw
    }, function(res){
        console.log("Edit response:", res);
        if(res.success) { 
            toast('✅ '+res.message); 
            setTimeout(()=>location.reload(),700); 
        } else {
            toast('❌ '+res.error);
        }
    }, 'json').fail(function(xhr, status, error){
        console.log("Edit request failed:", error);
        toast('❌ Request failed');
    });
}

function toggleRenew(id, on){
    console.log("Toggle renew for ID:", id, "status:", on);
    
    $.post('ajax_bulk_action.php', {
        action: 'toggle_renew', 
        id: id, 
        status: on ? 1 : 0
    }, function(res){
        console.log("Toggle renew response:", res);
        if(res.success) {
            toast('✅ '+res.message); 
        } else {
            toast('❌ '+res.error || 'Update failed');
        }
    }, 'json').fail(function(xhr, status, error){
        console.log("Toggle renew request failed:", error);
        toast('❌ Request failed');
    });
}

// Debug function - ENHANCED
function debugCheckboxes(){
    console.log("=== DEBUG CHECKBOXES ===");
    console.log("Total .rowCheck elements:", $('.rowCheck').length);
    console.log("Checked .rowCheck elements:", $('.rowCheck:checked').length);
    
    $('.rowCheck').each(function(i){
        const $this = $(this);
        const debug = $this.data('debug');
        let debugId = 0;
        
        if (debug) {
            const parts = debug.split('|');
            if (parts.length > 0) {
                debugId = parseInt(parts[0]);
            }
        }
        
        console.log("Checkbox " + i + ":", {
            value: $this.val(),
            debug: debug,
            debugId: debugId,
            row: $this.data('row'),
            domain: $this.data('domain'),
            checked: $this.is(':checked')
        });
    });
    
    // Test pickIds function
    console.log("=== TESTING pickIds ===");
    const testIds = pickIds();
    console.log("pickIds result:", testIds);
}
</script>
</body>
</html>