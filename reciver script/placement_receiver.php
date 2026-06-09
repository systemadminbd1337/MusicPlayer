<?php
header('Content-Type: application/json; charset=utf-8');
$SECRET = 'MYSECRET123'; // <-- তোমার key

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || ($data['api_key'] ?? '') !== $SECRET) {
    http_response_code(403);
    echo json_encode(['ok'=>0,'msg'=>'unauthorized']);
    exit;
}

$target = $data['target_url'] ?? '';
$anchor = $data['anchor_text'] ?? '';
$order  = $data['order_id'] ?? 0;

if (!$target) {
    http_response_code(400);
    echo json_encode(['ok'=>0,'msg'=>'missing target']);
    exit;
}

// শুধু demo—ফাইলের মধ্যে লিখে রাখে
file_put_contents(__DIR__.'/placed_links.txt',
    date('c')." | #$order | $anchor → $target\n",
    FILE_APPEND);

echo json_encode(['ok'=>1,'msg'=>'placed']);
