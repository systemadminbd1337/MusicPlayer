<?php
// placement_receiver.php
// Simple example of an endpoint that accepts POST JSON and places a link into a /links.html file (demo).
// DO NOT use this on third-party sites — only on sites you own.

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$SECRET = 'REPLACE_WITH_YOUR_SECRET_KEY'; // <-- set this per-site (keep secret)

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>0,'msg'=>'invalid_json']);
    exit;
}

// Basic auth via api_key (or header)
$api_key = $data['api_key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? null);
if (!$api_key || $api_key !== $SECRET) {
    http_response_code(403);
    echo json_encode(['ok'=>0,'msg'=>'unauthorized']);
    exit;
}

$target = trim($data['target_url'] ?? '');
$anchor = trim($data['anchor_text'] ?? '');
$order  = intval($data['order_id'] ?? 0);
if (!$target) {
    http_response_code(400);
    echo json_encode(['ok'=>0,'msg'=>'missing_target']);
    exit;
}

// Demo placement: append to a local links file — replace with real logic (WP API, DB insert, etc.)
$line = date('c') . " | order:{$order} | {$anchor} => {$target}\n";
$file = __DIR__ . '/placed_links.txt';
if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['ok'=>0,'msg'=>'write_failed']);
    exit;
}

// success
echo json_encode(['ok'=>1,'msg'=>'placed']);
exit;
