<?php
/**
 * api/receiver.php — Auto-publish Backlink Page Generator
 * Receives: secret, operation=place_link, domain, target_url, keyword
 * Publishes: /public/backlinks/{domain}/index.html (+ sitemap.xml, robots.txt)
 * Persists: entries.json (dedupe by target_url)
 */

//// ---------------- CONFIG ----------------
$SECRET_KEY      = "my_secret_key_12345";         // link-depo.php এর RECEIVER_SECRET এর সাথে মেলাতে হবে
$PUBLIC_BASE_URL = $PUBLIC_BASE_URL = "https://hack-link.com/backlinks";// পাবলিক বেস URL (শেষে স্ল্যাশ নয়)
$BASE_DIR        = dirname(__DIR__) . "/public/backlinks"; // সার্ভারে লেখার লোকেশন
$LOG_FILE        = __DIR__ . "/receiver.log";     // রিকোয়েস্ট লগ
$SITE_TITLE      = "Systemadminbd Backlinks";     // HTML হেডারের টাইটেল
$ALLOW_DUPLICATE = false;                         // true হলে একই target_url বহুবার রাখা হবে

//// ------------- SAFETY & HEADERS -------------
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>0,'msg'=>'Invalid method']); exit;
}

//// ------------- Read Input -------------
$secret     = $_POST['secret']     ?? '';
$operation  = $_POST['operation']  ?? '';
$domainRaw  = $_POST['domain']     ?? '';
$targetRaw  = $_POST['target_url'] ?? '';
$keywordRaw = $_POST['keyword']    ?? '';

//// ------------- Auth -------------
if (!hash_equals($SECRET_KEY, $secret)) {
  echo json_encode(['ok'=>0,'msg'=>'Unauthorized']); exit;
}

//// ------------- Validate Inputs -------------
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function norm_domain($d){
  $d = strtolower(trim($d));
  // allow punycode-ish labels, hyphen, digits, dots
  if (!preg_match('~^(?:[a-z0-9][a-z0-9\-]{0,62}\.)+[a-z]{2,63}$~i', $d)) return '';
  return $d;
}
function norm_url($u){
  $u = trim($u);
  if (!preg_match('~^https?://~i', $u)) return '';
  // basic URL validation
  $parts = parse_url($u);
  if (empty($parts['host'])) return '';
  return $u;
}

$domain     = norm_domain($domainRaw);
$target_url = norm_url($targetRaw);
$keyword    = trim($keywordRaw);

if ($operation !== 'place_link') { echo json_encode(['ok'=>0,'msg'=>'Invalid operation']); exit; }
if (!$domain)     { echo json_encode(['ok'=>0,'msg'=>'Bad domain']); exit; }
if (!$target_url) { echo json_encode(['ok'=>0,'msg'=>'Bad target_url']); exit; }
if ($keyword === '') $keyword = parse_url($target_url, PHP_URL_HOST) ?? 'visit'; // fallback

//// ------------- Prepare Paths -------------
$domainDir   = $BASE_DIR . "/" . $domain;
$entriesFile = $domainDir . "/entries.json";
$htmlFile    = $domainDir . "/index.html";
$sitemapFile = $domainDir . "/sitemap.xml";
$robotsFile  = $domainDir . "/robots.txt";

//// ------------- Ensure Directories -------------
if (!is_dir($BASE_DIR)) {
  if (!@mkdir($BASE_DIR, 0755, true)) {
    echo json_encode(['ok'=>0,'msg'=>'Cannot create BASE_DIR']); exit;
  }
}
if (!is_dir($domainDir)) {
  if (!@mkdir($domainDir, 0755, true)) {
    echo json_encode(['ok'=>0,'msg'=>'Cannot create domain dir']); exit;
  }
}

//// ------------- Load Existing Entries -------------
$entries = [];
if (is_file($entriesFile)) {
  $json = file_get_contents($entriesFile);
  $tmp  = json_decode($json, true);
  if (is_array($tmp)) $entries = $tmp;
}

