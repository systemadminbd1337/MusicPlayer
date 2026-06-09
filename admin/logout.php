<?php
// admin/logout.php

// ১️⃣ Output buffer চালু করো (যাতে header পাঠানোর আগে accidental echo ধরা পড়ে)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start(); // ✅ add this line at the very top

require_once __DIR__ . "/../config.php";

// ২️⃣ সেশন ধ্বংস
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// ৩️⃣ Redirect
header("Location: login.php");
exit;

// ৪️⃣ Output buffer শেষ করো (safety)
ob_end_flush();
