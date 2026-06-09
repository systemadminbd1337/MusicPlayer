<?php
// admin/test_logs.php
include __DIR__ . "/_bootstrap.php";

// টেস্ট ডেটা যোগ
$test_data = [
    ['admin_id' => 1, 'username' => 'superadmin', 'success' => 1],
    ['admin_id' => null, 'username' => 'hacker', 'success' => 0],
    ['admin_id' => 2, 'username' => 'moderator', 'success' => 1],
    ['admin_id' => null, 'username' => 'testuser', 'success' => 0],
];

foreach ($test_data as $data) {
    log_admin_login_attempt($data['admin_id'], $data['username'], $data['success']);
}

echo "✅ Test data added successfully! Check your admin logs page now.";
echo "<br><a href='login_logs.php'>View Admin Logs</a>";
?>