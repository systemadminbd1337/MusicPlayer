<?php
include "header.php";
if($user->level != 2)
{
	exit();
}
if(@$_POST)
{
	$site = post('site');
	$pl = parse_url($site);
	$domain = str_replace('www.', '', $pl["host"]);
	$domain = str_replace('/', '', $domain);
	$tipi = post('tipi');	
	$alexa = GetAlexa($site);
	$ulke = $alexa["Ulke"];
	$genel = $alexa["Global"];
	$lokasyon = $alexa["Lokasyon"];
	$time = time();
	$ekle = $db->query("insert into k_linkdb(domain,link,alexa1,alexa2,alexa3,tip,ups,durum)values('$domain','$site','$genel','$ulke','$lokasyon','$tipi','$time','1')");
	if($ekle)
	{
		echo '<div class="alert alert-success">Ekleme Tamamlandı</div>';
	}

}
?>
      <div class="jumbotron">
	    <h2>Site Ekle</h2>
      	<div class="alert alert-danger">
	  		<form action="" method="post" accept-charset="utf-8">
	  			<div class="form-group">
		  			<label for="">Site Url</label>
		  			<input id="" type="text" name="site" value="" class="form-control">
	  			</div>
	  			<div class="form-group">
		  			<label for="">Link Tipi</label>
		  			<select name="tipi" class="form-control">
			  			<option value="1">Link</option>
			  			<option value="2">Anti</option>
		  			</select>
	  			</div>
	  			<div class="form-group">
		  			<input type="submit" value="Gönder" class="btn btn-primary">
	  			</div>
	  		</form>	  		
      	</div>
      	<textarea class="form-control" style="height: 200px"><?php
	      		include("sifrele.php");
	      		echo trim($mycode);
	      	?>
      	</textarea>
      </div>
<?php
include "footer.php";
?>