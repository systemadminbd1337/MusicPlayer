<?php
// monitor.php  (CLI or cron)
require 'config.php';   // provide $pdo (PDO)
require 'utils/check_url.php';

$rows = $pdo->query("SELECT * FROM monitored_urls")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $res = check_url($r['url'], ['timeout'=>12, 'inspect_body'=>true]);

    // map to DB-friendly status
    $http_code = $res['http_code'] ?? 0;
    $status = $res['status'] ?? 'ERROR';
    $hash = $res['content_hash'] ?? null;
    $etag = $res['etag'] ?? null;
    $lm = $res['last_modified'] ?? null;
    $snippet = $res['response_snippet'] ?? null;

    // insert history
    $stmt = $pdo->prepare("INSERT INTO url_checks (url_id,http_code,status,etag,last_modified,content_hash,response_snippet) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$r['id'],$http_code,$status,$etag,$lm,$hash,$snippet]);

    // update monitored_urls last info
    $u = $pdo->prepare("UPDATE monitored_urls SET last_http_code=?, last_status=?, last_checked=NOW(), last_etag=?, last_modified=?, last_hash=? WHERE id=?");
    $u->execute([$http_code,$status,$etag,$lm,$hash,$r['id']]);

    // decide if notify: example logic -> notify only if previous status exists and changed to NOT_FOUND/GONE/ERROR
    $prev = $r['last_status'];
    if ($prev && $prev !== $status) {
        $bad = in_array($status, ['NOT_FOUND','GONE','ERROR','SERVER_ERROR','NOT_FOUND_CUSTOM']);
        if ($bad && !empty($r['notify_email'])) {
            $to = $r['notify_email'];
            $sub = "[ALERT] URL status changed: {$r['url']} -> {$status}";
            $body = "Checked: {$r['url']}\nPrevious: {$prev}\nNow: {$status} (HTTP {$http_code})\n\nSnippet:\n{$snippet}\n\n--\nAutomated monitor";
            @mail($to,$sub,$body,"From: no-reply@{$_SERVER['HTTP_HOST']}");
        }
    }
}
