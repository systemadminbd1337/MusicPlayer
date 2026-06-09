<?php
	if(@$_GET["x"])
	{
   		$redis = new Redis();
   		$redis->connect('127.0.0.1', 6379);
		$Ra = addslashes(strip_tags(trim(@$_GET["x"])));
		$Ra = str_replace('http://','', $Ra);
		$Ra = str_replace('https://','', $Ra);
		$Ra = str_replace('www.','', $Ra);
		$Ra = str_replace('/','', $Ra);
		if($Ra)
		{
			include "config.php";
			echo '<div style="display:none">
			';	
			$get = $redis->get($Ra);
			if($get)
			{
				
				$ret = array();	
				$ret["links"] = array();
				$resp = json_decode($get, true);

                foreach($resp["links"] as $r)
                {
                    echo '<a href="'.$r["url"].'" title="'.$r["title"].'">'.$r["title"].'</a>';
                }
			}else
			{
				
				$domain = $db->get_row("select id,domain,tip from k_linkdb where domain='$Ra'");
				if($domain)
				{
					$redis->select(1);
               		$tekil = $redis->get($domain->id);
               		$linkler = json_decode($tekil, true);
				    $ret = array();	
					$ret["links"] = array();
	                foreach($linkler as $t)
	                {
		               $mid = $t["mid"];
		               $sor = $db->get_var("select count(id) from k_multi where id='$mid'");
		               if($sor > 0)
		               {
	                   		array_push($ret["links"], array('url'=>$t["link"],'title'=>$t["title"],'baslik'=>$t["title"]));
	                   }
	                }
	                foreach($ret["links"] as $r)
	                {
	                    echo '<a href="'.$r["url"].'" title="'.$r["title"].'">'.$r["title"].'</a>';
	                }
	                $redis->select(0);
	                $js = json_encode($ret);
	                $redis->set($Ra, $js);
	                $redis->expire($Ra, 300);
				}
			}
			echo '</div>';
		}
	}
session_destroy();