<?php
include "header.php";
$id = get('id');
$link = $db->get_row("select * from k_multi where id='$id' and uid='$user->id'");
if(@$_GET["ekle"] == "tumu")
{
	$order = $db->get_results("select * from k_orders where uid='$user->id'");

	foreach($order as $o)
	{

		 $sor = $db->get_var("select count(*) from k_single where lid='$o->lid' and mid='$link->id' and uid='$user->id'");
		 $say = $db->get_var("select count(*) from k_single where uid='$user->id' and lid='$o->lid'");

		 if($sor > 0 || $say >= $user->hulk)
		 {
		

		 }else
		 {

			$db->query("insert into k_single(uid,oid,mid,lid,link,title,tip)values('$user->id','$o->id','$link->id','$o->lid','$link->link','$link->title','1')");
 		 }
	}
	redirect('backlinker.php');
}
?>
<div class="alert alert-success">Tüm link arşivinizi ekleyebilir, yada seçerek ekleme yapabilirsiniz.</div>
      <div class="jumbotron">
	  	<div class="alert alert-info">
		  	<a href="?id=<?=$id?>&ekle=tumu" class="btn btn-warning">TÜMÜNE EKLE</a> <a href="secerek-ekle.php?id=<?=$id?>" class="btn btn-primary">SEÇEREK EKLE</a> 
	  	</div>
      </div>
<?php
include "footer.php";