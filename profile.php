<?php
include "config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Auth
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
$uid  = (int)($user->id ?? 0);

// 🧩 esc()
function esc($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// 🔍 Load user
$row = $db->get_row("SELECT * FROM k_users WHERE id='{$uid}' LIMIT 1");
if(!$row){
  $row=(object)['username'=>'','email'=>'','country'=>'','api_key'=>'','otp_enabled'=>0,'profile_pic'=>'default.png'];
} else {
  $row->profile_pic = $row->profile_pic ?? 'default.png';
}

// ---------------- PROFILE UPDATE ----------------
$updateMsg='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])){
  $email   = trim($_POST['email']??'');
  $country = trim($_POST['country']??'');
  if($email){
    $stmt=$pdo->prepare("UPDATE k_users SET email=?, country=? WHERE id=?");
    $stmt->execute([$email,$country,$uid]);
    $row->email=$email; $row->country=$country;
    $updateMsg="<div class='alert neon-success'>✅ Profile updated.</div>";
  } else $updateMsg="<div class='alert neon-error'>⚠️ Email cannot be empty.</div>";
}

/* ---------------- PROFILE PIC UPLOAD (❌ DISABLED) ----------------
$picMsg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['profile_pic'])){
  $file=$_FILES['profile_pic'];
  if($file['error']==0){
    $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
    $allowed=['jpg','jpeg','png','gif'];
    if(in_array(strtolower($ext),$allowed)){
      $targetDir="Uploads/";
      if(!is_dir($targetDir)) mkdir($targetDir,0777,true);
      $fileName="user_{$uid}.".$ext;
      $path=$targetDir.$fileName;
      move_uploaded_file($file['tmp_name'],$path);
      $pdo->prepare("UPDATE k_users SET profile_pic=? WHERE id=?")->execute([$fileName,$uid]);
      $row->profile_pic=$fileName;
      $picMsg="<div class='alert neon-success'>🖼️ Profile picture updated.</div>";
    } else $picMsg="<div class='alert neon-error'>❌ Invalid image format.</div>";
  }
}
---------------- END DISABLED ---------------- */
$picMsg = ''; // keep variable empty to avoid undefined notices

// ---------------- PASSWORD UPDATE ----------------
$passMsg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])){
  $p1=$_POST['newpass']??''; $p2=$_POST['newpass2']??'';
  if($p1===$p2 && strlen($p1)>=8){
    $hash=password_hash($p1,PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE k_users SET password=? WHERE id=?")->execute([$hash,$uid]);
    $passMsg="<div class='alert neon-success'>✅ Password changed.</div>";
  } else $passMsg="<div class='alert neon-error'>❌ Passwords do not match or too short.</div>";
}

// ---------------- GENERATE API KEY ----------------
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['generate_api'])){
  $api=bin2hex(random_bytes(16));
  $pdo->prepare("UPDATE k_users SET api_key=? WHERE id=?")->execute([$api,$uid]);
  $row->api_key=$api;
}

include "header.php";
?>

