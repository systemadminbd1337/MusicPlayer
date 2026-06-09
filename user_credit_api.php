<?php
session_start();
include "config.php";
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user'])){echo json_encode(['credit'=>0]);exit;}
$user = (object)$_SESSION['user'];
$uid = (int)$user->id;
$col = $db->get_var("SHOW COLUMNS FROM k_users LIKE 'kredi'") ? 'kredi' : 'credit';
$credit = (float)$db->get_var("SELECT COALESCE($col,0) FROM k_users WHERE id='{$uid}'");
$_SESSION['user']->credit=$credit;
echo json_encode(['credit'=>$credit]);
