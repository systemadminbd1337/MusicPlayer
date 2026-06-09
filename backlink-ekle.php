<?php
include "header.php";
$id = get('id');
$link = $db->get_row("select * from k_multi where id='$id'");

if(@$_POST)
{
	$site = post('site');
	$title = post('title');
	$ekle = $db->query("insert into k_multi(uid,link,title,tip)values('$user->id','$site','$title','1')");
	if($ekle)
	{
		echo '<div class="alert alert-success">Eklendi</div>';
	}
}
?>

	<div class="alert alert-success">Backlink Ekle</div>
      <div class="jumbotron">
      	<div class="alert alert-danger">
	  		<form action="" method="post" accept-charset="utf-8">
	  			<div class="form-group">
		  			<label for="">Sitenizin Linki</label>
		  			<input id="" type="text" name="site" value="" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Link Title</label>
		  			<input id="" type="text" name="title" value="" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<input type="submit" value="Gönder" class="btn btn-primary">
	  			</div>
	  		</form>	  		
      	</div>
      </div>
<?php
include "footer.php";
?>