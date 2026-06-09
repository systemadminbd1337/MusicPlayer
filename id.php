<?php
session_start();
header('Content-Type: text/plain; charset=utf-8');

if (empty($_SESSION['user'])) {
    echo "❌ No session detected. You are not logged in.";
    exit;
}

$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
echo "✅ Session OK\n";
echo "Username: " . ($user->username ?? 'unknown') . "\n";
echo "User ID (uid): " . ($user->id ?? 'not set') . "\n";
