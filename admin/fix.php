<?php
// admin/test_fix.php
include __DIR__ . "/_bootstrap.php";

echo "<h3>🔧 Testing Login Logs System</h3>";

// Test multiple inserts
for ($i = 1; $i <= 5; $i++) {
    $username = "test_user_" . time() . "_" . $i;
    $ip = "192.168.1." . $i;
    
    $sql = "INSERT INTO k_admin_login_logs (username, ip, user_agent, success) 
            VALUES ('$username', '$ip', 'Test Agent', " . ($i % 2) . ")";
    
    $result = $db->query($sql);
    $insert_id = $db->insert_id ?? 'unknown';
    
    echo "Insert $i: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . " | ID: $insert_id<br>";
}

// Show all logs
echo "<h4>All Logs in Database:</h4>";
$logs = $db->get_results("SELECT * FROM k_admin_login_logs ORDER BY id DESC");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>IP</th><th>Success</th><th>Created At</th></tr>";
foreach ($logs as $log) {
    echo "<tr>";
    echo "<td>{$log->id}</td>";
    echo "<td>{$log->username}</td>";
    echo "<td>{$log->ip}</td>";
    echo "<td>" . ($log->success ? '✅' : '❌') . "</td>";
    echo "<td>{$log->created_at}</td>";
    echo "</tr>";
}
echo "</table>";
?>