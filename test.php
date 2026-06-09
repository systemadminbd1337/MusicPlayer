<?php
require_once __DIR__ . '/db.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $category = $_POST['category'] ?? 'Hacklinks';
        $author   = trim($_POST['author'] ?? 'Admin');
        $visible  = isset($_POST['visible']) ? 1 : 0;
        $order    = (int)($_POST['sort_order'] ?? 0);

        if ($question === '' || $answer === '') {
            $errors[] = "প্রশ্ন ও উত্তর পূরণ করতে হবে।";
        } else {
            $stmt = $pdo->prepare("INSERT INTO k_faq (question, answer, category, author, visible, sort_order) 
                                   VALUES (:q,:a,:c,:au,:v,:o)");
            $stmt->execute([
                ':q'=>$question, ':a'=>$answer, ':c'=>$category,
                ':au'=>$author, ':v'=>$visible, ':o'=>$order
            ]);
            $success = "নতুন FAQ যোগ হয়েছে।";
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $category = $_POST['category'] ?? 'Hacklinks';
        $author   = trim($_POST['author'] ?? 'Admin');
        $visible  = isset($_POST['visible']) ? 1 : 0;
        $order    = (int)($_POST['sort_order'] ?? 0);

        if ($id <= 0 || $question === '' || $answer === '') {
            $errors[] = "সঠিক ডাটা দিন।";
        } else {
            $stmt = $pdo->prepare("UPDATE k_faq 
                                   SET question=:q, answer=:a, category=:c, author=:au, visible=:v, sort_order=:o 
                                   WHERE id=:id");
            $stmt->execute([
                ':q'=>$question, ':a'=>$answer, ':c'=>$category, ':au'=>$author,
                ':v'=>$visible, ':o'=>$order, ':id'=>$id
            ]);
            $success = "FAQ আপডেট হয়েছে।";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM k_faq WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            $success = "FAQ মুছে ফেলা হয়েছে।";
        }
    }
}

$all = $pdo->query("SELECT * FROM k_faq ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8" />
<title>FAQ Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container my-4">
  <h2 class="mb-4">FAQ Admin Panel</h2>

  <?php if ($success): ?>
    <div class="alert alert-success"><?=h($success)?></div>
  <?php endif; ?>
  <?php if ($errors): foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?=h($err)?></div>
  <?php endforeach; endif; ?>

  <!-- Add New -->
  <div class="card mb-4">
    <div class="card-body bg-secondary">
      <h5 class="card-title">নতুন প্রশ্ন যোগ করুন</h5>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="mb-3">
          <label class="form-label">প্রশ্ন</label>
          <input class="form-control" name="question" required>
        </div>
        <div class="mb-3">
          <label class="form-label">উত্তর</label>
          <textarea class="form-control" name="answer" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">ক্যাটাগরি</label>
          <select name="category" class="form-control">
            <option>Hacklinks</option>
            <option>Account</option>
            <option>Payments</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Author</label>
          <input class="form-control" name="author" value="Admin">
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="visible" name="visible" checked>
          <label class="form-check-label" for="visible">Visible</label>
        </div>
        <div class="mb-3">
          <label class="form-label">Sort Order</label>
          <input class="form-control" type="number" name="sort_order" value="0">
        </div>
        <button class="btn btn-primary">Add FAQ</button>
      </form>
    </div>
  </div>

  <!-- List -->
  <h4>সব FAQ</h4>
  <table class="table table-dark table-striped">
    <thead>
      <tr>
        <th>ID</th><th>প্রশ্ন</th><th>ক্যাটাগরি</th><th>Author</th><th>Visible</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($all as $row): ?>
      <tr>
        <td><?=$row['id']?></td>
        <td><?=h($row['question'])?></td>
        <td><?=h($row['category'])?></td>
        <td><?=h($row['author'])?></td>
        <td><?=$row['visible'] ? '✔' : '✖'?></td>
        <td>
          <!-- Edit -->
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?=$row['id']?>">
            <input type="hidden" name="question" value="<?=h($row['question'])?>">
            <input type="hidden" name="answer" value="<?=h($row['answer'])?>">
            <input type="hidden" name="category" value="<?=h($row['category'])?>">
            <input type="hidden" name="author" value="<?=h($row['author'])?>">
            <input type="hidden" name="visible" value="<?=$row['visible']?>">
            <input type="hidden" name="sort_order" value="<?=$row['sort_order']?>">
            <button class="btn btn-sm btn-warning" formaction="faq-edit.php?id=<?=$row['id']?>">Edit</button>
          </form>
          <!-- Delete -->
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this FAQ?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$row['id']?>">
            <button class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a href="faq.php" class="btn btn-success">View Public FAQ</a>
</div>
</body>
</html>
