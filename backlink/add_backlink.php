<?php
// add_backlink.php
// Mock API for testing backlink creation on localhost

header('Content-Type: application/json; charset=utf-8');

// Simulate processing a backlink request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $url = $_POST['url'] ?? '';
  $anchor = $_POST['anchor'] ?? '';

  // Basic validation
  if (empty($url) || empty($anchor)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing url or anchor']);
    exit;
  }

  // Simulate successful backlink creation
  // In a real system, this would add the link to a CMS or database
  $backlink_url = "https://example.com/mock-post/" . rand(1000, 9999);

  // Return success response
  echo json_encode([
    'success' => true,
    'backlink_url' => $backlink_url,
    'message' => "Backlink created for $url with anchor '$anchor'"
  ]);
} else {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>