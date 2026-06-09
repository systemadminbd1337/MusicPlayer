<?php
// verify-otp.php — with auto DB column check
include "config.php";

// Session guard
if (empty($_SESSION['user'])) { redirect('login.php'); exit(); }
$user = is_object($_SESSION['user']) ? $_SESSION['user'] : (object)$_SESSION['user'];
$uid  = (int)$user->id;

// --- AUTO CREATE MISSING COLUMNS (otp_code, otp_expires, otp_enabled)
try {
    $cols = $db->get_results("SHOW COLUMNS FROM k_users", ARRAY_A);
    $have = array_column($cols, 'Field');
    $alter = [];

    if (!in_array('otp_code', $have))     $alter[] = "ADD COLUMN otp_code VARCHAR(6) DEFAULT NULL";
    if (!in_array('otp_expires', $have))  $alter[] = "ADD COLUMN otp_expires DATETIME DEFAULT NULL";
    if (!in_array('otp_enabled', $have))  $alter[] = "ADD COLUMN otp_enabled TINYINT(1) DEFAULT 0";

    if (!empty($alter)) {
        $sql = "ALTER TABLE k_users " . implode(", ", $alter);
        $db->query($sql);
    }
} catch (Throwable $e) {
    error_log("OTP column check failed: " . $e->getMessage());
}

// Reload user row
$row = $db->get_row("SELECT * FROM k_users WHERE id='$uid' LIMIT 1");

$otpMsg = "";

// ---------------- ENABLE OTP (send code) ----------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['enable_otp'])){
    $code = str_pad(rand(0,999999),6,'0',STR_PAD_LEFT);
    $expires = date("Y-m-d H:i:s", strtotime("+5 minutes"));
    try {
        $pdo->prepare("UPDATE k_users SET otp_code=?, otp_expires=? WHERE id=?")->execute([$code,$expires,$uid]);
    } catch (Throwable $e) {
        error_log("OTP enable error: ".$e->getMessage());
    }

    // ইমেইল পাঠানো
    $to = $row->email ?? '';
    $subject = "[HackLink Panel] Your OTP Code";
    $message = "Hello {$row->username},\n\nYour OTP code is: $code\nThis code will expire in 5 minutes.\n\n— HackLink Panel Security";
    @mail($to,$subject,$message);

    // Dev/testing fallback
    if (empty($to)) $otpMsg .= "<div class='alert alert-warning'>⚠️ Test Mode: OTP Code = <b>$code</b></div>";

    $otpMsg .= "<div class='alert alert-info'>📩 A 6-digit code has been sent to your email.</div>";

    $row->otp_code = $code;
    $row->otp_expires = $expires;
}

// ---------------- VERIFY OTP ----------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['verify_otp'])){
    $input = trim($_POST['otp_code'] ?? '');
    if ($input && $input === ($row->otp_code ?? '') && strtotime($row->otp_expires) > time()){
        try {
            $pdo->prepare("UPDATE k_users SET otp_enabled=1, otp_code=NULL, otp_expires=NULL WHERE id=?")
                ->execute([$uid]);
        } catch (Throwable $e) {
            error_log("OTP verify error: ".$e->getMessage());
        }
        $_SESSION['otp_success'] = "✅ OTP Enabled Successfully.";
        redirect("profile.php");
    } else {
        $otpMsg = "<div class='alert alert-danger'>❌ Invalid or expired code.</div>";
    }
}

include "header.php";
?>

<div class="row">
  <div class="col-md-6 offset-md-3">
    <div class="card bg-dark text-light shadow">
      <div class="card-header fw-bold">One-Time Password (OTP)</div>
      <div class="card-body">
        <?= $otpMsg ?>

        <?php if(empty($row->otp_enabled)): ?>
          <?php if(!empty($row->otp_code)): ?>
            <!-- Verification Form -->
            <form method="post">
              <div class="mb-3">
                <label class="form-label">Enter 6-digit OTP code</label>
                <input type="text" name="otp_code" maxlength="6" class="form-control" placeholder="123456" required>
              </div>
              <button type="submit" name="verify_otp" class="btn btn-success">Verify</button>
              <a href="profile.php" class="btn btn-secondary">Cancel</a>
            </form>
          <?php else: ?>
            <!-- First enable -->
            <form method="post">
              <button type="submit" name="enable_otp" class="btn btn-primary">Send OTP Code</button>
              <a href="profile.php" class="btn btn-secondary">Back</a>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-success">✅ OTP Already Enabled</div>
          <a href="profile.php" class="btn btn-light">Back to Profile</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include "footer.php"; ?>
