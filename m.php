<?php
// insert_test_data.php — এক ক্লিকে ড্যাশবোর্ডে ডাটা দেখান
require_once "config.php";
if (empty($_SESSION['user'])) { die("Login required."); }
$user = $_SESSION['user'];
$uid = (int)(is_object($user) ? $user->id : $user['id']);

if (!isset($db)) { die("Database not connected."); }

echo "<pre style='background:#000;color:#0f0;padding:20px;font-family:consolas;'>";
echo "INSERTING TEST DATA FOR UID: $uid\n";
echo str_repeat("=", 60) . "\n";

// 1. k_orders এ টেস্ট অর্ডার
$db->query("INSERT INTO k_orders (uid, lid, tarih, created_at, duration, target_url, keyword, credit) VALUES
    ($uid, 100, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), '30d', 'https://test.com', 'seo', 2.50),
    ($uid, 101, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), '30d', 'https://demo.com', 'backlink', 3.00),
    ($uid, 102, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), '60d', 'https://example.com', 'anchor', 4.00),
    ($uid, 103, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), '30d', 'https://site.com', 'link', 2.50),
    ($uid, 104, NOW(), NOW(), '30d', 'https://new.com', 'new', 3.00)
ON DUPLICATE KEY UPDATE credit = VALUES(credit)");

// 2. k_linkdb এ টেস্ট লিঙ্ক (যদি না থাকে)
$db->query("INSERT IGNORE INTO k_linkdb (id, domain, link, credit, created_at) VALUES
    (100, 'test.com', 'https://test.com', 2.50, NOW()),
    (101, 'demo.com', 'https://demo.com', 3.00, NOW()),
    (102, 'example.com', 'https://example.com', 4.00, NOW()),
    (103, 'site.com', 'https://site.com', 2.50, NOW()),
    (104, 'new.com', 'https://new.com', 3.00, NOW())");

echo "5 TEST ORDERS INSERTED!\n";
echo "Total Credit Spent: 15.00\n";
echo "Purchase History: 5 in last 7 days\n";
echo str_repeat("=", 60) . "\n";
echo "Go to: <a href='index.php' style='color:#0f0;text-decoration:underline;'>DASHBOARD</a>\n";
echo "</pre>";
?>