//// ------------- De-duplication or Add -------------
$nowIso = date('c');
$foundIndex = -1;
if (!$ALLOW_DUPLICATE) {
  foreach ($entries as $i => $row) {
    if (isset($row['target_url']) && $row['target_url'] === $target_url) { $foundIndex = $i; break; }
  }
}

if ($foundIndex >= 0) {
  // Update existing record
  $entries[$foundIndex]['keyword']     = $keyword;
  $entries[$foundIndex]['hits']        = (int)($entries[$foundIndex]['hits'] ?? 0) + 1;
  $entries[$foundIndex]['updated_at']  = $nowIso;
} else {
  // Create new record
  $entries[] = [
    'target_url' => $target_url,
    'keyword'    => $keyword,
    'created_at' => $nowIso,
    'updated_at' => $nowIso,
    'hits'       => 1
  ];
}

//// ------------- Persist entries.json (atomic) -------------
$ok = safe_write_json($entriesFile, $entries, $err);
if (!$ok) {
  echo json_encode(['ok'=>0,'msg'=>'Write entries failed: '.$err]); exit;
}

//// ------------- Render HTML (atomic) -------------
$pageUrl = rtrim($PUBLIC_BASE_URL, '/').'/'.$domain.'/';
$ok = safe_write_html($htmlFile, render_html($SITE_TITLE, $domain, $entries, $pageUrl), $err);
if (!$ok) {
  echo json_encode(['ok'=>0,'msg'=>'Write HTML failed: '.$err]); exit;
}

//// ------------- Write sitemap.xml & robots.txt (best-effort) -------------
safe_write_text($sitemapFile, render_sitemap($pageUrl, $entries));
if (!is_file($robotsFile)) {
  safe_write_text($robotsFile, "User-agent: *\nAllow: /\nSitemap: ".$pageUrl."sitemap.xml\n");
}

//// ------------- Log & Respond -------------
$logLine = sprintf("[%s] %s -> %s | '%s'\n", date('Y-m-d H:i:s'), $domain, $target_url, $keyword);
@file_put_contents($LOG_FILE, $logLine, FILE_APPEND);

echo json_encode([
  'ok'      => 1,
  'msg'     => 'Backlink published',
  'page'    => $pageUrl,
  'entries' => count($entries)
]);
exit;

//// ------------- Helper Functions -------------

/** atomic JSON write with file lock */
function safe_write_json($file, $data, &$err){
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  return safe_write_text($file, $json, $err);
}

/** atomic text write with file lock */
function safe_write_text($file, $text, &$err = null){
  $dir = dirname($file);
  if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $err = "mkdir failed"; return false; }
  $tmp = $file . ".tmp";
  if (file_put_contents($tmp, $text, LOCK_EX) === false) { $err = "tmp write failed"; return false; }
  if (!@rename($tmp, $file)) { @unlink($tmp); $err = "rename failed"; return false; }
  @chmod($file, 0644);
  return true;
}

/** atomic HTML write shortcut */
function safe_write_html($file, $html, &$err){ return safe_write_text($file, $html, $err); }

/** HTML Renderer (neon, responsive, no external CSS needed) */
function render_html($siteTitle, $domain, $entries, $pageUrl){
  // newest first
  usort($entries, function($a,$b){ return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''); });
  $rows = '';
  foreach ($entries as $i=>$e){
    $u = esc_attr($e['target_url'] ?? '#');
    $k = esc_attr($e['keyword'] ?? 'visit');
    $c = (int)($e['hits'] ?? 1);
    $up= esc_attr($e['updated_at'] ?? '');
    $cr= esc_attr($e['created_at'] ?? '');
    $rows .= "<tr>
      <td>".($i+1)."</td>
      <td><a href=\"{$u}\" rel=\"nofollow\" target=\"_blank\">{$k}</a><div class=\"sub\">{$u}</div></td>
      <td class=\"tc\">{$c}</td>
      <td class=\"td\">{$cr}</td>
      <td class=\"td\">{$up}</td>
    </tr>";
  }

  $now = esc_attr(date('Y-m-d H:i:s'));
  $title = esc_attr($siteTitle." — ".$domain);
  $domainEsc = esc_attr($domain);
  $pageUrlEsc= esc_attr($pageUrl);

  return "<!doctype html>
