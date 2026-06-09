<?php
// admin/admin_create.php — FINAL FIXED VERSION
// Safe Admin Creator (no warnings, user_id fallback = 0)
// DELETE THIS FILE AFTER USING ONCE!

$root = dirname(__DIR__);
if (file_exists($root . '/config.php')) include_once $root . '/config.php';
elseif (file_exists($root . '/header.php')) include_once $root . '/header.php';
else die("config.php/header.php not found — place this file inside admin/ folder.");

if (!isset($db)) die("❌ \$db connection not found. Ensure config.php defines it.");

// Safe helper wrappers
function safe_var($sql,$def=null){global $db;try{$v=$db->get_var($sql);return $v??$def;}catch(Throwable$e){return $def;}}
function safe_row($sql){global $db;try{$r=$db->get_row($sql,ARRAY_A);return is_array($r)?$r:[];}catch(Throwable$e){return[];}}
function safe_q($sql){global $db;try{$db->query($sql);}catch(Throwable$e){}}
function esc($v){global $db;if(isset($db)&&method_exists($db,'escape'))return $db->escape($v);return addslashes($v);}

$msgErr=[];$msgOk='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $username=trim($_POST['username']??'');
  $email=trim($_POST['email']??'');
  $password=$_POST['password']??'';
  $confirm=trim($_POST['confirm']??'');
  $target=$_POST['target']??'k_admins';
  if($confirm!=='I_WILL_DELETE_THIS_FILE')$msgErr[]='Safety confirmation failed.';
  elseif($username==''||$email==''||$password=='')$msgErr[]='সব ফিল্ড পূরণ করুন।';
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$msgErr[]='ইমেইল ঠিক নয়।';
  else{
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $has_admins=(bool)safe_var("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='k_admins'",false);

    try{
      if($target==='k_admins' && $has_admins){
        $old=safe_row("SELECT id FROM k_admins WHERE email='".esc($email)."' OR username='".esc($username)."' LIMIT 1");
        if(!empty($old['id'])){
          safe_q("UPDATE k_admins SET password='".esc($hash)."', username='".esc($username)."', email='".esc($email)."' WHERE id=".(int)$old['id']);
          $msgOk="✅ Updated existing admin (id={$old['id']}) in k_admins.";
        }else{
          $uid=(int)safe_var("SELECT id FROM k_users WHERE email='".esc($email)."' LIMIT 1",0);
          if($uid<=0)$uid=0;
          safe_q("INSERT INTO k_admins (user_id,username,email,password,super_admin,permissions,created_at)
                  VALUES ({$uid},'".esc($username)."','".esc($email)."','".esc($hash)."',1,NULL,NOW())");
          $msgOk="✅ New admin inserted into k_admins (user_id={$uid}).";
        }
      }else{
        $usr=safe_row("SELECT id FROM k_users WHERE email='".esc($email)."' OR username='".esc($username)."' LIMIT 1");
        if(!empty($usr['id'])){
          safe_q("UPDATE k_users SET password='".esc($hash)."', role='admin', is_banned=0, deleted_at=NULL WHERE id=".(int)$usr['id']);
          $msgOk="✅ Promoted existing user (id={$usr['id']}) to admin in k_users.";
        }else{
          safe_q("INSERT INTO k_users (username,email,password,role,kredi,is_banned,created_at)
                  VALUES ('".esc($username)."','".esc($email)."','".esc($hash)."','admin',0,0,NOW())");
          $msgOk="✅ Created new admin user in k_users.";
        }
      }
    }catch(Throwable$e){$msgErr[]="DB Error: ".$e->getMessage();}
  }
}
?>
<!doctype html><html lang="bn"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Creator</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#030611;color:#e6eef8;font-family:Inter,system-ui;padding:30px;}
.card{background:rgba(255,255,255,0.03);border:1px solid rgba(96,165,250,0.08);}
</style></head><body>
<div class="container" style="max-width:720px">
  <div class="card p-4 mt-4 shadow-lg">
    <h3 class="mb-3 text-warning">⚙️ One-time Admin Creator</h3>
    <p class="small text-muted">এই ফাইল চালিয়ে নতুন অ্যাডমিন তৈরি করো। <strong>কাজ শেষে এই ফাইল ডিলিট করো।</strong></p>

    <?php if($msgErr): ?><div class="alert alert-danger"><?php foreach($msgErr as $e)echo htmlspecialchars($e).'<br>';?></div><?php endif;?>
    <?php if($msgOk): ?><div class="alert alert-success"><?=htmlspecialchars($msgOk)?></div><?php endif;?>

    <form method="post" class="row g-3">
      <div class="col-md-6"><label class="form-label">Username</label>
        <input name="username" class="form-control" value="<?=htmlspecialchars($_POST['username']??'admin')?>" required></div>
      <div class="col-md-6"><label class="form-label">Email</label>
        <input name="email" type="email" class="form-control" value="<?=htmlspecialchars($_POST['email']??'admin@localhost')?>" required></div>
      <div class="col-md-6"><label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Target Table</label>
        <select name="target" class="form-control">
          <option value="k_admins">k_admins (preferred)</option>
          <option value="k_users">k_users</option>
        </select></div>
      <div class="col-12"><label class="form-label">Safety Confirmation</label>
        <input name="confirm" class="form-control" placeholder="I_WILL_DELETE_THIS_FILE" required>
        <div class="form-text text-secondary">Type exactly: <code>I_WILL_DELETE_THIS_FILE</code></div></div>
      <div class="col-12">
        <button class="btn btn-success">Create Admin</button>
        <a href="index.php" class="btn btn-outline-light">Back</a>
      </div>
    </form>
  </div>
</div>
</body></html>
