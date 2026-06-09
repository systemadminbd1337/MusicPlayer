<?php
// api/me.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if(isset($_SESSION['user']) && $_SESSION['user']) {
    // নিরাপত্তার কারণে sensitive ফিল্ড বাদ দিন
    $u = $_SESSION['user'];
    $out = array(
        'id' => $u->id ?? null,
        'user' => $u->user ?? null,
        'level' => $u->level ?? null,
        'kota' => $u->kota ?? null
    );
    echo json_encode(['ok' => true, 'user' => $out]);
} else {
    echo json_encode(['ok' => false, 'user' => null]);
}
