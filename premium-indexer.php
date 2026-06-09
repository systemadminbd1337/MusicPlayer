<?php
include "header.php";
global $db;

// ---------- SAFE AUTO-FIX FOR k_indexER TABLE ----------
try {
    $hasId = false; $isAuto = false;
    $cols = $db->get_results("SHOW COLUMNS FROM k_indexer", ARRAY_A);
    foreach ($cols as $c) {
        if (strtolower($c['Field']) === 'id') {
            $hasId = true;
            if (strpos(strtolower($c['Extra']), 'auto_increment') !== false) $isAuto = true;
        }
    }

    // Duplicate ID fixer
    $dupCount = (int)$db->get_var("SELECT COUNT(*) FROM (SELECT id, COUNT(*) c FROM k_indexer GROUP BY id HAVING c>1) t");
    if ($dupCount > 0) {
        error_log("⚠️ Duplicate ID found in k_indexer. Fixing...");
        $rows = $db->get_results("SELECT id FROM k_indexer ORDER BY id ASC");
        $new = 1;
        foreach ($rows as $r) {
            $db->query("UPDATE k_indexer SET id={$new} WHERE id={$r->id}");
            $new++;
        }
    }

    // If id not auto_increment → fix
    if ($hasId && !$isAuto) {
        $maxId = (int)$db->get_var("SELECT MAX(id) FROM k_indexer");
        $db->query("ALTER TABLE k_indexer MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        $db->query("ALTER TABLE k_indexer AUTO_INCREMENT=" . ($maxId + 1));
        error_log("✅ k_indexer AUTO_INCREMENT fixed (next id=" . ($maxId + 1) . ")");
    }
} catch (Throwable $e) {
    error_log("⚠️ Indexer self-heal failed: " . $e->getMessage());
}

// ---------- BASIC UTILS ----------
if (!function_exists('db_escape')) {
    function db_escape($v) {
        global $db;
        return isset($db) && method_exists($db, 'escape') ? $db->escape($v) : addslashes($v);
    }
}

// ---------- CLASSES ----------
class CacheUtil {
    private $dir = 'cache/';
    private $ttl = 86400;
    public function __construct() { if (!is_dir($this->dir)) mkdir($this->dir, 0755, true); }
    public function get($k){$f=$this->dir.md5($k).'.cache';return (file_exists($f)&&time()-filemtime($f)<$this->ttl)?unserialize(file_get_contents($f)):false;}
    public function set($k,$d){$f=$this->dir.md5($k).'.cache';file_put_contents($f,serialize($d));}
}

class SEOTool {
    private $uaList;
    private $cache;
    public function __construct(){
        $this->uaList=[
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
            'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
            'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)',
            'Mozilla/5.0 (compatible; Screaming Frog SEO Spider/16.0; +https://www.screamingfrog.co.uk/seo-spider/)',
            'Mozilla/5.0 (compatible; BaiduSpider/2.0; +http://www.baidu.com/search/spider.html)'
        ];
        $this->cache=new CacheUtil();
    }
    public function validateUrl($u){
        $u=trim($u);
        if(!filter_var($u,FILTER_VALIDATE_URL))return false;
        $p=parse_url($u);
        $host=rtrim($p['host']??'','/');
        $path=preg_replace('/\/+/','/',$p['path']??'/');
        return $p['scheme'].'://'.$host.$path;
    }
    public function checkRobotsTxt($url){
        $key='robots_'.$url;
        if($c=$this->cache->get($key))return $c;
        $p=parse_url($url);$r=$p['scheme'].'://'.$p['host'].'/robots.txt';
        $ch=curl_init($r);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false]);
        $c2=curl_exec($ch);curl_close($ch);
        $res=($c2!==false && strpos($c2,'Disallow:')!==false)?$c2:'';
        $this->cache->set($key,$res);return $res;
    }
    public function getSEOMetrics($url){
        $key='seo_'.$url;if($c=$this->cache->get($key))return $c;
        $ua=$this->uaList[array_rand($this->uaList)];
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERAGENT=>$ua,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false]);
        $st=microtime(true);$ct=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$lt=microtime(true)-$st;curl_close($ch);
        $m=['http_code'=>$code,'load_time'=>round($lt,2),'mobile_friendly'=>false,'keywords'=>[],'meta_title'=>'','meta_description'=>'','h1_count'=>0,'image_alt_missing'=>0];
        if($ct){
            $m['mobile_friendly']=strpos($ct,'viewport')!==false;
            $doc=new DOMDocument();@$doc->loadHTML($ct);$xp=new DOMXPath($doc);
            $mt=$xp->query("//meta[@name='title']/@content");$m['meta_title']=$mt->length?$mt->item(0)->value:'';
            $md=$xp->query("//meta[@name='description']/@content");$m['meta_description']=$md->length?$md->item(0)->value:'';
            $m['h1_count']=$xp->query("//h1")->length;
            $m['image_alt_missing']=$xp->query("//img[not(@alt) or @alt='']")->length;
            $text=strtolower(strip_tags($ct));$words=array_diff(str_word_count($text,1),['the','a','an','and','or','in','on','to']);
            $cnt=array_count_values($words);arsort($cnt);$m['keywords']=array_slice(array_keys($cnt),0,5);
        }
        $this->cache->set($key,$m);return $m;
    }
    public function triggerIndexing($urls){
        $mh=curl_multi_init();
        $h=[];
        $eng=[
            'Google'=>'https://www.google.com/ping?sitemap=',
            'Bing'=>'https://www.bing.com/ping?sitemap=',
            'Yandex'=>'https://webmaster.yandex.com/ping?sitemap=',
            'Baidu'=>'http://ping.baidu.com/ping/RPC2',
            'Ask'=>'http://submissions.ask.com/ping?sitemap='
        ];
        $maxRetries=2;
        $results=[];

        foreach($urls as $u){
            $p=parse_url($u);
            $host=$p['host']??'';
            $sitemapUrl=$p['scheme'].'://'.$host.'/sitemap.xml';
            
            // Ping search engines
            foreach($eng as $e=>$p){
                $url=$e==='Baidu'?$p:$p.urlencode($u);
                for($retry=0;$retry<=$maxRetries;$retry++){
                    $ch=curl_init($url);
                    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true];
                    if($e==='Baidu'){
                        $xmlrpc="<?xml version='1.0' encoding='UTF-8'?><methodCall><methodName>weblogUpdates.ping</methodName><params><param><value><string>$u</string></value></param><param><value><string>$u</string></value></param></params></methodCall>";
                        $opts[CURLOPT_POST]=true;
                        $opts[CURLOPT_POSTFIELDS]=$xmlrpc;
                        $opts[CURLOPT_HTTPHEADER]=['Content-Type: text/xml'];
                    }
                    curl_setopt_array($ch,$opts);
                    curl_multi_add_handle($mh,$ch);
                    $h[]=['ch'=>$ch,'url'=>$u,'engine'=>$e,'retry'=>$retry,'type'=>'ping'];
                }
            }

            // Try sitemap submission for Google and Bing
            if($host){
                foreach(['Google','Bing'] as $e){
                    $ch=curl_init($eng[$e].urlencode($sitemapUrl));
                    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true]);
                    curl_multi_add_handle($mh,$ch);
                    $h[]=['ch'=>$ch,'url'=>$sitemapUrl,'engine'=>$e,'retry'=>0,'type'=>'sitemap'];
                }
            }

            // Simulate bot visits with different user agents
            foreach($this->uaList as $ua){
                $ch=curl_init($u);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERAGENT=>$ua,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false]);
                curl_multi_add_handle($mh,$ch);
                $h[]=['ch'=>$ch,'url'=>$u,'engine'=>'BotVisit_'.substr($ua,0,20),'retry'=>0,'type'=>'bot'];
            }
        }

        // Execute multi-curl with retry logic
        do{
            curl_multi_exec($mh,$r);
            curl_multi_select($mh);
        }while($r>0);

        foreach($h as $v){
            $res=curl_multi_getcontent($v['ch']);
            $code=curl_getinfo($v['ch'],CURLINFO_HTTP_CODE);
            $results[]=['url'=>$v['url'],'engine'=>$v['engine'],'type'=>$v['type'],'retry'=>$v['retry'],'status'=>$code,'response'=>$res];
            curl_multi_remove_handle($mh,$v['ch']);
            curl_close($v['ch']);
        }
        curl_multi_close($mh);

        // Log results for debugging
        foreach($results as $res){
            error_log("Indexer [{$res['type']}][{$res['engine']}][Retry {$res['retry']}]: URL={$res['url']} Status={$res['status']}");
        }

        // Retry failed pings
        $failed=array_filter($results, function($r) use ($maxRetries) { return $r['type']==='ping'&&($r['status']<200||$r['status']>=400)&&$r['retry']<$maxRetries; });
        if($failed){
            $mh=curl_multi_init();
            $h2=[];
            foreach($failed as $f){
                $ch=curl_init($f['engine']==='Baidu'?$eng['Baidu']:$eng[$f['engine']].urlencode($f['url']));
                $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true];
                if($f['engine']==='Baidu'){
                    $xmlrpc="<?xml version='1.0' encoding='UTF-8'?><methodCall><methodName>weblogUpdates.ping</methodName><params><param><value><string>{$f['url']}</string></value></param><param><value><string>{$f['url']}</string></value></param></params></methodCall>";
                    $opts[CURLOPT_POST]=true;
                    $opts[CURLOPT_POSTFIELDS]=$xmlrpc;
                    $opts[CURLOPT_HTTPHEADER]=['Content-Type: text/xml'];
                }
                curl_setopt_array($ch,$opts);
                curl_multi_add_handle($mh,$ch);
                $h2[]=['ch'=>$ch,'url'=>$f['url'],'engine'=>$f['engine'],'retry'=>$f['retry']+1,'type'=>'ping'];
            }
            do{
                curl_multi_exec($mh,$r);
                curl_multi_select($mh);
            }while($r>0);
            foreach($h2 as $v){
                $res=curl_multi_getcontent($v['ch']);
                $code=curl_getinfo($v['ch'],CURLINFO_HTTP_CODE);
                error_log("Indexer Retry [ping][{$v['engine']}][Retry {$v['retry']}]: URL={$v['url']} Status=$code");
                curl_multi_remove_handle($mh,$v['ch']);
                curl_close($v['ch']);
            }
            curl_multi_close($mh);
        }
    }
}

