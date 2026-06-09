<?php
// config.php
// Improved: Robust database setup, error handling, and compatibility

// -------------------- SESSION --------------------
// Session is handled in _bootstrap.php, so no need to start it here

// -------------------- DB (ezSQL + PDO) --------------------
// Include ezSQL only once
include_once "inc/ezsql.core.php";
include_once "inc/ezsql.pdo.php";

try {
    // Initialize ezSQL_pdo with error handling
    $db = new ezSQL_pdo('mysql:host=localhost;dbname=check;charset=utf8mb4', 'root', '');
    $db->query('SET NAMES utf8mb4');
    $db->query('SET CHARACTER SET utf8mb4');
    $db->query("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");
} catch (Exception $e) {
    die("ezSQL_pdo connection failed: " . htmlspecialchars($e->getMessage()));
}

// Create PDO instance for compatibility
try {
    $pdo = new PDO('mysql:host=localhost;dbname=check;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET CHARACTER SET utf8mb4');
    $pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");
} catch (PDOException $e) {
    die("PDO connection failed: " . htmlspecialchars($e->getMessage()));
}

// Ensure $pdo is set for scripts expecting it
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = $db->dbh ?? null; // ezSQL_pdo stores PDO in $dbh
    if (!($pdo instanceof PDO)) {
        die("Error: PDO instance could not be established.");
    }
}

// -------------------- HELPERS --------------------
if (!function_exists('get')) {
    function get($key) {
        return htmlspecialchars(strip_tags(trim($_GET[$key] ?? '')));
    }
}

if (!function_exists('post')) {
    function post($key) {
        return htmlspecialchars(strip_tags(trim($_POST[$key] ?? '')));
    }
}

if (!function_exists('redirect')) {
    function redirect($link) {
        header("Location: $link");
        exit;
    }
}

if (!function_exists('getBot')) {
    function getBot($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'KralBot');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $retValue = curl_exec($ch);
        curl_close($ch);
        return $retValue;
    }
}

if (!function_exists('paginate')) {
    function paginate($total_records, $item_per_page, $current_page, $page_url) {
        if (!$current_page || $current_page == "") {
            $current_page = 1;
        }
        $total_pages = ceil($total_records / $item_per_page);
        $pagination = '';
        if ($total_pages > 0 && $total_pages != 1 && $current_page <= $total_pages) {
            $pagination .= '<ul class="pagination">';
            $right_links = $current_page + 15;
            $previous = $current_page - 1;
            $next = $current_page + 1;
            $first_link = true;

            if ($current_page > 1) {
                $previous_link = ($previous == 0) ? 1 : $previous;
                $pagination .= '<li><a href="' . $page_url . '1">&laquo;</a></li>';
                $pagination .= '<li><a href="' . $page_url . $previous_link . '">&lt;</a></li>';
                for ($i = ($current_page - 2); $i < $current_page; $i++) {
                    if ($i > 0) {
                        $pagination .= '<li><a href="' . $page_url . $i . '">' . $i . '</a></li>';
                    }
                }
                $first_link = false;
            }

            $pagination .= '<li class="active"><a href="#">' . $current_page . '</a></li>';

            for ($i = $current_page + 1; $i < $right_links; $i++) {
                if ($i <= $total_pages) {
                    $pagination .= '<li><a href="' . $page_url . $i . '">' . $i . '</a></li>';
                }
            }
            if ($current_page < $total_pages) {
                $next_link = ($i > $total_pages) ? $total_pages : $i;
                $pagination .= '<li><a href="' . $page_url . $next_link . '">&gt;</a></li>';
                $pagination .= '<li><a href="' . $page_url . $total_pages . '">&raquo;</a></li>';
            }
            $pagination .= '</ul>';
        }
        return $pagination;
    }
}

if (!function_exists('GetAlexa')) {
    function GetAlexa($url) {
        $p = parse_url($url);
        $d = getBot("https://www.alexa.com/minisiteinfo/" . $p["host"]);
        $ret = [];
        $c = @explode('countries', $d);
        if (count($c) <= 1) {
            $kod = 'Null';
        } else {
            $c1 = @explode('title="', $c[1]);
            $c2 = @explode('"', $c1[1]);
            $flag = $c2[0];
            $c3 = explode('>', $c2[1]);
            $c4 = explode('<', $c3[1]);
            $kod = trim($c4[0]);
        }
        if ($kod != "Null") {
            $n = explode('alt="' . $flag . ' Flag"/>', $d);
            $n1 = explode('<', $n[1]);
            $ulke = trim($n1[0]);
        } else {
            $ulke = 0;
        }
        $g = @explode('alt="Global"', $d);
        if (count($g) <= 1) {
            $global = 0;
        } else {
            $g1 = explode('>', $g[1]);
            $g2 = explode('<', $g1[1]);
            $global = trim($g2[0]);
        }
        $l = @explode('linksin', $d);
        if (count($l) <= 1) {
            $link = 0;
        } else {
            $l1 = explode('>', $l[1]);
            $l2 = explode('<', $l1[1]);
            $link = trim($l2[0]);
        }
        if ($ulke == "" || !$ulke) {
            $ulke = 0;
        }
        if ($global == "" || !$global) {
            $global = 0;
        }
        $ret["Ulke"] = $ulke;
        $ret["Global"] = $global;
        $ret["Lokasyon"] = $kod;
        return $ret;
    }
}

// -------------------- LANGUAGE HANDLER --------------------
if (!empty($_GET['lang'])) {
    $lang = strtolower($_GET['lang']);
    if (in_array($lang, ['en', 'tr'])) {
        $_SESSION['lang'] = $lang;
    }
}
if (empty($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
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
    ]
];

if (!function_exists('__t')) {
    function __t($key) {
        global $translations, $LANG;
        return $translations[$LANG][$key] ?? $key;
    }
}
?>