<?php
include "header.php";
if (empty($_SESSION['user'])) { redirect('login.php'); exit(); }
$user = (object) $_SESSION['user'];

// --- টেবিল তৈরি (যদি না থাকে) ---
// ✅ AUTO_INCREMENT fix সহ
$db->query("
CREATE TABLE IF NOT EXISTS k_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uid INT NOT NULL,
    paket_id INT NOT NULL,
    currency ENUM('USDT','BTC','ETH','SOL') DEFAULT 'USDT',
    amount DECIMAL(10,2) DEFAULT 0,
    txn_id VARCHAR(150) DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    INDEX idx_uid (uid),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ✅ নিশ্চিত করো যে AUTO_INCREMENT রিসেট হয়েছে
try {
    $max = (int)$db->get_var("SELECT MAX(id) FROM k_payments");
    $next = $max + 1;
    $db->query("ALTER TABLE k_payments AUTO_INCREMENT = {$next}");
} catch (Throwable $e) {}

// --- প্যাকেজ লোড ---
$paketler = $db->get_results("SELECT * FROM k_paketler ORDER BY fiyat ASC");

// --- সাবমিট হ্যান্ডলার ---
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paket_id'])) {
    $paket_id = (int)$_POST['paket_id'];
    $txn_id = trim($_POST['txn_id'] ?? '');
    $currency = $_POST['currency'] ?? 'USDT';

    if ($paket_id <= 0 || $txn_id === '') {
        $msg = '<div class="alert alert-warning">⚠️ Please enter a valid Transaction ID.</div>';
    } else {
        $paket = $db->get_row("SELECT * FROM k_paketler WHERE id='{$paket_id}'");
        if (!$paket) {
            $msg = '<div class="alert alert-danger">❌ Invalid package selected.</div>';
        } else {
            // ✅ ডুপ্লিকেট চেক
            $exists = $db->get_var("SELECT COUNT(*) FROM k_payments WHERE uid='{$user->id}' AND paket_id='{$paket_id}' AND status='pending'");
            if ($exists > 0) {
                $msg = '<div class="alert alert-info">ℹ️ You already have a pending payment for this package.</div>';
            } else {
                // ✅ নিরাপদ ইনসার্ট
                $uid = (int)$user->id;
                $currency = addslashes($currency);
                $amount = (float)$paket->fiyat;
                $txn_id = addslashes($txn_id);
                $db->query("INSERT INTO k_payments (uid, paket_id, currency, amount, txn_id, status, created_at)
                            VALUES ('{$uid}', '{$paket_id}', '{$currency}', '{$amount}', '{$txn_id}', 'pending', NOW())");
                $msg = '<div class="alert alert-success">✅ Payment request submitted! Please wait for admin approval.</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#0e0c1d; color:#eee; }
    .card { background:#161329; border:none; border-radius:16px; }
    .package-title { font-size:20px; font-weight:700; color:#9f7aea; }
    .package-price { font-size:26px; font-weight:800; color:#ff9800; }
    .adv-card { background:#1f1b38; border-radius:14px; padding:20px; text-align:center; }
    .adv-card h5 { color:#9f7aea; font-weight:600; }
    .credit-badge { background:linear-gradient(45deg, #ffd700, #ffb700); color:#000; font-weight:bold; padding:8px 15px; border-radius:20px; }
  </style>
</head>
<body>
<div class="container my-4">

  <?= $msg ?>

  <div class="alert alert-warning text-dark fw-bold">
    💰 <strong>Exchange Rate: 1 USD = 1 Credit</strong> - You will receive credits instead of quota
  </div>

  <h2 class="mb-4 text-light fw-bold">💳 Hacklink Packages</h2>

  <div class="row g-4">
    <?php if ($paketler) { foreach ($paketler as $paket) { 
        // Calculate credits based on package price (1 USD = 1 Credit)
        $credits = (int)$paket->fiyat;
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="package-title text-center mb-3"><?= htmlspecialchars($paket->baslik) ?></h5>
            <p class="package-price text-center mb-3">$<?= (int)$paket->fiyat ?> USD</p>
            
            <!-- Credit Display -->
            <div class="text-center mb-3">
              <span class="credit-badge">🎯 <?= $credits ?> Credits</span>
            </div>

            <ul class="list-group list-group-flush mb-3">
              <li class="list-group-item bg-dark text-light">
                <strong>💰 Credits:</strong> <?= $credits ?> Credits
              </li>
              <li class="list-group-item bg-dark text-light">
                <strong>⚡ Rate:</strong> 1 USD = 1 Credit
              </li>
              <li class="list-group-item bg-dark text-light">
                <strong>🕐 Duration:</strong> <?= (int)$paket->sure ?> Days
              </li>
              <li class="list-group-item bg-dark text-light">
                <strong>🛠️ Support:</strong> 24/7 Available
              </li>
              <li class="list-group-item bg-dark text-light">
                <strong>🔑 Access:</strong> Panel + API
              </li>
            </ul>
            <div class="mt-auto text-center">
              <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#payModal<?= $paket->id ?>">
                🪙 Buy <?= $credits ?> Credits
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Multi-Crypto Payment Modal -->
      <div class="modal fade" id="payModal<?= $paket->id ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content" style="background:#161329; color:#fff; border-radius:14px;">
            <div class="modal-header border-0">
              <h5 class="modal-title">💰 Buy <?= $credits ?> Credits - $<?= (int)$paket->fiyat ?> USD</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form method="post">
                <input type="hidden" name="paket_id" value="<?= $paket->id ?>">
                
                <!-- Credit Info Box -->
                <div class="alert alert-success text-center mb-3">
                  <strong>🎯 You will receive: <?= $credits ?> Credits</strong><br>
                  <small>Exchange Rate: 1 USD = 1 Credit</small>
                </div>

                <div class="mb-3">
                  <label class="form-label text-light">Select Currency</label>
                  <select name="currency" id="currencySelect<?= $paket->id ?>" class="form-select bg-dark text-light">
                    <option value="USDT" selected>USDT (TRC20) - $<?= (int)$paket->fiyat ?></option>
                    <option value="BTC">BTC (Bitcoin) - $<?= (int)$paket->fiyat ?></option>
                    <option value="ETH">ETH (Ethereum) - $<?= (int)$paket->fiyat ?></option>
                    <option value="SOL">SOL (Solana) - $<?= (int)$paket->fiyat ?></option>
                  </select>
                </div>

                <!-- Wallet Addresses -->
                <div class="alert alert-dark text-center" id="walletBox<?= $paket->id ?>" style="font-family:monospace;">
                  Send $<?= (int)$paket->fiyat ?> to:<br>
                  <strong id="walletAddr<?= $paket->id ?>">TW8A8hjxKXx95qgZQ8wQeZuXJf76Aa23Tx</strong>
                </div>

                <div class="mb-3">
                  <label class="form-label text-light">Transaction ID / Tx Hash</label>
                  <input type="text" name="txn_id" class="form-control bg-dark text-light" placeholder="Enter TxID or Hash" required>
                </div>
                
                <button type="submit" class="btn btn-success w-100">
                  🎯 Buy <?= $credits ?> Credits
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <script>
      // dynamic wallet address changer
      const currencyMap<?= $paket->id ?> = {
        'USDT': 'TW8A8hjxKXx95qgZQ8wQeZuXJf76Aa23Tx',
        'BTC': 'bc1qmybtcaddresstest1234567xyz',
        'ETH': '0x8eA9DfAAc1b77bD8d23E55C7E6CcD16aE7C1B97f',
        'SOL': '7A4gx8tpsSFaN1vTtqsm3z7kLULoCkTrfE5u1k8YpDFZ'
      };
      const sel<?= $paket->id ?> = document.getElementById('currencySelect<?= $paket->id ?>');
      const addr<?= $paket->id ?> = document.getElementById('walletAddr<?= $paket->id ?>');
      sel<?= $paket->id ?>.addEventListener('change', ()=> {
        addr<?= $paket->id ?>.textContent = currencyMap<?= $paket->id ?>[sel<?= $paket->id ?>.value];
      });
      </script>

    <?php } } else { ?>
      <div class="col-12">
        <div class="alert alert-info text-center">⚠️ No packages available right now.</div>
      </div>
    <?php } ?>
  </div>

  <!-- User Payment History -->
  <div class="mt-5">
    <h3 class="text-light mb-3">🧾 Your Payment Requests</h3>
    <div class="card shadow-sm p-3">
      <table class="table table-dark table-striped align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Package</th>
            <th>Credits</th>
            <th>Currency</th>
            <th>Amount</th>
            <th>Txn ID</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $pays = $db->get_results("SELECT p.*, k.baslik, k.fiyat FROM k_payments p 
                                  LEFT JOIN k_paketler k ON k.id=p.paket_id 
                                  WHERE p.uid='{$user->id}' ORDER BY p.id DESC LIMIT 20");
        if($pays){ foreach($pays as $p){ 
            $credits = (int)$p->fiyat;
        ?>
          <tr>
            <td><?= $p->id ?></td>
            <td><?= htmlspecialchars($p->baslik ?? '-') ?></td>
            <td>
              <span class="badge bg-warning text-dark"><?= $credits ?> Credits</span>
            </td>
            <td><?= htmlspecialchars($p->currency) ?></td>
            <td>$<?= $p->amount ?> USD</td>
            <td><small><?= htmlspecialchars($p->txn_id) ?></small></td>
            <td>
              <?php if($p->status==='approved'){ ?>
                <span class="badge bg-success">Approved</span>
              <?php } elseif($p->status==='rejected'){ ?>
                <span class="badge bg-danger">Rejected</span>
              <?php } else { ?>
                <span class="badge bg-warning text-dark">Pending</span>
              <?php } ?>
            </td>
            <td><small><?= $p->created_at ?></small></td>
          </tr>
        <?php } } else { ?>
          <tr><td colspan="8" class="text-center text-muted">No payment records found.</td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include "footer.php"; ?>
</body>
</html>