$seoTool = new SEOTool();
$uid = (int)$user->id;
$credits = (int)$db->get_var("SELECT kredi FROM k_users WHERE id=$uid");
$costPerLink = 1;
$message = "";
$seoReports = [];

if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD']==='POST'){
    if ($_POST['csrf_token']!==$_SESSION['csrf_token']){
        $message='<div class="alert alert-danger neon-alert">Invalid CSRF token.</div>';
    } else {
        $lines=array_filter(array_map('trim',explode("\n",$_POST['urls'])));
        $valid=[];foreach($lines as $ln){if($u=$seoTool->validateUrl($ln))$valid[]=$u;}
        $need=count($valid)*$costPerLink;
        if(!$valid){$message='<div class="alert alert-warning neon-warning">⚠️ No valid links.</div>';}
        elseif($credits<$need){$message='<div class="alert alert-danger neon-alert">Not enough credits.</div>';}
        else{
            try{
                $batch=[];foreach($valid as $v){$batch[]="($uid,'".db_escape($v)."',NOW())";}
                $db->query("INSERT INTO k_indexer (uid,link,tarih) VALUES ".implode(',',$batch));
                $seoTool->triggerIndexing($valid);
                $db->query("UPDATE k_users SET kredi=kredi-$need WHERE id=$uid");
                $credits-=$need;
                $message='<div class="alert alert-success neon-success">✅ '.count($valid).' links added successfully!</div>';
            }catch(Throwable $e){$message='<div class="alert alert-danger neon-alert">DB error: '.$e->getMessage().'</div>';error_log($e->getMessage());}
        }
    }
}

