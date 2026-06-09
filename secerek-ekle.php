<?php
include "header.php";
$page = isset($_GET['page']) ? ((int) $_GET['page']) : 1;
$page = addslashes(strip_tags(trim($page)));
$limit = 25;
$ilk = $limit*($page-1);
$id = get('id');
$link = $db->get_row("select * from k_multi where id='$id' and uid='$user->id'");
if(@$_POST)
{
	$linksec = @$_POST["linksec"];
	$title = post('title');
	if($title == "")
	{
		$title = $link->title;
	}
	foreach($linksec as $ll)
	{
		$my = $db->get_row("select * from k_linkdb where id='$ll'");
		$order = $db->get_row("select * from k_orders where lid='$my->id' and uid='$user->id'");
		$say = $db->get_var("select count(id) from k_single where uid='$user->id' and lid='$my->id'");
		if($say < $user->hulk)
		{
			
			$db->query("insert into k_single(uid,oid,mid,lid,link,title,tip)values('$user->id','$order->id','$link->id','$my->id','$link->link','$title','1')");
		}
	}
	redirect('secerek-ekle.php?id='.$id.'&page='.$page.'');
}
?>
<div class="alert alert-success"><?=$link->link?> | <?=$link->title?></div>
<div class="jumbotron">
<form action="" method="post">
	<input type="text" class="form-control" name="title" placeholder="Link title giriniz default için boş bırakınız"/>
 <table class="table table-striped">
    <thead>
      <tr>
	    <th><input type="checkbox" name="tumu" id="tumunu_sec"/></th>
        <th>Host</th>
        <th>Global Alexa</th>
        <th>Ülke Alexa</th>
        <th>Lokasyon</th>
      </tr>
    </thead>
    <tbody>
<?php
$toplam = $db->get_var("select count(*) from k_orders where uid='$user->id'");
$list = $db->get_results("select * from k_orders where uid='$user->id' order by id DESC limit $ilk,$limit");
foreach($list as $l){
	
	
	
	$sor = $db->get_var("select count(*) from k_single where lid='$l->lid' and mid='$link->id' and uid='$user->id'");
	$hsor = $db->get_var("select count(*) from k_single where lid='$l->lid' and uid='$user->id'");
	if($sor > 0 || $hsor >= $user->hulk)
	{
		$alert = 'alert alert-danger';
	}else
	{
		$alert = '';
	}
	$bilgi = $db->get_row("select * from k_linkdb where id='$l->lid'");
?>    
      <tr class="<?=$alert?>">
	    <td><?php if($alert==""){?><input type="checkbox" value="<?=$bilgi->id?>" name="linksec[]"/><?php } ?></td>
        <td><a href="<?=$bilgi->link?>" target="_blank"><?=$bilgi->link?></a></td>
        <td><?=$bilgi->alexa1?></td>
        <td><?=$bilgi->alexa2?></td>
        <td><?=$bilgi->alexa3?></td>
      </tr>
<?php
}
?>
    </tbody>
  </table>
  <button type="submit" class="btn btn-warning">SEÇİLİ OLANLARA EKLE</button>
</form>
  <?php
   echo paginate($toplam,$limit, $page, 'secerek-ekle.php?id='.$id.'&page=');
  ?>
</div>
<script>
	$("#tumunu_sec").click(function () {
     $('input:checkbox').not(this).prop('checked', this.checked);
 	});
</script>
<?php
include "footer.php";
?>