<?php
include "config.php";

$token = $_GET['token'] ?? '';

if ($token == '') {
    die("Invalid token.");
}

// Check token
$stmt = $pdo->prepare("SELECT id,email FROM k_users WHERE email_confirm_token=? LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Update user
    $pdo->prepare("UPDATE k_users SET email_verified=1, email_confirm_token=NULL WHERE id=?")
        ->execute([$user['id']]);

    $msg = "🎉 Email verified successfully!";
} else {
    $msg = "⚠️ Invalid or expired token.";
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Email Verification</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white d-flex align-items-center" style="height:100vh;">
  <div class="container text-center">
    <div class="alert alert-success shadow-lg">
      <?= $msg ?>
    </div>
    <a href="profile.php" class="btn btn-light">Go to Profile</a>
  </div>
</body>
</html>
