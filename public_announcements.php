<?php
require_once "config.php";

$rows = [];
try {
  $rows = $db->get_results("
    SELECT id, title, message, author, created_at
    FROM k_announcements
    WHERE COALESCE(visible,1)=1
    ORDER BY created_at DESC
    LIMIT 50
  ");
} catch (Exception $e) {
  $rows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>📢 Public Announcements - HackLink</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --bg:#030611;
  --panel:#0b0d21;
  --neon-blue:#60a5fa;
  --neon-purple:#a855f7;
  --neon-yellow:#facc15;
  --text:#e6eef8;
}
body{
  background:radial-gradient(circle at 10% 10%,rgba(96,165,250,0.08),transparent 60%),
             radial-gradient(circle at 90% 90%,rgba(168,85,247,0.06),transparent 60%),
             var(--bg);
  color:var(--text);
  font-family:Inter,system-ui,Arial;
  min-height:100vh;
  margin:0;
  padding-bottom:40px;
}
.header{
  text-align:center;
  padding:40px 10px 20px;
}
.header h1{
  font-weight:800;
  font-size:2rem;
  color:var(--neon-yellow);
  text-shadow:0 0 20px rgba(250,204,21,0.4);
}
.header p{
  color:#9aa7be;
  font-size:15px;
}
.containerx{
  max-width:900px;
  margin:auto;
  padding:0 20px;
}
.cardx{
  background:rgba(255,255,255,0.03);
  border:1px solid rgba(255,255,255,0.06);
  border-radius:14px;
  padding:20px;
  margin-bottom:16px;
  transition:0.3s;
  box-shadow:0 8px 30px rgba(0,0,0,0.6);
}
.cardx:hover{
  transform:translateY(-4px);
  box-shadow:0 0 25px rgba(96,165,250,0.25);
}
.cardx .title{
  color:var(--neon-blue);
  font-weight:700;
  font-size:18px;
}
.cardx .meta small{
  color:#9aa7be;
}
.cardx .author{
  color:var(--neon-yellow);
  font-weight:600;
  font-size:13px;
}
.cardx .message{
  color:var(--text);
  font-size:15px;
  margin-top:6px;
  white-space:pre-line;
}
.back-btn{
  display:inline-block;
  margin-top:25px;
  background:linear-gradient(90deg,var(--neon-blue),var(--neon-purple));
  padding:10px 20px;
  border-radius:10px;
  color:#fff;
  text-decoration:none;
  font-weight:600;
  transition:0.3s;
}
.back-btn:hover{
  box-shadow:0 0 20px rgba(96,165,250,0.6);
  transform:translateY(-3px);
}
</style>
</head>
<body>
  <div class="header">
    <h1>📢 Public Announcements</h1>
    <p>Latest updates, system notices and admin messages</p>
  </div>

  <div class="containerx">
    <?php if(!empty($rows)){ foreach($rows as $r){ ?>
      <div class="cardx">
        <div class="d-flex justify-content-between align-items-center meta">
          <div class="title"><?= htmlspecialchars($r->title) ?></div>
          <small><?= htmlspecialchars($r->created_at) ?></small>
        </div>
        <div class="author">By <?= htmlspecialchars($r->author ?? 'Admin') ?></div>
        <div class="message"><?= nl2br(htmlspecialchars($r->message)) ?></div>
      </div>
    <?php } } else { ?>
      <div class="alert alert-warning text-center">⚠️ No announcements available.</div>
    <?php } ?>

    <div class="text-center">
      <a href="index.php" class="back-btn">⬅ Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
