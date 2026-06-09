<?php
    include "/home/googlee/public_html/link/config.php";
    $link = $db->get_row("select * from k_linkdb order by ups ASC");
	$kaynak = getBot($link->link."?loremipsum=true");
	$ups = time();

	if(strstr($kaynak, '<!--LOREMIPSUM-->'))
	{
		$db->query("update k_linkdb set ups='$ups', durum='1' where id='$link->id'");
	}else
	{
		$db->query("update k_linkdb set ups='$ups', durum='2' where id='$link->id'");
	}