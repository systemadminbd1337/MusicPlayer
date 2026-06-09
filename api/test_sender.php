<?php
$url = 'https://hack-link.com/api/receiver.php';
$data = [
  'secret' => 'my_secret_key_12345',
  'operation' => 'place_link',
  'domain' => 'systemadminbd.com',
  'target_url' => 'https://mysite.com/article-123',
  'keyword' => 'best seo tips'
];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$out = curl_exec($ch);
$err = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "<h3>HTTP: {$http}</h3>";
if ($err) { echo "<pre>cURL error: {$err}</pre>"; }
echo "<pre>" . htmlspecialchars($out) . "</pre>";
