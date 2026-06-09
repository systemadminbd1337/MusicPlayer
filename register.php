<?php
session_start();
include "config.php"; // এখানে $pdo থাকবে

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $country = $_POST['country'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($username) && !empty($email) && !empty($country) && !empty($password)) {
        // পাসওয়ার্ড হ্যাশ করে রাখুন
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO k_users (name, username, email, country, password) VALUES (?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $username, $email, $country, $hashedPassword]);
            $_SESSION['success'] = "Registration successful! Please login.";
            $success = true;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - HackLink Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
  <style>
  :root{--bg:#05060a;--panel:#0b1019;--neon1:#00ff9d;--neon2:#00e6ff;}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:radial-gradient(circle at 20% 20%,rgba(0,255,157,.06),transparent 60%),#000814;
  font-family:'Share Tech Mono',monospace;color:#c7f9e8;overflow:hidden;}
  .card{background:var(--panel);border-radius:16px;border:1px solid rgba(0,255,157,.15);
  box-shadow:0 0 25px rgba(0,230,255,.08);overflow:hidden;}
  .card-header{background:linear-gradient(90deg,var(--neon1),var(--neon2));
  color:#001214;font-family:'Orbitron',sans-serif;font-size:20px;letter-spacing:1px;text-transform:uppercase;}
  .card-body{background:rgba(255,255,255,.02);padding:1.8rem;}
  label{font-size:.9rem;color:#bfffe9;margin-bottom:4px;}
  .form-control{background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.06);
  color:#bfffe9;padding:12px;border-radius:10px;}
  .form-control:focus{border-color:var(--neon2);box-shadow:0 0 10px rgba(0,255,157,.3);}
  .btn-success{background:linear-gradient(90deg,var(--neon1),var(--neon2));
  border:none;color:#001214;font-weight:700;letter-spacing:.6px;border-radius:12px;}
  .btn-success:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(0,230,255,.15);}
  .alert{border-radius:10px;font-size:.9rem;}
  a{color:var(--neon2);text-decoration:none;}
  a:hover{color:var(--neon1);}
  /* ✅ Success Overlay */
  .success-overlay{
    position:fixed;inset:0;background:rgba(0,10,25,.92);
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    z-index:9999;text-align:center;font-family:'Orbitron',sans-serif;color:#00ff9d;
    animation:fadeIn .6s ease forwards;
  }
  .success-overlay h2{font-size:2rem;text-shadow:0 0 15px #00ff9d;}
  .countdown{font-size:1.3rem;color:#00e6ff;margin-top:10px;text-shadow:0 0 10px #00e6ff;}
  .bar{margin-top:20px;width:300px;height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;}
  .bar-inner{width:0;height:100%;background:linear-gradient(90deg,var(--neon1),var(--neon2));animation:fillBar 3s linear forwards;}
  @keyframes fillBar{from{width:0;}to{width:100%;}}
  @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
  </style>
</head>
<body class="d-flex align-items-center" style="height:100vh;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-lg">
        <div class="card-header text-center fw-bold">Register</div>
        <div class="card-body">
          <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
          <?php elseif(!empty($success)): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?? 'Registration successful!' ?></div>
          <?php endif; ?>
          <?php if(empty($success)): ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Country</label>
              <input type="text" name="country" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Register</button>
          </form>
          <div class="mt-3 text-center small">
            Already have an account? <a href="login.php">Login here</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if(!empty($success)): ?>
<!-- ✅ Neon Glow Success Overlay + Redirect Countdown -->
<div class="success-overlay" id="successOverlay">
  <h2>✅ REGISTRATION SUCCESSFUL</h2>
  <p>Your account has been created successfully!</p>
  <div class="countdown" id="countdown">Redirecting to login in 3...</div>
  <div class="bar"><div class="bar-inner"></div></div>
</div>
<script>
let sec=3;
const cd=document.getElementById('countdown');
const timer=setInterval(()=>{
  sec--; cd.textContent=`Redirecting to login in ${sec}...`;
  if(sec<=0){clearInterval(timer);window.location.href='login.php';}
},1000);
</script>
<?php endif; ?>
</body>
</html>
