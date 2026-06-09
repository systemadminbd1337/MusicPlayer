 <?php
// inc/check_url.php
// Depends: curl extension enabled

function check_url(string $url, array $opts = []): array {
    // opts: ['timeout'=>int, 'user_agent'=>string, 'inspect_body'=>bool]
    $timeout = $opts['timeout'] ?? 10;
    $ua = $opts['user_agent'] ?? 'HackLinkChecker/1.0 (+localhost)';
    $inspectBody = $opts['inspect_body'] ?? true;

    // Prepare curl for HEAD first
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_SSL_VERIFYPEER => false, // local dev; set true in prod with certs
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $error = $errno ? curl_error($ch) : null;
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $etag = null;
    $last_modified = null;

    // parse headers via headerfunction? simpler: use getinfo('request_header') not reliable.
    // We'll re-run HEAD with CURLOPT_HEADER true if needed
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $hdrBody = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_text = substr($hdrBody, 0, $header_size);
    $body = substr($hdrBody, $header_size);
    curl_close($ch);

    // Extract ETag/Last-Modified
    if (preg_match('/ETag:\s*(.+)/i', $header_text, $m)) { $etag = trim($m[1]); }
    if (preg_match('/Last-Modified:\s*(.+)/i', $header_text, $m)) { $last_modified = trim($m[1]); }

    // If we only did HEAD and got 200 but body might be custom 404, optionally fetch body
    $content_hash = null;
    $response_snippet = null;

    if ($inspectBody) {
        // get full body (follow redirects already handled)
        $ch2 = curl_init($effective_url ?: $url);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $fullBody = curl_exec($ch2);
        $errn2 = curl_errno($ch2);
        $http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE) ?: $http_code;
        curl_close($ch2);

        if ($errn2) {
            return [
                'url'=>$url, 'ok'=>false, 'http_code'=>$http_code, 'status'=>'ERROR',
                'error'=>$fullBody ?: 'curl_error',
            ];
        }

        $content_hash = hash('sha256', $fullBody);
        // snippet to store for quick identification (first 400 chars)
        $response_snippet = mb_substr(strip_tags($fullBody), 0, 400);

        // detect custom 404 keywords
        $lower = mb_strtolower($response_snippet);
        $not_found_keywords = ['not found','404','page not found','gone','no such page','sorry, the page'];
        $is_custom_404 = false;
        foreach ($not_found_keywords as $kw) {
            if (mb_strpos($lower, $kw) !== false) { $is_custom_404 = true; break; }
        }
    }

    // Determine status
    $status = 'UNKNOWN';
    if (in_array($http_code, [200, 201, 202])) $status = $is_custom_404 ? 'NOT_FOUND_CUSTOM' : 'OK';
    if (in_array($http_code, [301,302,303,307,308])) $status = 'REDIRECT';
    if ($http_code == 404) $status = 'NOT_FOUND';
    if ($http_code == 410) $status = 'GONE';
    if ($http_code >= 500) $status = 'SERVER_ERROR';
    if ($http_code == 0) $status = 'ERROR';

    return [
        'url'=>$url,
        'ok'=> in_array($status, ['OK','REDIRECT']),
        'http_code'=>$http_code,
        'status'=>$status,
        'effective_url'=>$effective_url,
        'etag'=>$etag,
        'last_modified'=>$last_modified,
        'content_hash'=>$content_hash,
        'response_snippet'=>$response_snippet,
        'error'=>$error ?? null,
    ];
}
