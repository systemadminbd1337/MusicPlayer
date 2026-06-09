<?php
ob_start(); // Start output buffering to prevent headers issue
include "header.php";
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session for CSRF protection if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Debug: Log user object (kept for diagnostics)
error_log('User object: ' . print_r($user, true));

// Keep sanitized user id for any future use
$uid = isset($user->id) ? (int)$user->id : 0;

// We intentionally removed ticket CRUD and DB fetch logic per request.
// All support is now handled via direct Telegram contact.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>🛰️ Direct Support - HackLink Panel</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #050510;
    --panel: #0a0920;
    --neon1: #00e6ff;
    --neon2: #00ff9d;
    --accent: #facc15;
    --danger: #ff4d6d;
}
html,body{height:100%}
body {
    min-height:100%;
    background: radial-gradient(900px 300px at 10% 10%, rgba(0,230,255,.03), transparent 50%),
                radial-gradient(700px 300px at 90% 90%, rgba(0,255,157,.02), transparent 50%), var(--bg);
    color: #e7edf8;
    font-family: 'Share Tech Mono', monospace;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
}

/* Navbar like small top bar */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    padding:10px 16px;
    border-radius:10px;
    background: linear-gradient(90deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.03);
    margin-bottom:18px;
    box-shadow: 0 6px 30px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,0.02);
}
.h-title {
    font-family: 'Orbitron', sans-serif;
    color: var(--neon1);
    font-size: 20px;
    letter-spacing: 0.6px;
}

/* Main card */
.cardx {
    background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.008));
    border: 1px solid rgba(0,255,157,.08);
    border-radius: 14px;
    box-shadow: 0 0 36px rgba(0,255,157,.04), 0 8px 40px rgba(0,0,0,.7);
    padding: 28px;
    margin-bottom: 20px;
}

/* Neon message area */
.support-msg {
    border-radius: 12px;
    padding:22px;
    background: linear-gradient(135deg, rgba(0,14,23,0.6), rgba(3,9,20,0.4));
    border: 1px solid rgba(0,230,255,.06);
    box-shadow: 0 8px 30px rgba(0,0,0,.6), 0 0 18px rgba(0,230,255,.03) inset;
    margin-bottom:18px;
}
.support-msg h2 {
    color: var(--neon1);
    font-family: 'Orbitron', sans-serif;
    font-size: 22px;
    margin-bottom:8px;
}
.support-msg p {
    color: #bfdff7;
    margin-bottom:0;
    line-height:1.45;
}

/* Mega neon telegram button */
.telegram-btn {
    display:inline-flex;
    align-items:center;
    gap:12px;
    padding:14px 20px;
    border-radius:14px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.6px;
    cursor:pointer;
    border: none;
    background: linear-gradient(90deg, rgba(0,255,157,0.14), rgba(0,230,255,0.12));
    color: #001219;
    position:relative;
    overflow:visible;
    transition: transform .18s ease, box-shadow .18s ease;
    box-shadow: 0 6px 30px rgba(0,255,157,.06);
}

/* Glow layers */
.telegram-btn::before,
.telegram-btn::after{
    content:"";
    position:absolute;
    inset:0;
    border-radius:14px;
    z-index:-2;
}
.telegram-btn::before{
    background: radial-gradient(120px 40px at 30% 20%, rgba(0,230,255,.28), transparent 30%),
                radial-gradient(120px 40px at 80% 80%, rgba(0,255,157,.18), transparent 30%);
    filter: blur(18px);
    opacity:.9;
    transform: translateY(2px) scale(.98);
}
.telegram-btn::after{
    background: linear-gradient(90deg, rgba(0,255,157,.28), rgba(0,230,255,.36));
    filter: blur(28px);
    opacity:.65;
    transform: translateY(8px) scale(.98);
}

/* hover/pulse */
.telegram-btn:hover{
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 14px 60px rgba(0,255,157,.18), 0 30px 80px rgba(0,230,255,.06);
}
.telegram-btn:active{ transform: translateY(-2px) scale(1.01); }

@keyframes neonPulse {
    0% { box-shadow: 0 6px 30px rgba(0,255,157,.06), 0 0 0 rgba(0,255,157,0); }
    50%{ box-shadow: 0 18px 60px rgba(0,255,157,.12), 0 0 120px rgba(0,230,255,.04); }
    100%{ box-shadow: 0 6px 30px rgba(0,255,157,.06), 0 0 0 rgba(0,255,157,0); }
}
.telegram-btn.pulse{
    animation: neonPulse 2.6s infinite ease-in-out;
}

