<?php
// receiver.php (তোমার রিমোট সাইটে)
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

// verify api_key
if (($data['api_key'] ?? '') !== 'YOUR_SECRET_KEY') {
  echo json_encode(['ok'=>0,'msg'=>'Invalid API key']); exit;
}

// TODO: এখানে তোমার link placement লজিক করো (file write / DB insert)
echo json_encode(['ok'=>1,'msg'=>'Link placed successfully']);
