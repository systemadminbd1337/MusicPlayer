<?php
// inc/auto_functions.php
// Core automation functions for cron and admin panel

$autoProcessEnabled = true; // Toggle auto-processing
$maxRetries = 3; // Max retry attempts
$retryBackoffMinutes = [5, 15, 60]; // Minutes to wait between retries
$proxyList = []; // Add proxies like ['http://user:pass@proxy:port']
$adminEmail = 'admin@example.com'; // For failure notifications
$defaultApiEndpoint = '/api/add-backlink'; // Default endpoint path if api_endpoint is missing
$apiAuthKey = ''; // Optional: Add API key for real sites here or per-site in k_linkdb

function log_job($order_id, $action, $message) {
  global $db;
  try {
    $db->query("INSERT INTO k_job_logs (order_id, `timestamp`, action, message) VALUES ('" . (int)$order_id . "', NOW(), '" . dbx($action) . "', '" . dbx($message) . "')");
  } catch (Throwable $e) { /* Ignore errors to prevent job failure */ }
}

function process_order($order_id) {
  global $db, $maxRetries, $adminEmail, $proxyList, $autoProcessEnabled, $defaultApiEndpoint, $apiAuthKey;
  if (!$autoProcessEnabled) {
    log_job($order_id, 'process', 'Auto-processing disabled');
    return;
  }

  // Fetch order with link details
  $order = $db->get_row("SELECT o.*, l.domain, l.api_endpoint FROM k_orders o LEFT JOIN k_linkdb l ON l.id = o.lid WHERE o.id = '" . (int)$order_id . "'");
  if (!$order) {
    log_job($order_id, 'process', 'Order not found');
    return;
  }

  // Skip if not processing
  if ($order->status !== 'processing') {
    log_job($order_id, 'process', "Invalid status: {$order->status}");
    return;
  }

  // Increment attempts
  $attempts = (int)$order->attempts + 1;

  try {
    // Use api_endpoint if available, else construct default endpoint
    $add_url = $order->api_endpoint ?: "https://{$order->domain}" . $defaultApiEndpoint;
    $data = [
      'url' => $order->target_url,
      'anchor' => $order->anchor,
      'api_key' => $apiAuthKey // Add global API key (optional; can be per-site in k_linkdb)
    ];

    // cURL setup
    $ch = curl_init($add_url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($data),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_SSL_VERIFYPEER => true, // Enable for real sites
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
      ]
    ]);

    // Proxy support (optional)
    if (!empty($proxyList)) {
      $proxy = $proxyList[array_rand($proxyList)];
      curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }

    // Execute request
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Handle response
    if ($res === false || $http_code >= 400) {
      $msg = "Failed: HTTP $http_code" . ($curl_error ? " ($curl_error)" : "");
      if ($attempts >= $maxRetries) {
        $db->query("UPDATE k_orders SET status = 'failed', attempts = '{$attempts}', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
        log_job($order_id, 'process', "Max retries reached: $msg");
        if ($adminEmail) {
          @mail($adminEmail, "Backlink Order Failed: $order_id", "Order $order_id failed after $maxRetries attempts: $msg");
        }
      } else {
        $db->query("UPDATE k_orders SET status = 'pending', attempts = '{$attempts}', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
        log_job($order_id, 'process', "Retry $attempts/$maxRetries: $msg");
      }
      return;
    }

    // Parse JSON response
    $response = json_decode($res, true);
    if (!$response || !isset($response['success']) || !$response['success'] || empty($response['backlink_url'])) {
      $msg = "Invalid response: " . ($response ? json_encode($response) : 'No JSON');
      if ($attempts >= $maxRetries) {
        $db->query("UPDATE k_orders SET status = 'failed', attempts = '{$attempts}', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
        log_job($order_id, 'process', "Max retries reached: $msg");
        if ($adminEmail) {
          @mail($adminEmail, "Backlink Order Failed: $order_id", "Order $order_id failed after $maxRetries attempts: $msg");
        }
      } else {
        $db->query("UPDATE k_orders SET status = 'pending', attempts = '{$attempts}', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
        log_job($order_id, 'process', "Retry $attempts/$maxRetries: $msg");
      }
      return;
    }

    // Success
    $backlink_url = $response['backlink_url'];
    $msg = "Success: Link added at $backlink_url";
    $db->query("UPDATE k_orders SET status = 'processed', processed_at = NOW(), backlink_url = '" . dbx($backlink_url) . "', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
    log_job($order_id, 'process', $msg);

  } catch (Throwable $e) {
    $msg = "Exception: " . $e->getMessage();
    if ($attempts >= $maxRetries) {
      $db->query("UPDATE k_orders SET status = 'failed', attempts = '{$attempts}', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
      log_job($order_id, 'process', "Max retries reached: $msg");
      if ($adminEmail) {
        @mail($adminEmail, "Backlink Order Failed: $order_id", "Order $order_id failed after $maxRetries attempts: $msg");
      }
    } else {
      $db->query("UPDATE k_orders SET status = 'pending', attempts = '{$attempts}', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
      log_job($order_id, 'process', "Retry $attempts/$maxRetries: $msg");
    }
  }
}

function verify_order($order_id) {
  global $db, $adminEmail;

  $order = $db->get_row("SELECT * FROM k_orders WHERE id = '" . (int)$order_id . "'");
  if (!$order || !$order->backlink_url) {
    log_job($order_id, 'verify', 'Order or backlink_url not found');
    return;
  }

  try {
    $ch = curl_init($order->backlink_url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $check = [
      'date' => date('Y-m-d H:i:s'),
      'http_code' => $http_code,
      'contains_anchor' => false,
      'snippet' => '',
      'content_hash' => '',
      'error' => $curl_error ?: ($res === false ? 'Request failed' : '')
    ];

    if ($res !== false && $http_code < 400) {
      $check['content_hash'] = md5($res);
      $pattern = '/<a\s[^>]*href=[\'"](?:' . preg_quote($order->target_url, '/') . '|https?:\/\/[^\'"]+)[\'"][^>]*>' . preg_quote($order->anchor, '/') . '<\/a>/i';
      $check['contains_anchor'] = preg_match($pattern, $res);
      if ($check['contains_anchor']) {
        $start = max(0, strpos($res, $order->anchor) - 50);
        $check['snippet'] = substr($res, $start, 100);
      }
    }

    $checks = json_decode($order->url_checks ?: '[]', true);
    $checks[] = $check;
    if (count($checks) > 10) array_shift($checks);

    $failed_count = 0;
    foreach (array_slice($checks, -3) as $c) {
      if (!$c['contains_anchor']) $failed_count++;
    }

    if ($failed_count >= 3) {
      $msg = "Failed 3 consecutive verifications";
      $db->query("UPDATE k_orders SET status = 'failed', url_checks = '" . dbx(json_encode($checks)) . "', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
      log_job($order_id, 'verify', $msg);
      if ($adminEmail) {
        @mail($adminEmail, "Backlink Verification Failed: $order_id", "Order $order_id failed verification: $msg");
      }
    } else {
      $msg = "Verification: " . ($check['contains_anchor'] ? 'Link found' : 'Link not found');
      $db->query("UPDATE k_orders SET url_checks = '" . dbx(json_encode($checks)) . "', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
      log_job($order_id, 'verify', $msg);
    }

  } catch (Throwable $e) {
    $msg = "Verification exception: " . $e->getMessage();
    $checks = json_decode($order->url_checks ?: '[]', true);
    $checks[] = [
      'date' => date('Y-m-d H:i:s'),
      'http_code' => 0,
      'contains_anchor' => false,
      'snippet' => '',
      'content_hash' => '',
      'error' => $msg
    ];
    if (count($checks) > 10) array_shift($checks);
    $db->query("UPDATE k_orders SET url_checks = '" . dbx(json_encode($checks)) . "', notes = CONCAT(IFNULL(notes, ''), '\n', '" . dbx($msg) . "') WHERE id = '" . (int)$order_id . "'");
    log_job($order_id, 'verify', $msg);
  }
}
?>