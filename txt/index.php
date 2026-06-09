<?php
// index.php
include "header.php"; 

if (empty($user)) {
    if (!empty($_SESSION['user'])) {
        $user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
    }
}
if (empty($user) || empty($user->id)) {
    header("Location: login.php");
    exit;
}
$uid = (int)$user->id;

// DB Queries
$singleLinks   = (int) $db->get_var("SELECT count(*) FROM k_single WHERE uid={$uid}");
$multiLinks    = (int) $db->get_var("SELECT count(*) FROM k_multi WHERE uid={$uid} AND tip='1'");
$multiAnchors  = (int) $db->get_var("SELECT count(*) FROM k_multi WHERE uid={$uid} AND tip='2'");
$linkMarket    = (int) $db->get_var("SELECT count(tip) FROM k_linkdb WHERE tip='1'");
$antiMarket    = (int) $db->get_var("SELECT count(tip) FROM k_linkdb WHERE tip='2'");
$orderCount    = (int) $db->get_var("SELECT count(*) FROM k_orders WHERE uid={$uid}");

$user->kota = isset($user->kota) ? (int)$user->kota : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - HackLink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container my-4">
  <h2 class="mb-4 fw-bold text-light">📊 Dashboard</h2>

  <div class="row g-4">
    <!-- Package Info -->
    <div class="col-md-6 col-xl-3">
      <div class="card shadow-sm border-0 h-100 bg-gradient" style="background: linear-gradient(45deg,#2a2a72,#009ffd);">
        <div class="card-body text-center text-white">
          <h5 class="card-title">Package</h5>
          <p class="display-6 fw-bold">
            <?php 
              if($user->kota === 0){
                echo "Unlimited";
              } else {
                $remaining = $user->kota - $singleLinks;
                if ($remaining < 0) $remaining = 0;
                echo "Total: ".htmlspecialchars($user->kota)."<br>Remaining: ".htmlspecialchars($remaining);
              }
            ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Backlinks -->
    <div class="col-md-6 col-xl-3">
      <div class="card shadow-sm border-0 h-100 bg-gradient" style="background: linear-gradient(45deg,#1e9600,#fff200,#ff0000);">
        <div class="card-body text-center text-white">
          <h5 class="card-title">Your Backlinks</h5>
          <p class="display-6 fw-bold"><?= htmlspecialchars($multiLinks) ?></p>
        </div>
      </div>
    </div>

    <!-- Link Archive -->
    <div class="col-md-6 col-xl-3">
      <div class="card shadow-sm border-0 h-100 bg-gradient" style="background: linear-gradient(45deg,#fc466b,#3f5efb);">
        <div class="card-body text-center text-white">
          <h5 class="card-title">Link Archive</h5>
          <p class="display-6 fw-bold"><?= htmlspecialchars($singleLinks) ?></p>
        </div>
      </div>
    </div>

    <!-- Database -->
    <div class="col-md-6 col-xl-3">
      <div class="card shadow-sm border-0 h-100 bg-gradient" style="background: linear-gradient(45deg,#ff6a00,#ee0979);">
        <div class="card-body text-center text-white">
          <h5 class="card-title">Link Database</h5>
          <p class="display-6 fw-bold"><?= htmlspecialchars($orderCount) ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Extra Stats -->
  <div class="row g-4 mt-3">
    <div class="col-md-6">
      <div class="card shadow-sm border-0 bg-gradient" style="background: linear-gradient(45deg,#00b4db,#0083b0);">
        <div class="card-body text-white">
          <h5 class="card-title">Link Market</h5>
          <p class="fs-5 fw-semibold"><?= htmlspecialchars($linkMarket) ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm border-0 bg-gradient" style="background: linear-gradient(45deg,#e53935,#e35d5b);">
        <div class="card-body text-white">
          <h5 class="card-title">Anti-Market</h5>
          <p class="fs-5 fw-semibold"><?= htmlspecialchars($antiMarket) ?></p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
