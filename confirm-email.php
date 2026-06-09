<?php
include "config.php";

if (empty($_SESSION['user'])) {
    redirect('login.php');
    exit();
}

$user = (object) $_SESSION['user'];

// Random token generate
$token = bin2hex(random_bytes(32));

// Save to DB
$stmt = $pdo->prepare("UPDATE k_users SET email_confirm_token=?, email_verified=0 WHERE id=?");
$stmt->execute([$token, $user->id]);

// Verification link বানানো
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$link   = $scheme . "://" . $host . "/verify-email.php?token=" . $token;

// Mail পাঠানো
$to      = $user->email;
$subject = "Verify your email - HackLink Panel";
$message = "Hello {$user->username},\n\nClick this link to verify your email:\n\n{$link}\n\nIf you did not request this, ignore this email.";
$headers = "From: no-reply@{$host}";

// Send mail (make sure mail() works in your XAMPP or SMTP setup)
if (@mail($to, $subject, $message, $headers)) {
    $msg = "✅ Email verification link sent to your email!";
} else {
    $msg = "⚠️ Could not send email. Configure mail() or SMTP.";
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Confirm Email</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white d-flex align-items-center" style="height:100vh;">
  <div class="container text-center">
    <div class="alert alert-info shadow-lg">
      <?= $msg ?>
    </div>
    <a href="profile.php" class="btn btn-primary">Back to Profile</a>
  </div>
</body>
</html>
