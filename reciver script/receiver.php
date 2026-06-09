<?php
/**
 * receiver.php — Remote Auto Link Placement Script
 * 
 * Deploy this file in root directory of partner sites.
 * Example: https://partner-site.com/receiver.php
 * 
 * It receives POST JSON like:
 * {
 *   "domain": "partner-site.com",
 *   "target_url": "https://yoursite.com/page",
 *   "keyword": "best seo service",
 *   "order_id": 123
 * }
 * 
 * Then automatically inserts <a href="target_url">keyword</a>
 * into a pre-defined HTML template or target file.
 */

// ---------------- CONFIG ----------------
$logFile = __DIR__ . '/receiver_log.txt';      // logs every action
$contentFile = __DIR__ . '/index.html';        // where link will be inserted
$secretKey = 'CHANGE_THIS_SECRET_KEY';         // same secret key must match with your main panel

// ---------------- BASIC SECURITY ----------------
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>0,'msg'=>'Only POST allowed']);
    exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['domain'],$data['target_url'],$data['keyword'])) {
    echo json_encode(['ok'=>0,'msg'=>'Invalid JSON body']);
    exit;
}

// Optional header auth (match secret key)
$auth = $_SERVER['HTTP_X_RECEIVER_KEY'] ?? '';
if ($auth !== $secretKey) {
    echo json_encode(['ok'=>0,'msg'=>'Unauthorized']);
    exit;
}

// ---------------- VALIDATE INPUT ----------------
$domain = trim($data['domain']);
$target = trim($data['target_url']);
$keyword = trim($data['keyword']);
$orderId = (int)($data['order_id'] ?? 0);

if (!filter_var($target, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok'=>0,'msg'=>'Invalid target URL']);
    exit;
}
if (strlen($keyword) < 2) {
    echo json_encode(['ok'=>0,'msg'=>'Keyword too short']);
    exit;
}

// ---------------- INSERT LINK ----------------
try {
    if (!file_exists($contentFile)) {
        file_put_contents($contentFile, "<html><body><h1>Welcome to $domain</h1><p>No links yet.</p></body></html>");
    }

    $html = file_get_contents($contentFile);
    $linkTag = '<a href="'.htmlspecialchars($target).'" target="_blank" rel="nofollow">'.htmlspecialchars($keyword).'</a>';

    // Smart insert: before closing </body>
    if (stripos($html, '</body>') !== false) {
        $html = str_ireplace('</body>', $linkTag."\n</body>", $html);
    } else {
        $html .= "\n".$linkTag;
    }

    file_put_contents($contentFile, $html);

    // Log result
    $log = date('Y-m-d H:i:s')." | {$domain} | {$target} | {$keyword} | order={$orderId}\n";
    file_put_contents($logFile, $log, FILE_APPEND);

    echo json_encode(['ok'=>1,'msg'=>'Link placed successfully','domain'=>$domain]);
} catch (Throwable $e) {
    file_put_contents($logFile, "ERROR ".$e->getMessage()."\n", FILE_APPEND);
    echo json_encode(['ok'=>0,'msg'=>'Server error: '.$e->getMessage()]);
}
