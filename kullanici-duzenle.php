<?php
include "header.php";
$id = get('id');
if($user->level != 2)
{
	exit();
}
if(@$_POST)
{
	$sifre = post('sifre');	
	$limit = post('limit');
	$odeme = post('odeme');
	$hulk = post('hulk');

	$ekle = $db->query("update k_users set pass='$sifre',kota='$limit',odeme='$odeme',hulk='$hulk' where id='$id'");
	if($ekle)
	{
		echo '<div class="alert alert-success">Kullanıcı Tanımlandı</div>';
	}
	
}
$kul = $db->get_row("select * from k_users where id='$id'");
?>
      <div class="jumbotron">
	    <h2>Kullanıcı Ekle</h2>
      	<div class="alert alert-danger">
	  		<form action="" method="post" accept-charset="utf-8">
	  			<div class="form-group">
		  			<label for="">Kullanıcı Adı</label>
		  			<input id="" type="text" name="username" value="<?=$kul->user?>" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Şifre</label>
		  			<input id="" type="text" name="sifre" value="<?=$kul->pass?>" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Limit (Kaç link alabilsin)</label>
		  			<input id="" type="text" name="limit" value="<?=$kul->kota?>" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Link başı limit</label>
		  			<input id="" type="text" name="hulk" value="<?=$kul->hulk?>" class="form-control">
	  			</div>	 		 
	  			<div class="form-group">
		  			<label for="">Ödeme Tarihi</label>
		  			<input id="" type="text" name="odeme" value="<?=$kul->odeme?>" class="form-control">
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