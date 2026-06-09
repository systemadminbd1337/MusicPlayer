<?php
// ============================================================
// ajax_bulk_action.php — USER Panel (FIXED VERSION)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
include "config.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ---------------- Debug Logging Function ----------------
function log_credit_debug($message) {
    $log_file = __DIR__ . '/credit_error.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// ---------------- Auth ----------------
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']); exit;
}
$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
$uid  = (int)($user->id ?? $user->uid ?? $user->user_id ?? 0);
if ($uid <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid session']); exit; }

// ---------------- Helpers ----------------
function safe_val($sql, $def = 0){
    global $db;
    try { $v = $db->get_var($sql); return $v ?? $def; }
    catch(Throwable $e){ error_log("safe_val err: ".$e->getMessage()); return $def; }
}
function safe_rows($sql){
    global $db;
    try { return $db->get_results($sql); }
    catch(Throwable $e){ error_log("safe_rows err: ".$e->getMessage()); return []; }
}
function clean_ids($mixed){
    if (is_string($mixed)) $arr = explode(',', $mixed);
    elseif (is_array($mixed)) $arr = $mixed; else $arr = [];
    $arr = array_map('intval', $arr);
    return array_values(array_filter(array_unique($arr), fn($x)=>$x>0));
}
function db_escape($v){
    global $db;
    if (is_null($v)) return 'NULL';
    if (is_object($db) && method_exists($db,'escape')) return $db->escape((string)$v);
    if (is_object($db) && method_exists($db,'escape_str')) return $db->escape_str((string)$v);
    return addslashes((string)$v);
}

// ---------------- Force kredi column since it has credits ----------------
$creditCol = 'kredi';
$currentCredits = (float) safe_val("SELECT COALESCE(kredi,0) FROM k_users WHERE id='{$uid}'", 0);

log_credit_debug("=== CREDIT SYSTEM ===");
log_credit_debug("User ID: {$uid}");
log_credit_debug("Credit Column: {$creditCol}");
log_credit_debug("Current Credits: {$currentCredits}");

$action = $_POST['action'] ?? '';
log_credit_debug("Action requested: {$action}");

// Wrap whole logic in try/catch
try {

    // ---------------- toggle_renew (single) ----------------
    if ($action === 'toggle_renew') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }

        $own = (int) safe_val("SELECT COUNT(*) FROM k_orders WHERE id='{$id}' AND uid='{$uid}'", 0);
        if ($own === 0) { echo json_encode(['success'=>false,'error'=>'Access denied']); exit; }

        $db->query("UPDATE k_orders SET auto_renew='{$status}' WHERE id='{$id}' AND uid='{$uid}'");
        $new = (int) safe_val("SELECT COALESCE(auto_renew,0) FROM k_orders WHERE id='{$id}' AND uid='{$uid}'", 0);
        if ($new === $status) {
            echo json_encode(['success'=>true, 'message' => $status ? 'Auto-renew enabled' : 'Auto-renew disabled']);
        } else {
            echo json_encode(['success'=>false,'error'=>'Failed to persist auto_renew (DB update did not take)']);
        }
        exit;
    }

    // ---------------- edit_keyword_url (Bulk keyword AND target URL update) ----------------
    if ($action === 'edit_keyword_url') {
        $keyword = trim((string)($_POST['keyword'] ?? ''));
        $target_url = trim((string)($_POST['target_url'] ?? ''));
        
        if ($keyword === '' && $target_url === '') { 
            echo json_encode(['success'=>false,'error'=>'Please enter either keyword or target URL']); 
            exit; 
        }

        $raw_ids = $_POST['ids'] ?? '';
        $ids = clean_ids($raw_ids);
        if (empty($ids)) {
            echo json_encode(['success'=>false,'error'=>'No valid items selected']); 
            exit;
        }
        $idsList = implode(',', $ids);

        $ownedCount = (int) safe_val("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)", 0);
        if ($ownedCount !== count($ids)) {
            echo json_encode(['success'=>false,'error'=>'One or more items not owned by you']); 
            exit;
        }

        $updates = [];
        if ($keyword !== '') {
            $keyword_e = db_escape($keyword);
            $updates[] = "keyword='{$keyword_e}'";
        }
        if ($target_url !== '') {
            $target_url_e = db_escape($target_url);
            $updates[] = "target_url='{$target_url_e}'";
        }

        if (!empty($updates)) {
            $update_query = "UPDATE k_orders SET " . implode(', ', $updates) . " WHERE uid='{$uid}' AND id IN ($idsList)";
            $update_result = $db->query($update_query);
            
            if ($update_result) {
                $message = "Updated ";
                if ($keyword !== '') $message .= "keyword";
                if ($keyword !== '' && $target_url !== '') $message .= " and ";
                if ($target_url !== '') $message .= "target URL";
                $message .= " for {$ownedCount} link(s)";
                
                echo json_encode(['success'=>true, 'message' => $message]);
            } else {
                echo json_encode(['success'=>false,'error'=>'Database update failed']);
            }
        }
        exit;
    }

    // ---------------- edit_single_link (Single link keyword AND target URL update) ----------------
    if ($action === 'edit_single_link') {
        $id = (int)($_POST['id'] ?? 0);
        $keyword = trim((string)($_POST['keyword'] ?? ''));
        $target_url = trim((string)($_POST['target_url'] ?? ''));
        
        if (!$id) {
            echo json_encode(['success'=>false,'error'=>'Invalid link ID']); 
            exit;
        }
        
        if ($keyword === '' && $target_url === '') { 
            echo json_encode(['success'=>false,'error'=>'Please enter either keyword or target URL']); 
            exit; 
        }

        $own = (int) safe_val("SELECT COUNT(*) FROM k_orders WHERE id='{$id}' AND uid='{$uid}'", 0);
        if ($own === 0) {
            echo json_encode(['success'=>false,'error'=>'Access denied or link not found']); 
            exit;
        }

        $updates = [];
        if ($keyword !== '') {
            $keyword_e = db_escape($keyword);
            $updates[] = "keyword='{$keyword_e}'";
        }
        if ($target_url !== '') {
            $target_url_e = db_escape($target_url);
            $updates[] = "target_url='{$target_url_e}'";
        }

        if (!empty($updates)) {
            $update_query = "UPDATE k_orders SET " . implode(', ', $updates) . " WHERE id='{$id}' AND uid='{$uid}'";
            $update_result = $db->query($update_query);
            
            if ($update_result) {
                $message = "Updated link successfully";
                if ($keyword !== '') $message .= " - Keyword: {$keyword}";
                if ($target_url !== '') $message .= " - URL: " . (strlen($target_url) > 30 ? substr($target_url, 0, 30) . '...' : $target_url);
                
                echo json_encode(['success'=>true, 'message' => $message]);
            } else {
                echo json_encode(['success'=>false,'error'=>'Database update failed']);
            }
        }
        exit;
    }

    // ---------------- Bulk IDs common init ----------------
    $ids = clean_ids($_POST['ids'] ?? '');
    if (empty($ids)) {
        // For actions that require ids, we will error later
    }

    // ---------------- Ownership validation for bulk actions ----------------
    if (!empty($ids)) {
        $idsList = implode(',', $ids);
        $ownedCount = (int) safe_val("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)", 0);
        if ($ownedCount === 0) {
            echo json_encode(['success'=>false,'error'=>'Access denied or invalid orders']); exit;
        }
    }

    // ---------------- Actions ----------------
    switch ($action) {

        // --------- Extend (bulk) ---------
        case 'extend': {
            $ids = clean_ids($_POST['ids'] ?? '');
            if (empty($ids)) { echo json_encode(['success'=>false,'error'=>'No valid items selected for extend']); exit; }
            $idsList = implode(',', $ids);
            $days = (int)($_POST['days'] ?? 30);

            if ($days <= 0) { 
                echo json_encode(['success'=>false,'error'=>'Invalid days value']); exit; 
            }

            // FIX: Prevent wrong expiry dates by limiting days
            if ($days > 365) {
                $days = 30; // Force maximum 30 days for safety
            }

            $ownedCount = (int) safe_val("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)", 0);
            if ($ownedCount === 0) { echo json_encode(['success'=>false,'error'=>'No owned items found']); exit; }

            // Cost calculation: 1 credit per 30 days per item
            $costPerLink = ceil($days / 30);
            $need = $costPerLink * $ownedCount;

            log_credit_debug("=== EXTEND ACTION ===");
            log_credit_debug("Items to extend: {$ownedCount}");
            log_credit_debug("Days: {$days}");
            log_credit_debug("Cost Per Link: {$costPerLink}");
            log_credit_debug("Total Credits Needed: {$need}");
            log_credit_debug("Current Credits: {$currentCredits}");
            
            if ($currentCredits < $need) {
                log_credit_debug("ERROR: Insufficient credits");
                echo json_encode(['success'=>false,'error'=>"Insufficient credits: need {$need}, have {$currentCredits}"]); exit;
            }

            // deduct credits
            $deduct_result = $db->query("UPDATE k_users SET kredi = GREATEST(0, kredi - {$need}) WHERE id='{$uid}'");
            log_credit_debug("Deduction result: " . ($deduct_result ? 'SUCCESS' : 'FAILED'));

            // Extend expiry_date with additional safety checks
            $orders = safe_rows("SELECT id, expiry_date FROM k_orders WHERE id IN ($idsList)");
            $successCount = 0;
            
            foreach ($orders as $order) {
                $order_id = (int)$order->id;
                $current_expiry = $order->expiry_date;
                
                // FIX: Additional safety check for current expiry date
                if (empty($current_expiry) || $current_expiry == '0000-00-00' || strtotime($current_expiry) > strtotime('+60 days')) {
                    // If expiry date is invalid or too far in future, start from current date
                    $new_expiry = date('Y-m-d', strtotime("+{$days} days"));
                } else {
                    // Normal extension from current expiry date
                    $new_expiry = date('Y-m-d', strtotime("{$current_expiry} +{$days} days"));
                }
                
                // FIX: Final safety check - don't allow expiry dates too far in future
                if (strtotime($new_expiry) > strtotime('+400 days')) {
                    $new_expiry = date('Y-m-d', strtotime('+30 days'));
                }
                
                $update_result = $db->query("UPDATE k_orders SET expiry_date = '{$new_expiry}' WHERE id = '{$order_id}'");
                if ($update_result) {
                    $successCount++;
                    log_credit_debug("Extended order {$order_id}: {$current_expiry} -> {$new_expiry}");
                }
            }

            $newCredit = (float) safe_val("SELECT COALESCE(kredi,0) FROM k_users WHERE id='{$uid}'", 0);
            log_credit_debug("New credit balance: {$newCredit}");
            log_credit_debug("Successfully extended: {$successCount} orders");

            if ($successCount > 0) {
                echo json_encode([
                    'success'=>true,
                    'message'=> "Extended {$successCount} link(s) by {$days} days. Cost: {$need} CR",
                    'new_credit'=>$newCredit
                ]);
            } else {
                // Refund credits if failed
                $db->query("UPDATE k_users SET kredi = kredi + {$need} WHERE id='{$uid}'");
                $newCredit = (float) safe_val("SELECT COALESCE(kredi,0) FROM k_users WHERE id='{$uid}'", 0);
                echo json_encode(['success'=>false,'error'=>'Failed to extend links','new_credit'=>$newCredit]);
            }
            break;
        }

        // --------- Renew (bulk) - NO credit deduction ---------
        case 'renew': {
            $ids = clean_ids($_POST['ids'] ?? '');
            if (empty($ids)) { echo json_encode(['success'=>false,'error'=>'No valid items selected for renew']); exit; }
            $idsList = implode(',', $ids);
            $ownedCount = (int) safe_val("SELECT COUNT(*) FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)", 0);
            if ($ownedCount === 0) { echo json_encode(['success'=>false,'error'=>'No owned items found']); exit; }

            // Renew by adding 30 days to expiry_date
            $orders = safe_rows("SELECT id, expiry_date FROM k_orders WHERE id IN ($idsList)");
            $successCount = 0;
            
            foreach ($orders as $order) {
                $order_id = (int)$order->id;
                $current_expiry = $order->expiry_date;
                
                // FIX: Safety check for current expiry date
                if (empty($current_expiry) || $current_expiry == '0000-00-00' || strtotime($current_expiry) > strtotime('+60 days')) {
                    $new_expiry = date('Y-m-d', strtotime("+30 days"));
                } else {
                    $new_expiry = date('Y-m-d', strtotime("{$current_expiry} +30 days"));
                }
                
                // FIX: Final safety check
                if (strtotime($new_expiry) > strtotime('+400 days')) {
                    $new_expiry = date('Y-m-d', strtotime('+30 days'));
                }
                
                $update_result = $db->query("UPDATE k_orders SET expiry_date = '{$new_expiry}' WHERE id = '{$order_id}'");
                if ($update_result) {
                    $successCount++;
                }
            }

            $newCredit = (float) safe_val("SELECT COALESCE(kredi,0) FROM k_users WHERE id='{$uid}'", 0);
            
            if ($successCount > 0) {
                echo json_encode(['success'=>true,'message'=>"$successCount link(s) renewed (30 days) — no credits deducted",'new_credit'=>$newCredit]);
            } else {
                echo json_encode(['success'=>false,'error'=>'Failed to renew links','new_credit'=>$newCredit]);
            }
            break;
        }

        // --------- Refund (bulk) ---------
        case 'refund': {
            $ids = clean_ids($_POST['ids'] ?? '');
            if (empty($ids)) { echo json_encode(['success'=>false,'error'=>'No valid items selected for refund']); exit; }
            $idsList = implode(',', $ids);

            $sum = (float) safe_val("SELECT COALESCE(SUM(credit),0) FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)", 0);
            $lid_rows = safe_rows("SELECT DISTINCT lid FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)");
            $lids = array_map(fn($r)=>(int)$r->lid,$lid_rows);
            $lidList = $lids ? implode(',', $lids) : '';

            $db->query("DELETE FROM k_orders WHERE uid='{$uid}' AND id IN ($idsList)");
            if ($sum > 0) $db->query("UPDATE k_users SET kredi = kredi + {$sum} WHERE id='{$uid}'");
            if ($lidList) {
                $db->query("DELETE FROM k_linkdb WHERE id IN ($lidList) AND NOT EXISTS (SELECT 1 FROM k_orders x WHERE x.lid=k_linkdb.id)");
            }
            $newCredit = (float) safe_val("SELECT COALESCE(kredi,0) FROM k_users WHERE id='{$uid}'", 0);
            echo json_encode(['success'=>true,'message'=>"Refunded orders: {$ownedCount} (+{$sum} CR)",'new_credit'=>$newCredit]);
            break;
        }

        default:
            echo json_encode(['success'=>false,'error'=>'Unknown action']);
    }

} catch (Throwable $e) {
    $error_msg = 'ajax_bulk_action error: '.$e->getMessage();
    error_log($error_msg);
    log_credit_debug("EXCEPTION: {$error_msg}");
    echo json_encode(['success'=>false,'error'=>'Server error']);
}

log_credit_debug("=== PROCESS COMPLETED ===\n");
?>