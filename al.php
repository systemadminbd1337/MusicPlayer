<?php
require_once("dir/ayar.php");?>
<p style="overflow: auto; position: fixed; height: 0pt; width: 0pt">
  <?php
 $s=$_GET["s"];
 $urn = $db->prepare("SELECT id,uid FROM k_multi WHERE id=?");
 $urn->execute(array($s));
 if($urn->rowCount()){

 foreach ($urn as $row) {

 ?>
 <meta charset="utf-8">
 <a href="<?php echo $row["link"]; ?>" title="<?php echo $row["title"]; ?>"><?php echo $row["title"]; ?></a>

 
 <?php }} ?>

</p>
