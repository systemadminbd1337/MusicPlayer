<?php include "header.php"; ?>
<h3 class="mb-4">My Backlinks</h3>
<div class="card shadow">
  <div class="card-body">
    <table class="table table-hover table-bordered text-white align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>URL</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $res = $db->get_results("SELECT * FROM backlinks WHERE uid='$user->id' ORDER BY id DESC");
        if($res){
          foreach($res as $row){ ?>
            <tr>
              <td><?=$row->id?></td>
              <td><a href="<?=$row->url?>" target="_blank" class="text-warning"><?=$row->url?></a></td>
              <td>
                <?php if($row->status=="active"){ ?>
                  <span class="badge bg-success">Active</span>
                <?php } else { ?>
                  <span class="badge bg-danger">Inactive</span>
                <?php } ?>
              </td>
              <td><?=$row->created_at?></td>
              <td>
                <a href="link-goster.php?id=<?=$row->id?>" class="btn btn-sm btn-orange">View</a>
              </td>
            </tr>
        <?php } } else { ?>
          <tr><td colspan="5" class="text-center">No backlinks found</td></tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>
<?php include "footer.php"; ?>
