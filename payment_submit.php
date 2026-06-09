<?php
include "header.php";
if (empty($_SESSION['user'])) { redirect('login.php'); exit(); }
$user = (object) $_SESSION['user'];
$uid  = (int)$user->id;

// --- Safe escape function ---
function esc($v){ global $db; return $db->escape($v ?? ''); }

// --- Handle POST submission ---
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paket_id  = (int)($_POST['paket_id'] ?? 0);
    $currency  = esc($_POST['currency'] ?? 'USDT');
    $txn_id    = esc($_POST['txn_id'] ?? '');
    $amount    = (float)($_POST['amount'] ?? 0);

    if ($paket_id <= 0 || $txn_id === '' || $amount <= 0) {
        $msg = '<div class="alert alert-danger">⚠️ All fields are required!</div>';
    } else {
        try {
            // Insert pending payment
            $db->query("
                INSERT INTO k_payments (uid, paket_id, amount, usdt_amount, txn_id, status, currency, created_at)
                VALUES ('{$uid}', '{$paket_id}', '{$amount}', '{$amount}', '{$txn_id}', 'pending', '{$currency}', NOW())
            ");
            $msg = '<div class="alert alert-success">✅ Payment submitted successfully. Waiting for admin approval.</div>';
        } catch (Throwable $e) {
            $msg = '<div class="alert alert-danger">❌ Database error: '.htmlspecialchars($e->getMessage()).'</div>';
        }
    }
}

// --- Fetch packages ---
$paketler = $db->get_results("SELECT * FROM k_paketler ORDER BY fiyat ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Submit Payment - HackLink Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0e0c1d;color:#eee;font-family:'Share Tech Mono',monospace;}
.card{background:#161329;border:none;border-radius:16px;}
label{color:#b6f7ff;}
.btn-neon{background:linear-gradient(90deg,#00ff9d,#00e6ff);border:0;color:#001214;font-weight:700;}
.btn-neon:hover{box-shadow:0 0 15px rgba(0,255,157,.3);}
.alert{font-family:monospace;}
</style>
</head>
<body>
<div class="container my-5">
  <h2 class="fw-bold mb-4 text-center text-info">💳 Submit Payment</h2>

  <?= $msg ?>

  <div class="card p-4 shadow">
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Select Package</label>
        <select name="paket_id" class="form-select" required>
          <option value="">-- Choose Package --</option>
          <?php foreach ($paketler as $p): ?>
            <option value="<?=$p->id?>"><?=$p->baslik?> (<?=$p->fiyat?> USDT)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Select Currency</label>
        <select name="currency" class="form-select" required>
          <option value="USDT">USDT (TRC20)</option>
          <option value="BTC">Bitcoin (BTC)</option>
          <option value="ETH">Ethereum (ETH)</option>
          <option value="SOL">Solana (SOL)</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Amount</label>
        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Transaction ID / Hash</label>
        <input type="text" name="txn_id" class="form-control" placeholder="Paste your transaction hash" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Payment Wallet Address</label>
        <div class="alert alert-dark">
          💰 <b>USDT(TRC20):</b> TG8YxkQkM7xp8x2xUSDTvY12zx34Y8gG  
          <br>💰 <b>BTC:</b> bc1qexamplebtcaddress98ajd7  
          <br>💰 <b>ETH:</b> 0xExampleEthereumWalletAddress98AJK  
          <br>💰 <b>SOL:</b> 8xExampleSolanaAddressZxYY22  
        </div>
      </div>

      <button class="btn btn-neon w-100 py-2">🚀 Submit Payment</button>
    </form>
  </div>

  <div class="mt-4">
    <h5 class="text-center text-warning">⚠️ Note:</h5>
    <p class="text-center small">
      After submitting your transaction hash, please wait for admin approval.<br>
      Once approved, your credits or package will be automatically activated.
    </p>
  </div>
</div>
<?php include "footer.php"; ?>
</body>
</html>