$list=$db->get_results("SELECT * FROM k_indexer WHERE uid=$uid ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><title>🚀 Premium SEO Indexer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#060717;color:#e6eef8;font-family:'Inter',system-ui;}
.card{background:rgba(255,255,255,0.02);border:1px solid rgba(96,165,250,0.08);border-radius:12px;box-shadow:0 0 25px rgba(96,165,250,0.08);}
textarea{background:#0b0d21;color:#e6eef8;border:1px solid rgba(255,255,255,0.1);border-radius:8px;}
.btn-neon{background:linear-gradient(90deg,#60a5fa,#a855f7);color:#fff;border:0;transition:.2s;}
.btn-neon:hover{transform:scale(1.03);opacity:.85;}
.neon-alert{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;border-radius:8px;}
.neon-success{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.4);color:#bbf7d0;border-radius:8px;}
.neon-warning{background:rgba(250,204,21,0.08);border:1px solid rgba(250,204,21,0.3);color:#fde68a;border-radius:8px;}
.credit-box{background:rgba(255,255,255,0.05);padding:10px 15px;border-radius:10px;display:inline-block;margin-bottom:15px;}
.credit-box span{color:#facc15;font-weight:700;}
.table-dark{--bs-table-bg:transparent;--bs-table-color:#cbd5e1;}
a{color:#93c5fd;text-decoration:none;}a:hover{color:#a855f7;}
</style>
</head>
<body>
<div class="container my-4">
  <h2 class="fw-bold mb-4">🚀 Premium SEO Indexer</h2>
  <div class="credit-box">💰 Remaining Credits: <span><?= (int)$credits ?></span></div>
  <?= $message ?>
  <form method="post" class="card p-3 mb-4">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <label class="form-label">Enter URLs (one per line)</label>
    <textarea name="urls" rows="5" class="form-control" placeholder="https://example.com/page1
https://example.com/page2"></textarea>
    <p class="small text-muted mt-2">Cost: <?= $costPerLink ?> credit per link</p>
    <button class="btn btn-neon">➕ Add to Indexer</button>
  </form>
  <div class="card p-3">
    <h5 class="mb-3">📜 Last 50 Indexed</h5>
    <table class="table table-dark align-middle">
      <thead><tr><th>#</th><th>Link</th><th>Date</th></tr></thead><tbody>
      <?php if($list){$i=1;foreach($list as $r){?>
        <tr><td><?=$i++?></td><td><a href="<?=htmlspecialchars($r->link)?>" target="_blank"><?=htmlspecialchars($r->link)?></a></td><td><?=date("d.m.Y H:i",strtotime($r->tarih))?></td></tr>
      <?php }}else{echo '<tr><td colspan=3 class="text-center text-muted">No links yet.</td></tr>';}?>
      </tbody>
    </table>
  </div>
</div>
<?php include "footer.php"; ?>
</body>
</html>