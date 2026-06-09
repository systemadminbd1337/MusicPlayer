<?php
// -------------------- SESSION --------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------- DB (ezSQL + PDO) --------------------
include_once "inc/ezsql.core.php";
include_once "inc/ezsql.pdo.php";

$db = new ezSQL_pdo('mysql:host=localhost;dbname=hacklinksc_ff', 'hacklinksc_blackhatseo', 'blackhatseo&&&***@ADMINgm');

// ✅ UTF-8 FIXED - COMPREHENSIVE SETTINGS
$db->query('SET NAMES utf8mb4');
$db->query("SET CHARACTER SET utf8mb4");
$db->query("SET collation_connection = 'utf8mb4_unicode_ci'");
$db->query("SET character_set_client = 'utf8mb4'");
$db->query("SET character_set_results = 'utf8mb4'");
$db->query("SET character_set_connection = 'utf8mb4'");

try {
    $pdo = new PDO('mysql:host=localhost;dbname=hacklinksc_ff;charset=utf8mb4', 'hacklinksc_blackhatseo', 'blackhatseo&&&***@ADMINgm');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ✅ PDO UTF-8 Settings
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
    
} catch (PDOException $e) {
    die("PDO connection failed: " . $e->getMessage());
}

// -------------------- UTF-8 HEADERS --------------------
header('Content-Type: text/html; charset=utf-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// -------------------- HELPERS (UTF-8 SAFE) --------------------
if (!function_exists('get')) {
    function get($key){ 
        $value = $_GET[$key] ?? '';
        $value = trim($value);
        $value = strip_tags($value);
        // ✅ UTF-8 safe escaping
        if (function_exists('mb_convert_encoding')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return $value;
    }
}

if (!function_exists('post')) {
    function post($key){ 
        $value = $_POST[$key] ?? '';
        $value = trim($value);
        $value = strip_tags($value);
        // ✅ UTF-8 safe escaping
        if (function_exists('mb_convert_encoding')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return $value;
    }
}

if (!function_exists('redirect')) {
    function redirect($link){
        echo '<script>location="'.$link.'";</script>'; exit();
    }
}

if (!function_exists('getBot')) {
    function getBot($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'KralBot');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // ✅ UTF-8 response handling
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: text/html; charset=utf-8",
            "Accept-Charset: utf-8"
        ));
        $retValue = curl_exec($ch);
        
        // ✅ Convert to UTF-8 if needed
        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            $encoding = mb_detect_encoding($retValue, 'UTF-8, ISO-8859-1, WINDOWS-1252', true);
            if ($encoding !== 'UTF-8') {
                $retValue = mb_convert_encoding($retValue, 'UTF-8', $encoding);
            }
        }
        
        return $retValue;
    }
}

if (!function_exists('paginate')) {
    function paginate($total_records,$item_per_page,$current_page,$page_url){
        if(!$current_page||$current_page==""){ $current_page = 1; }
        $total_pages = ceil($total_records/$item_per_page);
        $pagination = '';
        if($total_pages > 0 && $total_pages != 1 && $current_page <= $total_pages){
            $pagination .= '<ul class="pagination">';
            $right_links = $current_page + 15; $previous = $current_page - 1; $next = $current_page + 1; $first_link = true;

            if($current_page > 1){
                $previous_link = ($previous==0)?1:$previous;
                $pagination .= '<li><a href="'.$page_url.'1">&laquo;</a></li>';
                $pagination .= '<li><a href="'.$page_url.$previous_link.'">&lt;</a></li>';
                for($i = ($current_page-2); $i < $current_page; $i++){
                    if($i > 0){ $pagination .= '<li><a href="'.$page_url.$i.'">'.$i.'</a></li>'; }
                }
                $first_link = false;
            }

            $pagination .= '<li class="active"><a href="#">'.$current_page.'</a></li>';

            for($i = $current_page+1; $i < $right_links ; $i++){
                if($i<=$total_pages){ $pagination .= '<li><a href="'.$page_url.$i.'">'.$i.'</a></li>'; }
            }
            if($current_page < $total_pages){
                $next_link = ($i > $total_pages)? $total_pages : $i;
                $pagination .= '<li><a href="'.$page_url.$next_link.'">&gt;</a></li>';
                $pagination .= '<li><a href="'.$page_url.$total_pages.'">&raquo;</a></li>';
            }
            $pagination .= '</ul>';
        }
        return $pagination;
    }
}

