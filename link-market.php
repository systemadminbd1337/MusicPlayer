<?php
include "header.php";
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);
$limit = 25;
$ilk = $limit * ($page - 1);

// প্যাকেজ / লিমিট চেক
if ($user->kota < 1) {
    echo '<div class="alert alert-warning"> You don't have any packages </div>';
    include "footer.php";
    exit();
}
$tekilSay = $db->get_var("SELECT COUNT(*) FROM k_orders WHERE uid='$user->id'");
$kalan = $user->kota - $tekilSay;
if ($kalan < 1) {
    echo '<div class="alert alert-danger">⚠️Your package limit has been reached.</div>';
    include "footer.php";
    exit();
}

// Satın alma işlem
if (@$_GET["islem"] == "satinal") {
    $lid = get('id');
    $order = $db->get_var("SELECT COUNT(*) FROM k_orders WHERE lid='$lid' AND uid='$user->id'");
    if ($order < 1 && $kalan > 0) {
        $db->query("INSERT INTO k_orders(uid,lid) VALUES('$user->id','$lid')");
        echo '<div class="alert alert-success">✅ Purchase completed.</div>';
    } else {
        echo '<div class="alert alert-warning">⚠️ This link already exists in your archive or your limit has been reached.</div>';
    }
}
?>

<div class="container my-4">
  <h2 class="mb-4 fw-bold">🛒 Link Market</h2>
  <p class="alert alert-info">Connected Limit: <strong><?=$kalan?></strong> (Total Package: <?=$user->kota?>)</p>

  <div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>Host</th>
            <th>Global Alexa</th>
            <th>Country Alexa</th>
            <th>Location</th>
            <th>Option</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $toplam = $db->get_var("SELECT COUNT(id) FROM k_linkdb WHERE tip='1' AND durum='1'");
          $list = $db->get_results("SELECT * FROM k_linkdb WHERE tip='1' AND durum='1' ORDER BY id DESC LIMIT $ilk,$limit");

          foreach ($list as $link) {
              $order = $db->get_var("SELECT COUNT(*) FROM k_orders WHERE lid='$link->id' AND uid='$user->id'");
              $alert = ($order > 0) ? "table-danger" : "";
              ?>
              <tr class="<?=$alert?>">
                <td><a href="<?=$link->link?>" target="_blank"><?=$link->link?></a></td>
                <td><?=$link->alexa1?></td>
                <td><?=$link->alexa2?></td>
                <td><?=$link->alexa3?></td>
                <td>
                  <?php if ($order > 0) { ?>
                    <button class="btn btn-sm btn-secondary" disabled>✔️ Purchased</button>
                  <?php } else { ?>
                    <a class="btn btn-sm btn-success" href="link-market.php?islem=satinal&id=<?=$link->id?>&page=<?=$page?>">
                      🛒 Purchase
                    </a>
                  <?php } ?>
                </td>
              </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <?php echo paginate($toplam, $limit, $page, 'link-market.php?page='); ?>
  </div>
</div>

<?php include "footer.php"; ?>
