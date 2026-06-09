<?php
include __DIR__ . "/_bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    echo "Invalid CSRF token.";
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    /* ---------------- USERS ---------------- */
    case 'user_add':
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? 'user', ['user','admin']) ? $_POST['role'] : 'user';
        if ($username==='' || $email==='' || $password==='') exit("Missing fields.");
        $u = db_escape($username);
        $e = db_escape($email);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO k_users (username, email, password, role, kredi, is_banned, created_at, expiry_date)
                VALUES ('$u','$e','".db_escape($hash)."','$role',0,0,NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))";
        $db->query($sql);
        header("Location: users.php"); break;

    case 'user_ban':
    case 'user_unban':
        $uid = i($_POST['uid'] ?? 0);
        if ($uid>0) { $v = ($action==='user_ban')?1:0; $db->query("UPDATE k_users SET is_banned=$v WHERE id=$uid LIMIT 1"); }
        header("Location: users.php"); break;

    case 'user_delete':
        $uid = i($_POST['uid'] ?? 0);
        if ($uid>0) { $db->query("UPDATE k_users SET deleted_at=NOW() WHERE id=$uid LIMIT 1"); }
        header("Location: users.php"); break;


    /* ---------------- CREDIT ---------------- */
    case 'credit_add':
        $uid = i($_POST['uid'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $reason = db_escape(trim($_POST['reason'] ?? ''));
        if ($uid>0 && $amount!==0) {
            $db->query("UPDATE k_users SET kredi = kredi + ($amount), expiry_date = DATE_ADD(COALESCE(expiry_date, NOW()), INTERVAL 30 DAY) WHERE id=$uid AND deleted_at IS NULL LIMIT 1");
            $admin_id = (int)$user->id;
            $db->query("INSERT INTO k_credits_log (uid, delta, reason, admin_id, created_at)
                        VALUES ($uid, $amount, '$reason', $admin_id, NOW())");
        }
        header("Location: users.php"); break;


    /* ---------------- ANNOUNCEMENTS ---------------- */
    case 'ann_create':
        $title = db_escape(trim($_POST['title'] ?? ''));
        $author = db_escape(trim($_POST['author'] ?? ($user->username ?? 'admin')));
        $message = db_escape(trim($_POST['message'] ?? ''));
        if ($title==='' || $message==='') exit("Missing title/message");
        $db->query("INSERT INTO k_announcements (title, message, author, visible, created_at)
                    VALUES ('$title','$message','$author',1,NOW())");
        header("Location: announcements.php"); break;

    case 'ann_update':
        $id = i($_POST['id'] ?? 0);
        $title = db_escape(trim($_POST['title'] ?? ''));
        $author = db_escape(trim($_POST['author'] ?? ($user->username ?? 'admin')));
        $message = db_escape(trim($_POST['message'] ?? ''));
        if ($id>0 && $title!=='' && $message!=='') {
            $db->query("UPDATE k_announcements SET title='$title', message='$message', author='$author'
                        WHERE id=$id LIMIT 1");
        }
        header("Location: announcements.php"); break;

    case 'ann_toggle':
        $id = i($_POST['id'] ?? 0);
        if ($id>0) {
            $v = (int)$db->get_var("SELECT visible FROM k_announcements WHERE id=$id");
            $nv = $v ? 0 : 1;
            $db->query("UPDATE k_announcements SET visible=$nv WHERE id=$id LIMIT 1");
        }
        header("Location: announcements.php"); break;

    case 'ann_delete':
        $id = i($_POST['id'] ?? 0);
        if ($id>0) { $db->query("DELETE FROM k_announcements WHERE id=$id LIMIT 1"); }
        header("Location: announcements.php"); break;


    /* ---------------- BROKEN LINKS ---------------- */
    case 'broken_add':
        $domain = db_escape(trim($_POST['domain'] ?? ''));
        $url    = db_escape(trim($_POST['url'] ?? ''));
        $reason = db_escape(trim($_POST['reason'] ?? ''));
        if ($domain !== '' && $url !== '') {
            $db->query("INSERT INTO k_broken_links (domain, url, reason, status, checked_at, reported_by)
                        VALUES ('$domain', '$url', '$reason', 'broken', NOW(), '".db_escape($user->username ?? 'admin')."')");
        }
        header("Location: broken_links.php"); break;

    case 'broken_move_defunct':
        $id = i($_POST['id'] ?? 0);
        if ($id>0) {
            $db->query("UPDATE k_broken_links SET status='defunct', checked_at=NOW() WHERE id=$id LIMIT 1");
        }
        header("Location: broken_links.php"); break;

    case 'broken_delete':
        $id = i($_POST['id'] ?? 0);
        if ($id>0) {
            $db->query("DELETE FROM k_broken_links WHERE id=$id LIMIT 1");
        }
        header("Location: broken_links.php"); break;



    /* =======================================================
       🧠 CREDIT EXPIRY + RENEW / REFUND SYSTEM
       ======================================================= */

    case 'renew_access':
        $uid = i($_POST['uid'] ?? 0);
        if ($uid > 0) {
            $db->query("UPDATE k_users SET expiry_date = DATE_ADD(COALESCE(expiry_date, NOW()), INTERVAL 30 DAY)
                        WHERE id=$uid");
            $db->query("INSERT INTO k_credits_log (uid, delta, reason, admin_id, created_at)
                        VALUES ($uid, 0, 'Renewed 30 days access', ".(int)$user->id.", NOW())");
        }
        header("Location: users.php"); break;

    case 'refund_user':
        $uid = i($_POST['uid'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $reason = db_escape(trim($_POST['reason'] ?? 'Refund from deleted site'));
        if ($uid>0 && $amount>0) {
            $db->query("UPDATE k_users SET kredi = kredi + $amount WHERE id=$uid LIMIT 1");
            $db->query("INSERT INTO k_refunds (uid, amount, reason, created_at)
                        VALUES ($uid, $amount, '$reason', NOW())");
            $db->query("INSERT INTO k_credits_log (uid, delta, reason, admin_id, created_at)
                        VALUES ($uid, $amount, 'Refund processed', ".(int)$user->id.", NOW())");
        }
        header("Location: refunds.php"); break;

    case 'auto_refund_deleted':
        $deleted_sites = $db->get_results("SELECT id, uid, price FROM k_orders WHERE site_status='deleted' AND refunded=0");
        if ($deleted_sites) {
            foreach ($deleted_sites as $s) {
                $db->query("UPDATE k_users SET kredi = kredi + {$s->price} WHERE id={$s->uid}");
                $db->query("UPDATE k_orders SET refunded=1 WHERE id={$s->id}");
                $db->query("INSERT INTO k_refunds (uid, amount, reason, created_at)
                            VALUES ({$s->uid}, {$s->price}, 'Auto refund (deleted site)', NOW())");
            }
        }
        header("Location: refunds.php"); break;


    /* =======================================================
       🔐 ADMIN LOGIN LOG MANUAL RECORD (OPTIONAL)
       ======================================================= */
    case 'log_admin_login':
        $aid = i($_POST['admin_id'] ?? 0);
        $name = db_escape($_POST['username'] ?? '');
        $ok = (int)($_POST['success'] ?? 0);
        $ip = db_escape($_SERVER['REMOTE_ADDR'] ?? '');
        $db->query("INSERT INTO k_admin_login_logs (admin_id, username, ip, success, created_at)
                    VALUES ($aid, '$name', '$ip', $ok, NOW())");
        echo "OK"; exit;



    /* ---------------- DEFAULT ---------------- */
    default:
        http_response_code(400);
        echo "Unknown action.";
}
?>
