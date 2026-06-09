<?php
// =====================================================================
// admin/ajax_bulk_action.php — Bulk Actions for Admin Panel (Systemadminbd)
// =====================================================================
require_once __DIR__ . '/_bootstrap.php'; // use admin bootstrap

header('Content-Type: application/json; charset=utf-8');

// ✅ Admin only
if (($user->role ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>0,'msg'=>'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';
$idsRaw = $_POST['ids'] ?? '[]';
$extra  = $_POST['extra'] ?? '';

$allowed = ['delete','extend','renew','refund','edit'];
if (!in_array($action, $allowed, true)) {
    echo json_encode(['ok'=>0,'msg'=>'Invalid action']);
    exit;
}

$ids = json_decode($idsRaw, true);
if (!is_array($ids) || empty($ids)) {
    echo json_encode(['ok'=>0,'msg'=>'No IDs provided']);
    exit;
}
$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($v)=>$v>0);
if (empty($ids)) {
    echo json_encode(['ok'=>0,'msg'=>'Invalid ID list']);
    exit;
}
$in = implode(',', $ids);

// ensure $db available
global $db;
if (!isset($db) || !is_object($db)) {
    echo json_encode(['ok'=>0,'msg'=>'Database not initialized']);
    exit;
}

try {
    // 🔹 Delete
    if ($action === 'delete') {
        $db->query("DELETE FROM k_orders WHERE id IN ($in)");
        echo json_encode(['ok'=>1,'msg'=>'✅ Deleted selected orders']);
        exit;
    }

    // 🔹 Extend (add days)
    if ($action === 'extend') {
        $days = (int)$extra;
        if ($days <= 0) $days = 30;
        $db->query("UPDATE k_orders SET end_date = DATE_ADD(end_date, INTERVAL $days DAY) WHERE id IN ($in)");
        echo json_encode(['ok'=>1,'msg'=>"✅ Extended by {$days} days"]);
        exit;
    }

    // 🔹 Enable Auto Renewal
    if ($action === 'renew') {
        $db->query("UPDATE k_orders SET auto_renewal = 1 WHERE id IN ($in)");
        echo json_encode(['ok'=>1,'msg'=>'✅ Auto-renewal enabled']);
        exit;
    }

    // 🔹 Refund (mark refunded=1)
    if ($action === 'refund') {
        try {
            $db->query("UPDATE k_orders SET refunded = 1 WHERE id IN ($in)");
            echo json_encode(['ok'=>1,'msg'=>'✅ Marked as refunded']);
        } catch (Throwable $e) {
            echo json_encode(['ok'=>0,'msg'=>'Refund failed: missing column']);
        }
        exit;
    }

    // 🔹 Edit placeholder
    if ($action === 'edit') {
        echo json_encode(['ok'=>1,'msg'=>'🧩 Bulk edit UI coming soon']);
        exit;
    }

    echo json_encode(['ok'=>0,'msg'=>'Unhandled action']);
} catch (Throwable $e) {
    error_log("bulk_action error: ".$e->getMessage());
    echo json_encode(['ok'=>0,'msg'=>'Server error']);
    exit;
}
