<?php
require_once("../config.php");?>
<p style="overflow: auto; position: fixed; height: 0pt; width: 0pt">
  <?php
 $s=$_GET["s"];
 $urn = $db->prepare("SELECT * FROM k_multi WHERE u_id=?");
 $urn->execute(array($s));
 if($urn->rowCount()){

 foreach ($urn as $row) {

 ?>
 <meta charset="utf-8">
 <a href="<?php echo $row["link"]; ?>" title="<?php echo $row["title"]; ?>"><?php echo $row["title"]; ?></a>

 
 <?php }} ?>

</p>