<html lang=\"en\">
<head>
<meta charset=\"utf-8\"/>
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"/>
<title>{$title}</title>
<meta name=\"robots\" content=\"index,follow\"/>
<link rel=\"canonical\" href=\"{$pageUrlEsc}\"/>
<style>
  :root{--bg:#0b0b16;--card:#121226;--ink:#e6e6ff;--mut:#9aa0c3;--ac:#8b5cf6;--ok:#22c55e;}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,Segoe UI,Roboto,Ubuntu,Arial}
  .wrap{max-width:1100px;margin:40px auto;padding:0 16px}
  .card{background:var(--card);border-radius:18px;padding:20px;border:1px solid rgba(255,255,255,.06);box-shadow:0 10px 30px rgba(0,0,0,.35)}
  h1{margin:0 0 8px;font-size:26px}
  .subtle{color:var(--mut);font-size:14px}
  .badge{display:inline-block;padding:4px 10px;border-radius:999px;background:linear-gradient(90deg,var(--ok),#06b6d4);color:#00261f;font-weight:800;font-size:12px;margin-left:8px}
  table{width:100%;border-collapse:collapse;margin-top:14px}
  th,td{padding:10px;border-bottom:1px dashed rgba(255,255,255,.08)}
  th{color:#cfd3ff;text-align:left;font-weight:700}
  .tc{text-align:center}
  .td{white-space:nowrap;font-feature-settings:\"tnum\" 1,\"lnum\" 1}
  .sub{font-size:12px;color:var(--mut);word-break:break-all}
  .grid{display:grid;grid-template-columns:1fr;gap:16px}
  .top{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;flex-wrap:wrap}
  .mut{color:var(--mut)}
  .foot{margin-top:12px;font-size:12px;color:var(--mut);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
  @media(min-width:700px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
  <div class=\"wrap\">
    <div class=\"card\">
      <div class=\"top\">
        <div>
          <h1>Backlinks for <span style=\"color:var(--ac)\">{$domainEsc}</span></h1>
          <div class=\"subtle\">Auto-published at <time>{$now}</time></div>
        </div>
        <div><span class=\"badge\">LIVE</span></div>
      </div>
      <div class=\"grid\">
        <div>
          <table aria-label=\"Backlink list\">
            <thead>
              <tr><th>#</th><th>Link (anchor & URL)</th><th class=\"tc\">Hits</th><th>Created</th><th>Updated</th></tr>
            </thead>
            <tbody>{$rows}</tbody>
          </table>
        </div>
      </div>
      <div class=\"foot\">
        <div>Robots: <a class=\"mut\" href=\"{$pageUrlEsc}robots.txt\">robots.txt</a> • Sitemap: <a class=\"mut\" href=\"{$pageUrlEsc}sitemap.xml\">sitemap.xml</a></div>
        <div class=\"mut\">Powered by Systemadminbd</div>
      </div>
    </div>
  </div>
</body>
</html>";
}

/** very small sitemap for the domain container page itself (optional) */
function render_sitemap($pageUrl, $entries){
  $last = date('c');
  return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">
  <url>
    <loc>".htmlspecialchars(rtrim($pageUrl,'/')."/", ENT_QUOTES, 'UTF-8')."</loc>
    <lastmod>{$last}</lastmod>
    <priority>0.6</priority>
  </url>
</urlset>";
}
