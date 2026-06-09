<?php
// cron_place_links.php
// Run from CLI / cron: php /path/to/cron_place_links.php
// Requires: config.php providing $pdo (or adjusts below to use $db if you prefer ezSQL)

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Path to your config
require_once __DIR__ . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // try ezSQL $db fallback
    global $db;
    if (!isset($db)) {
        error_log("DB connection not found. Exiting.");
        exit(1);
    }
}

// SETTINGS
$BATCH_LIMIT = 25;               // how many pending orders to process per run
$HTTP_TIMEOUT = 12;              // curl timeout seconds
$MAX_ATTEMPTS = 5;               // max attempts per order before giving up
$USER_AGENT = 'PlacementBot/1.0';

// Helper to fetch pending orders that target sites have placement_api_url set
function fetch_pending_orders($limit = 20) {
    global $pdo, $db;
    // We want orders that are pending and their linkdb row has placement_api_url (non-null)
    if (isset($pdo) && ($pdo instanceof PDO)) {
        $sql = "
            SELECT o.id AS order_id, o.uid, o.lid AS linkdb_id, o.tarih, o.target_url, o.anchor_text, p.placement_api_url, p.placement_api_key, p.domain
            FROM k_orders o
            JOIN k_linkdb p ON p.id = o.lid
            WHERE COALESCE(o.status, 'pending') = 'pending'
              AND p.placement_api_url IS NOT NULL
              AND p.placement_api_url <> ''
            ORDER BY o.id ASC
            LIMIT :lim
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lim'=>$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // ezSQL fallback
        $rows = $db->get_results("SELECT o.id AS order_id, o.uid, o.lid AS linkdb_id, o.tarih, o.target_url, o.anchor_text, p.placement_api_url, p.placement_api_key, p.domain
            FROM k_orders o
            JOIN k_linkdb p ON p.id = o.lid
            WHERE COALESCE(o.status, 'pending') = 'pending'
              AND p.placement_api_url IS NOT NULL
              AND p.placement_api_url <> ''
            ORDER BY o.id ASC
            LIMIT {$limit}", OBJECT);
        // convert to array
        $out = [];
        foreach($rows as $r) $out[] = (array)$r;
        return $out;
    }
}

// Curl POST helper
function do_post_json($url, $payload, $headers = [], $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $hdrs = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PlacementBot/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // set true in prod and ensure valid certs
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body'=>$resp, 'info'=>$info, 'error'=>$err];
}

// Insert placement log
function insert_placement_log($data) {
    global $pdo, $db;
    $defaults = [
        'order_id'=>0,'linkdb_id'=>0,'uid'=>0,'domain'=>null,'target_url'=>null,'anchor_text'=>null,
        'api_url'=>null,'api_key_used'=>null,'response_code'=>null,'response_body'=>null,'attempts'=>0
    ];
    $d = array_merge($defaults,$data);

    if (isset($pdo) && ($pdo instanceof PDO)) {
        $sql = "INSERT INTO k_link_placements
            (order_id, linkdb_id, uid, domain, target_url, anchor_text, api_url, api_key_used, response_code, response_body, attempts, last_attempt_at, created_at)
            VALUES (:order_id,:linkdb_id,:uid,:domain,:target_url,:anchor_text,:api_url,:api_key_used,:response_code,:response_body,:attempts,NOW(),NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':order_id'=>$d['order_id'], ':linkdb_id'=>$d['linkdb_id'], ':uid'=>$d['uid'],
            ':domain'=>$d['domain'], ':target_url'=>$d['target_url'], ':anchor_text'=>$d['anchor_text'],
            ':api_url'=>$d['api_url'], ':api_key_used'=>$d['api_key_used'], ':response_code'=>$d['response_code'],
            ':response_body'=>substr($d['response_body'] ?? '', 0, 2000), ':attempts'=>$d['attempts']
        ]);
    } else {
        $db->query("INSERT INTO k_link_placements (order_id, linkdb_id, uid, domain, target_url, anchor_text, api_url, api_key_used, response_code, response_body, attempts, last_attempt_at, created_at)
                   VALUES ('{$d['order_id']}','{$d['linkdb_id']}','{$d['uid']}','".addslashes($d['domain'])."','".addslashes($d['target_url'])."','".addslashes($d['anchor_text'])."','".addslashes($d['api_url'])."','".addslashes($d['api_key_used'])."','{$d['response_code']}','".addslashes($d['response_body'])."','{$d['attempts']}',NOW(),NOW())");
    }
}

// Update order status safely
function update_order_status($order_id, $status) {
    global $pdo, $db;
    if (isset($pdo) && ($pdo instanceof PDO)) {
        $stmt = $pdo->prepare("UPDATE k_orders SET status = :st WHERE id = :id");
        $stmt->execute([':st'=>$status, ':id'=>$order_id]);
    } else {
        $db->query("UPDATE k_orders SET status='{$status}' WHERE id=".((int)$order_id));
    }
}

// MAIN
$pending = fetch_pending_orders($BATCH_LIMIT);
if (!$pending) {
    echo "[".date('c')."] No pending placement orders with API configured.\n";
    exit(0);
}

foreach ($pending as $row) {
    $order_id = (int)$row['order_id'];
    $uid = (int)$row['uid'];
    $linkdb_id = (int)$row['linkdb_id'];
    $domain = $row['domain'] ?? null;
    $api_url = $row['placement_api_url'] ?? null;
    $api_key = $row['placement_api_key'] ?? null;

    // get target_url and anchor_text from k_orders (if not included)
    if (empty($row['target_url']) || empty($row['anchor_text'])) {
        if (isset($pdo) && ($pdo instanceof PDO)) {
            $stmt = $pdo->prepare("SELECT target_url, anchor_text, attempts FROM k_orders WHERE id = :id LIMIT 1");
            $stmt->execute([':id'=>$order_id]);
            $ord = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $ord = $db->get_row("SELECT target_url, anchor_text, attempts FROM k_orders WHERE id={$order_id}", ARRAY_A);
        }
        $target_url = $ord['target_url'] ?? ($row['target_url'] ?? '');
        $anchor = $ord['anchor_text'] ?? ($row['anchor_text'] ?? '');
        $attempts = isset($ord['attempts']) ? (int)$ord['attempts'] : 0;
    } else {
        $target_url = $row['target_url'];
        $anchor = $row['anchor_text'];
        $attempts = 0;
    }

    // safety checks
    if (!$api_url || !$target_url) {
        // cannot place: missing info — mark failed and log
        insert_placement_log([
            'order_id'=>$order_id,'linkdb_id'=>$linkdb_id,'uid'=>$uid,'domain'=>$domain,
            'target_url'=>$target_url,'anchor_text'=>$anchor,'api_url'=>$api_url,'api_key_used'=>$api_key,
            'response_code'=>0,'response_body'=>'Missing api_url or target_url','attempts'=>$attempts+1
        ]);
        // increment attempts on order, if exists
        if (isset($pdo) && ($pdo instanceof PDO)) {
            $pdo->prepare("UPDATE k_orders SET attempts = COALESCE(attempts,0)+1 WHERE id = :id")->execute([':id'=>$order_id]);
        } else {
            $db->query("UPDATE k_orders SET attempts = COALESCE(attempts,0)+1 WHERE id = {$order_id}");
        }
        continue;
    }

    // build payload — JSON
    $payload = [
        'target_url' => $target_url,
        'anchor_text' => $anchor,
        'order_id' => $order_id,
        'source' => 'your-platform', // optional
    ];
    if ($api_key) $payload['api_key'] = $api_key;

    // do request
    $res = do_post_json($api_url, $payload, [], $HTTP_TIMEOUT);
    $http_code = (int)($res['info']['http_code'] ?? 0);
    $resp_body = $res['body'] ?? '';
    $attempts++;

    // Log response
    insert_placement_log([
        'order_id'=>$order_id,'linkdb_id'=>$linkdb_id,'uid'=>$uid,'domain'=>$domain,
        'target_url'=>$target_url,'anchor_text'=>$anchor,'api_url'=>$api_url,'api_key_used'=>($api_key?substr($api_key,0,8):null),
        'response_code'=>$http_code,'response_body'=>$resp_body,'attempts'=>$attempts
    ]);

    // Check success: we expect remote endpoint to return JSON {ok:1} or 200 status
    $success = false;
    if ($http_code >= 200 && $http_code < 300) {
        // try parse JSON
        $json = json_decode($resp_body, true);
        if (is_array($json) && (isset($json['ok']) && $json['ok'])) {
            $success = true;
        } else {
            // also accept simple "OK"
            if (stripos($resp_body, 'ok') !== false || stripos($resp_body, 'success') !== false) $success = true;
        }
    }

    if ($success) {
        // mark order placed
        update_order_status($order_id, 'placed');
        echo "[".date('c')."] Order {$order_id} placed on {$domain} (code {$http_code}).\n";
    } else {
        // increment attempt count and maybe mark failed if exceeded
        if (isset($pdo) && ($pdo instanceof PDO)) {
            $pdo->prepare("UPDATE k_orders SET attempts = COALESCE(attempts,0)+1 WHERE id = :id")->execute([':id'=>$order_id]);
            $stmt = $pdo->prepare("SELECT attempts FROM k_orders WHERE id = :id LIMIT 1");
            $stmt->execute([':id'=>$order_id]);
            $rowa = $stmt->fetch(PDO::FETCH_ASSOC);
            $curAttempts = (int)($rowa['attempts'] ?? 0);
        } else {
            $db->query("UPDATE k_orders SET attempts = COALESCE(attempts,0)+1 WHERE id = {$order_id}");
            $curAttempts = (int)$db->get_var("SELECT attempts FROM k_orders WHERE id={$order_id}");
        }

        if ($curAttempts >= $MAX_ATTEMPTS) {
            update_order_status($order_id, 'placement_failed');
            echo "[".date('c')."] Order {$order_id} failed after {$curAttempts} attempts.\n";
        } else {
            echo "[".date('c')."] Order {$order_id} attempt {$curAttempts} failed (HTTP {$http_code}).\n";
        }
    }

    // small sleep to avoid hammering
    usleep(200000); // 0.2s
}

echo "[".date('c')."] Done.\n";