/* small telegram icon */
.tg-icon {
    width:22px;
    height:22px;
    display:inline-block;
    background:transparent;
}

/* footer */
footer {
    color: #9ab1d1;
    text-align: center;
    margin-top: 40px;
    padding: 15px 0;
    font-size: 13px;
}

/* responsive */
@media (max-width:576px){
    .telegram-btn { width:100%; justify-content:center; padding:12px 16px; }
    .cardx { padding:16px; }
}
</style>
</head>
<body>
<div class="container my-4" style="max-width:980px;">
    <div class="topbar">
        <div class="h-title">⚡ HackLink Panel — Direct Support</div>
        <div style="color:#9ab1d1;font-size:13px">Logged in: <?= htmlspecialchars($user->username ?? 'Guest') ?></div>
    </div>

    <div class="cardx">
        <div class="support-msg">
            <h2>📡 Need Immediate Help?</h2>
            <p>
                For direct, real-time support please contact our admin on Telegram.  
                Click the button below to open a chat with the support operator. Provide your <strong>username</strong> and a short description of the issue (e.g. page, error message, timestamp).
            </p>
        </div>

        <div class="d-flex gap-3 flex-column flex-sm-row align-items-start">
            <!-- Telegram button -->
            <a href="https://t.me/BL4CKHatSeo" target="_blank" rel="noopener noreferrer" class="telegram-btn pulse" title="Open Telegram chat with @BL4CKHatSeo">
                <!-- Inline SVG Telegram icon -->
                <svg class="tg-icon" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#00bcd4"/><stop offset="1" stop-color="#00e5a9"/></linearGradient></defs>
                    <circle cx="120" cy="120" r="120" fill="url(#g)"/>
                    <path d="M50 122c0 0 30-12 48-19 18-6 66-22 66-22s-4 38-6 56c-2 19-6 45-6 45s-33-15-49-23c-16-8-55-28-55-37z" fill="#fff" opacity="0.9"/>
                    <path d="M75 130c10-3 40-12 54-17 14-5 18-6 24-8 6-2 6 1 2 5-4 4-22 19-34 27-12 8-22 15-22 15s-11-1-24-22z" fill="#e6faff"/>
                </svg>
                <span style="font-size:14px;color:#001219;">Contact @BL4CKHatSeo</span>
            </a>

            <!-- Quick notes / hint box -->
            <div style="flex:1;min-width:220px;">
                <div style="padding:14px;border-radius:10px;background:rgba(255,255,255,0.01);border:1px solid rgba(255,255,255,0.02);">
                    <strong style="color:var(--neon1)">How to message</strong>
                    <ul style="margin:8px 0 0 18px;color:#bfe8ff">
                        <li>Send your <strong>panel username</strong> (e.g. <code><?= htmlspecialchars($user->username ?? 'your_username') ?></code>)</li>
                        <li>Describe the page / error + time (e.g. <em>index.php — SQL error — 20:14</em>)</li>
                        <li>Attach screenshot if available</li>
                    </ul>
                    <div style="margin-top:10px;font-size:13px;color:#9ab1d1">Support hours: 24/7 — typically replies within minutes on Telegram.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Optional small admin contact card -->
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="cardx" style="flex:1;min-width:260px;">
            <h5 style="color:var(--neon1);margin-bottom:8px">⚙️ Pro Tip</h5>
            <p style="color:#bfdff7;margin-bottom:0">
                For faster resolution, include the exact URL and any console/Apache error snippets.  
                If your issue is related to backups, include the exact DB name and time of the incident.
            </p>
        </div>
        <div class="cardx" style="flex:1;min-width:260px;">
            <h5 style="color:var(--neon1);margin-bottom:8px">🔒 Security Notice</h5>
            <p style="color:#bfdff7;margin-bottom:0">
                Never share passwords in Telegram. Share temporary logs, screenshots or error text only. Admin may request access tokens via a secure channel if needed.
            </p>
        </div>
    </div>

    <footer>© <?= date('Y') ?> HackLink Panel — 2025</footer>
</div>

<?php ob_end_flush(); // Flush output buffer ?>
</body>
</html>
