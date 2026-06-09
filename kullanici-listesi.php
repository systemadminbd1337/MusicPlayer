<?php include "header.php"; ?>
<h3 class="mb-4">User Management</h3>
<div class="card shadow">
  <div class="card-body">
    <a href="kullanici-ekle.php" class="btn btn-orange mb-3">+ Add New User</a>
    <table class="table table-hover table-bordered text-white">
      <thead>
        <tr>
          <th>#</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $users = $db->get_results("SELECT * FROM users ORDER BY id DESC");
        if($users){
          foreach($users as $u){ ?>
            <tr>
              <td><?=$u->id?></td>
              <td><?=$u->user?></td>
              <td><?=$u->email?></td>
              <td><?=($u->level==2?'Admin':'User')?></td>
              <td>
                <a href="kullanici-duzenle.php?id=<?=$u->id?>" class="btn btn-sm btn-warning">Edit</a>
                <a href="kullanici-sil.php?id=<?=$u->id?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete user?')">Delete</a>
              </td>
            </tr>
        <?php } } else { ?>
          <tr><td colspan="5" class="text-center">No users found</td></tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>
<?php include "footer.php"; ?>
