<?php
// db.php
$DB_HOST = "localhost";
$DB_NAME = "hacklinksc_ff";   // <-- আপনার ডাটাবেস নাম
$DB_USER = "hacklinksc_blackhatseo";
$DB_PASS = "blackhatseo&&&***@ADMINgm"; // XAMPP default এ ফাঁকা থাকে

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
