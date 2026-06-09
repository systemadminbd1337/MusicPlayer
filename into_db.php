<?php
   	$redis = new Redis();
   	$redis->connect('127.0.0.1', 6379);
   	$redis->select(1);
   	
   	include "config.php";
   	
   	$single = $db->get_results("select * from k_single");
   	foreach($single as $s)
   	{
	   	$c = $redis->get($s->lid);
	   	if($c)
	   	{
		   
		   	$j = json_decode($c, true);
		   	$v = array('uid' => $s->uid, 'oid' => $s->oid, 'mid'=> $s->mid, 'link' => $s->link, 'title' => $s->title);
		   	$j[] = $v;
		  	$redis->set($s->lid, json_encode($j));
		      	
	   	}else
	   	{
		   	$v = array();
		   	$v[] = array('uid' => $s->uid, 'oid' => $s->oid, 'mid'=> $s->mid, 'link' => $s->link, 'title' => $s->title);
		   	$data = json_encode($v);
		   	$redis->set($s->lid, $data);		   	
	   	}

	   	echo $s->id."\n";
   	}