<style>
body{background:#050505;color:#c8ffc8;font-family:'Consolas',monospace;}
.card{background:#0c0c0c;border:1px solid #00ff99;box-shadow:0 0 15px #00ff99a0;border-radius:12px;}
.nav-pills .nav-link.active{background:linear-gradient(90deg,#00ff99,#00ccff);color:#000;font-weight:bold;}
.btn-hacker{background:linear-gradient(90deg,#00ff99,#00ccff);border:none;color:#000;font-weight:600;transition:all .2s;}
.btn-hacker:hover{box-shadow:0 0 15px #00ffcc;}
.alert{border:none;padding:10px 15px;margin-bottom:10px;border-left:4px solid;}
.alert.neon-success{color:#0f0;border-color:#0f0;background:#001a00;}
.alert.neon-error{color:#ff4444;border-color:#f00;background:#220000;}
.neon-avatar{width:120px;height:120px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:40px;background:#001b10;border:3px solid #00ff99;box-shadow:0 0 20px #00ff99a0;margin:0 auto 15px auto;}
hr{border-color:#00ff99;}
input.form-control{background:#000;border:1px solid #00ff99;color:#0f0;}
input.form-control:focus{background:#000;border-color:#00ccff;box-shadow:0 0 10px #00ccffaa;color:#0ff;}
.tab-pane{padding-top:15px;}
img.neon-pic{border:2px solid #00ff99;border-radius:50%;box-shadow:0 0 20px #00ff99a0;width:120px;height:120px;object-fit:cover;}
/* 🕶️ Hide upload field entirely */
.hidden-upload{display:none !important;visibility:hidden !important;}
</style>

<div class="container py-4">
  <div class="row g-4">
    <!-- Sidebar -->
    <div class="col-lg-3">
      <div class="card text-center p-3">
        <div class="mb-3">
          <?php $pic = $row->profile_pic ?: 'default.png'; ?>
          <img src="Uploads/<?= esc($pic) ?>" class="neon-pic" alt="Profile Picture">
        </div>
        <h5><?= esc($row->username) ?></h5>
        <p class="text-muted small"><?= esc($row->email ?: 'Not set') ?></p>
        <ul class="nav nav-pills flex-column">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#profile">👤 Profile</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#security">🔒 Security</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#api">⚙️ API / OTP</a></li>
        </ul>
      </div>
    </div>

    <!-- Main -->
    <div class="col-lg-9">
      <div class="tab-content">

        <!-- Profile -->
        <div class="tab-pane fade show active" id="profile">
          <div class="card p-3 mb-3">
            <h5 class="mb-3 border-bottom pb-2">Profile Information</h5>
            <?= $updateMsg . $picMsg ?>
            <form method="post">
              <div class="mb-2">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= esc($row->email) ?>" required>
              </div>
              <div class="mb-3">
                <label>Country</label>
                <input type="text" name="country" class="form-control" value="<?= esc($row->country) ?>">
              </div>

              <!-- 🖼️ Upload hidden -->
              <div class="hidden-upload">
                <label>Profile Picture</label>
                <input type="file" name="profile_pic" class="form-control">
              </div>

              <button type="submit" name="update_profile" class="btn btn-hacker w-100">💾 Save Changes</button>
            </form>
          </div>

          <!-- Last login info -->
          <div class="card p-3">
            <h5 class="mb-3 border-bottom pb-2">Last Login Information</h5>
            <p><strong>IP:</strong> <?= esc($row->last_login_ip ?? $_SERVER['REMOTE_ADDR']) ?></p>
            <p><strong>Country:</strong> <?= esc($row->last_login_country ?? 'Unknown') ?></p>
            <p><strong>Time:</strong> <?= esc($row->last_login ?? date('Y-m-d H:i:s')) ?></p>
          </div>
        </div>

        <!-- Security -->
        <div class="tab-pane fade" id="security">
          <div class="card p-3">
            <h5 class="mb-3 border-bottom pb-2">Change Password</h5>
            <?= $passMsg ?>
            <form method="post">
              <input type="password" name="newpass" class="form-control mb-2" placeholder="New Password" required>
              <input type="password" name="newpass2" class="form-control mb-2" placeholder="Confirm Password" required>
              <button type="submit" name="change_password" class="btn btn-hacker w-100">Change Password</button>
            </form>
          </div>
        </div>

        <!-- API / OTP -->
        <div class="tab-pane fade" id="api">
          <div class="card p-3">
            <h5 class="border-bottom pb-2 mb-3">API Key Management</h5>
            <?php if(!empty($row->api_key)): ?>
              <input type="text" readonly class="form-control mb-2" value="<?= esc($row->api_key) ?>">
            <?php endif; ?>
            <form method="post">
              <button type="submit" name="generate_api" class="btn btn-hacker w-100 mb-3">Generate API Key</button>
            </form>

            <h5 class="border-bottom pb-2 mb-3 mt-4">One-Time Password (OTP)</h5>
            <?php if(empty($row->otp_enabled)): ?>
              <form method="post" action="verify-otp.php">
                <button type="submit" name="enable_otp" class="btn btn-outline-light w-100">Enable OTP</button>
              </form>
            <?php else: ?>
              <div class="alert neon-success text-center">✅ OTP Enabled</div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include "footer.php"; ?>
