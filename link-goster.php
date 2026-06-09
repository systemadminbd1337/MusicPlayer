<?php
include "header.php";
$page = isset($_GET['page']) ? ((int) $_GET['page']) : 1;
$page = addslashes(strip_tags(trim($page)));
$limit = 25;
$ilk = $limit*($page-1);
$id = get('id');
$link = $db->get_row("select * from k_multi where id='$id' and uid='$user->id'");
if(@$_GET["sil"])
{
	$sid = get('sil');
	$del = $db->query("delete from k_single where id='$sid' and uid='$user->id'");
	if($del)
	{
		redirect('link-goster.php?id='.$id.'');
	}
}
?>
<div class="alert alert-success"><?=$link->link?> | <?=$link->title?></div>

<div class="jumbotron">

 <table class="table table-striped">
    <thead>
      <tr>
        <th>Host</th>
        <th>Global Alexa</th>
        <th>Ülke Alexa</th>
        <th>Lokasyon</th>
        <th>Settings</th>
      </tr>
    </thead>
    <tbody>
<?php
$toplam = $db->get_var("select count(id) from k_single where mid='$id' and uid='$user->id' and tip='1'");
$list = $db->get_results("select * from k_single where mid='$id' and uid='$user->id' and tip='1' order by id DESC limit $ilk,$limit");
foreach($list as $l){
	$bilgi = $db->get_row("select * from k_linkdb where id='$l->lid'");
?>    
      <tr>
        <td><a href="<?=$bilgi->link?>" target="_blank"><?=$bilgi->link?></a></td>
        <td><?=$bilgi->alexa1?></td>
        <td><?=$bilgi->alexa2?></td>
        <td><?=$bilgi->alexa3?></td>
	    <td><a class="btn btn-danger" href="?id=<?=$id?>&sil=<?=$l->id?>">SİL</a></td>
      </tr>
<?php
}
?>
</div>
<?php
include "footer.php";
?>