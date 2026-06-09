<?php
// cron/verify_links.php
// Run this via system cron every hour: php /path/to/cron/verify_links.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php'; // Include the DB connection
function dbx($v) { global $db; return $db->escape($v); }

// Ensure k_job_logs exists (same as worker)
try {
  $db->query("CREATE TABLE IF NOT EXISTS k_job_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    `timestamp` DATETIME,
    action VARCHAR(50),
    message TEXT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* ignore */ }

require_once '../inc/auto_functions.php';

// Lock to prevent concurrent runs
$lock_file = __DIR__ . '/verify_links.lock';
$fp = fopen($lock_file, 'w+');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
  echo "Verifier is already running. Skipping.\n";
  exit;
}

// Fetch 20 random processed orders for verification (to distribute load; adjust as needed)
$processed = $db->get_results("SELECT * FROM k_orders WHERE status = 'processed' AND backlink_url IS NOT NULL ORDER BY RAND() LIMIT 20");

foreach ($processed as $order) {
  verify_order($order->id);
  sleep(rand(1, 3)); // Delay
}

// Release lock
flock($fp, LOCK_UN);
fclose($fp);
unlink($lock_file);

echo "Verifier completed.\n";
?>