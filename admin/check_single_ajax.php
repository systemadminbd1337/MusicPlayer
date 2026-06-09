<?php
// admin/check_single_ajax.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/check_url.php';
header('Content-Type: application/json');

$url = trim($_POST['url'] ?? '');
$add = !empty($_POST['add']);
if(!$url || !filter_var($url, FILTER_VALIDATE_URL)){
  echo json_encode(['ok'=>false,'status'=>'INVALID_URL','url'=>$url]); exit;
}
try{
  $res = check_url($url, ['timeout'=>10, 'inspect_body'=>true]);
  if($add && isset($pdo)){
    $stmt = $pdo->prepare("INSERT IGNORE INTO monitored_urls (url,last_status,last_http_code,last_checked,last_etag,last_modified,last_hash)
      VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
      $url,
      $res['status'] ?? null,
      $res['http_code'] ?? null,
      date('Y-m-d H:i:s'),
      $res['etag'] ?? null,
      $res['last_modified'] ?? null,
      $res['content_hash'] ?? null
    ]);
  }
  echo json_encode($res);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'status'=>'ERROR','url'=>$url,'error'=>$e->getMessage()]);
}