if (!function_exists('GetAlexa')) {
    function GetAlexa($url){
        $p = parse_url($url);
        $d = getBot("https://www.alexa.com/minisiteinfo/".$p["host"]);
        $ret = array();
        $c = @explode('countries',$d);
        if(count($c) <= 1){ $kod = 'Null'; } else {
            $c1 = @explode('title="',$c[1]); $c2 = @explode('"',$c1[1]); $flag = $c2[0];
            $c3 = explode('>',$c2[1]); $c4 = explode('<',$c3[1]); $kod = trim($c4[0]);
        }
        if($kod != "Null"){ $n = explode('alt="'.$flag.' Flag"/>',$d); $n1 = explode('<',$n[1]); $ulke = trim($n1[0]); } else { $ulke = 0; }
        $g = @explode('alt="Global"',$d); if(count($g) <= 1){ $global = 0; } else { $g1 = explode('>',$g[1]); $g2 = explode('<',$g1[1]); $global = trim($g2[0]); }
        $l = @explode('linksin',$d); if(count($l) <= 1){ $link = 0; } else { $l1 = explode('>',$l[1]); $l2 = explode('<',$l1[1]); $link = trim($l2[0]); }
        if($ulke==""||!$ulke){ $ulke = 0; } if($global==""||!$global){ $global = 0; }
        $ret["Ulke"] = $ulke; $ret["Global"] = $global; $ret["Lokasyon"] = $kod; return $ret;
    }
}

// -------------------- LANGUAGE HANDLER --------------------
if (!empty($_GET['lang'])) {
    $lang = strtolower($_GET['lang']);
    if (in_array($lang, ['en','tr','ko'])) { $_SESSION['lang'] = $lang; } // ✅ Korean added
}
if (empty($_SESSION['lang'])) { $_SESSION['lang'] = 'en'; }
$LANG = $_SESSION['lang'];

$translations = [
    'en' => [
        'dashboard' => 'Dashboard',
        'link_market' => 'Link Market',
        'my_links' => 'My Links',
        'premium_indexer' => 'Premium Indexer',
        'packages' => 'Packages',
        'invoices' => 'Invoices',
        'support' => 'Support',
        'faq' => 'FAQ',
        'credits' => 'Credits',
        'documents' => 'Documents',
        'profile' => 'Profile',
        'logout' => 'Logout',
        'account' => 'Account',
        'support_requests' => 'Support Requests',
        'all_requests' => 'All Requests',
        'new_support' => 'New Support Ticket',
        'notifications' => 'Notifications',
        'no_notifications' => 'No notifications yet.',
    ],
    'tr' => [
        'dashboard' => 'Gösterge Paneli',
        'link_market' => 'Link Pazarı',
        'my_links' => 'Linklerim',
        'premium_indexer' => 'Premium Indexer',
        'packages' => 'Paketler',
        'invoices' => 'Faturalar',
        'support' => 'Destek',
        'faq' => 'SSS',
        'credits' => 'Kredi',
        'documents' => 'Dokümanlar',
        'profile' => 'Profil',
        'logout' => 'Çıkış',
        'account' => 'Hesap',
        'support_requests' => 'Destek Talepleri',
        'all_requests' => 'Tüm Talepler',
        'new_support' => 'Yeni Destek Talebi',
        'notifications' => 'Bildirimler',
        'no_notifications' => 'Henüz bildirim yok.',
    ],
    'ko' => [ // ✅ Korean translations added
        'dashboard' => '대시보드',
        'link_market' => '링크 마켓',
        'my_links' => '내 링크',
        'premium_indexer' => '프리미엄 인덱서',
        'packages' => '패키지',
        'invoices' => '인보이스',
        'support' => '지원',
        'faq' => '자주 묻는 질문',
        'credits' => '크레딧',
        'documents' => '문서',
        'profile' => '프로필',
        'logout' => '로그아웃',
        'account' => '계정',
        'support_requests' => '지원 요청',
        'all_requests' => '모든 요청',
        'new_support' => '새 지원 티켓',
        'notifications' => '알림',
        'no_notifications' => '아직 알림이 없습니다.',
    ]
];

if (!function_exists('__t')) {
    function __t($key){
        global $translations, $LANG;
        return $translations[$LANG][$key] ?? $key;
    }
}

// ✅ UTF-8 DEBUG HELPER (Remove in production)
if (isset($_GET['debug_utf8'])) {
    function debug_utf8($text) {
        echo "<!-- UTF-8 DEBUG: " . htmlspecialchars($text) . " -->\n";
    }
    debug_utf8("Korean Test: 안녕하세요");
    debug_utf8("Config loaded with UTF-8 support");
}
?>