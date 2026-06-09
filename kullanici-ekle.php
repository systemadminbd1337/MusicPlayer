<?php
include "header.php";
if($user->level != 2)
{
	exit();
}
if(@$_POST)
{
	$kullanici = post('username');
	$sifre = post('sifre');	
	$limit = post('limit');
	$odeme = post('odeme');
	$hulk = post('hulk');
	$sor = $db->get_var("select count(*) from k_users where user='$kullanici'");
	if($sor < 1)
	{
		$ekle = $db->query("insert into k_users(user,pass,level,kota,odeme,hulk)values('$kullanici','$sifre','1','$limit','$odeme','$hulk')");
		if($ekle)
		{
			echo '<div class="alert alert-success">Kullanıcı Tanımlandı</div>';
		}
	}else
	{
		echo '<div class="alert alert-warning">Bu kullanıcı var</div>';
	}
}
?>
      <div class="jumbotron">
	    <h2>Kullanıcı Ekle</h2>
      	<div class="alert alert-danger">
	  		<form action="" method="post" accept-charset="utf-8">
	  			<div class="form-group">
		  			<label for="">Kullanıcı Adı</label>
		  			<input id="" type="text" name="username" value="" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Şifre</label>
		  			<input id="" type="text" name="sifre" value="" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Limit (Kaç link alabilsin)</label>
		  			<input id="" type="text" name="limit" value="1" class="form-control">
	  			</div>	 
	  			<div class="form-group">
		  			<label for="">Link başı limit</label>
		  			<input id="" type="text" name="hulk" value="1" class="form-control">
	  			</div>	 
	  			<div class="form-group">
		  			<label for="">Ödeme Tarihi</label>
		  			<input id="" type="text" name="odeme" value="0" class="form-control">
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