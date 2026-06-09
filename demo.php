<?php
// =========================================
// ✅ add_demo_orders_final.php
// Generates demo link + order safely
// =========================================
session_start();
include "config.php";
header('Content-Type: text/html; charset=utf-8');

// ---- Security: only for logged in users ----
if (empty($_SESSION['user'])) {
    echo "<h3 style='color:red'>❌ Not logged in</h3>";
    exit;
}
$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
$uid  = (int)($user->id ?? 0);

// ✅ Force demo to match main user ID for ownership
if ($uid === 10) { 
    $uid = 1; // temporary override for demo data
}
echo "<h2>🧩 Demo Data Generator (Active UID: {$uid})</h2>";

// --- Helper ---
function safe_val($sql,$def=0){ global $db;
  try{$v=$db->get_var($sql);return $v??$def;}catch(Throwable $e){return $def;}
}
function esc($v){ return addslashes(trim((string)$v)); }

try {
    global $db;
    
    // Step 1️⃣ — Insert into k_linkdb
    $domain = "demo-" . uniqid() . ".example.com";
    $link   = "https://example.com/page/" . rand(100,999);
    $keyword= "demo-keyword";

    $sql = "INSERT INTO k_linkdb
        (domain, keyword, link, credit, type, domain_age, added_date,
         alexa1, alexa2, alexa3, tip, ups, durum, created_at)
        VALUES
        ('{$domain}', '{$keyword}', '{$link}', 0, 'PHP', 5, NOW(),
         'a1', 'a2', 'a3', 0, 0, 1, NOW())";

    $ok = $db->query($sql);
    echo "<p>Insert into <b>k_linkdb</b>: " . var_export($ok, true) . "</p>";

    // Step 2️⃣ — Get last inserted ID
    $lid = 0;
    if (method_exists($db, 'insert_id')) {
        $lid = (int)$db->insert_id;
    } elseif (property_exists($db, 'dbh') && $db->dbh instanceof mysqli) {
        $lid = mysqli_insert_id($db->dbh);
    } else {
        $lid = (int)safe_val("SELECT MAX(id) FROM k_linkdb");
    }
    echo "<p>Detected new link ID (lid): <b>{$lid}</b></p>";

    // Step 3️⃣ — Insert into k_orders
    if ($lid > 0) {
        $sql2 = "INSERT INTO k_orders
            (uid, lid, target_url, anchor, credit, auto_renew, tarih, status)
            VALUES
            ('{$uid}', '{$lid}', 'https://target.example.com/test', 'demo', 5, 0, NOW(), 'pending')";
        $ok2 = $db->query($sql2);
        echo "<p>Insert into <b>k_orders</b>: " . var_export($ok2, true) . "</p>";

        $oid = (int)safe_val("SELECT MAX(id) FROM k_orders WHERE uid='{$uid}'");
        echo "<p>✅ Created order ID: <b>{$oid}</b></p>";
    } else {
        echo "<p style='color:red'>⚠️ No lid generated, skipping order creation.</p>";
    }

} catch (Throwable $e) {
    echo "<pre style='color:red'>❌ Exception: " . $e->getMessage() . "</pre>";
}

echo "<hr><p style='color:#10b981;font-weight:bold'>🎉 Done! Now open <b>my_links.php</b> to see your new demo link.</p>";
?>
