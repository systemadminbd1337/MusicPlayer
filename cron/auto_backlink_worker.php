<?php
// cron/auto_backlink_worker.php
// Run this via system cron every 5 minutes: php /path/to/cron/auto_backlink_worker.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php'; // Include the DB connection
function dbx($v) { global $db; return $db->escape($v); }

// Create k_job_logs if not exists
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
$lock_file = __DIR__ . '/auto_worker.lock';
$fp = fopen($lock_file, 'w+');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
  echo "Worker is already running. Skipping.\n";
  exit;
}

// Fetch pending orders (LIMIT 10)
$pending = $db->get_results("SELECT * FROM k_orders WHERE status = 'pending' AND attempts < " . (int)$maxRetries . " ORDER BY created_at ASC LIMIT 10");

foreach ($pending as $order) {
  $att = (int)$order->attempts;
  if ($att > 0) {
    $backoff_min = isset($retryBackoffMinutes[$att - 1]) ? $retryBackoffMinutes[$att - 1] : end($retryBackoffMinutes);
    $retry_time = strtotime($order->last_attempt) + ($backoff_min * 60);
    if ($retry_time > time()) continue; // Not ready for retry
  }

  // Set to processing (with check to avoid race)
  $db->query("UPDATE k_orders SET status = 'processing' WHERE id = '" . (int)$order->id . "' AND status = 'pending'");
  if ($db->rows_affected == 0) continue;

  process_order($order->id);

  sleep(rand(1, 3)); // Simple delay to avoid rate limits
}

// Release lock
flock($fp, LOCK_UN);
fclose($fp);
unlink($lock_file);

echo "Worker completed.\n";
?>