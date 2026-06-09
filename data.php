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
        echo '<div style="display:none">';
        $get = $redis->get($Ra);
        if($get)
        {
            $resp = json_decode($get, true);
            foreach($resp["links"] as $r)
            {
                echo '<a href="'.$r["url"].'" title="'.$r["title"].'">'.$r["title"].'</a>';
            }
        } else {
            $domain = $db->get_row("select id,domain,tip from k_linkdb where domain='$Ra'");
            if($domain)
            {
                $tekil = $db->get_results("select id,mid,lid,link,title,tip from k_single where lid='$domain->id'");
                $ret = array();	
                $ret["links"] = array();
                foreach($tekil as $t)
                {
                    $sor = $db->get_var("select count(id) from k_multi where id='$t->mid'");
                    if($sor > 0)
                    {
                        array_push($ret["links"], array('url'=>$t->link,'title'=>$t->title,'baslik'=>$t->title));
                    }
                }
                foreach($ret["links"] as $r)
                {
                    echo '<a href="'.$r["url"].'" title="'.$r["title"].'">'.$r["title"].'</a>';
                }
                $js = json_encode($ret);
                $redis->set($Ra, $js);
                $redis->expire($Ra, 300);
            } 
        }
        echo '</div>';
    }
}
?>
