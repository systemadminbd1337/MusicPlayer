<?php include "header.php"; ?>
<h3 class="mb-4">Active Links</h3>
<div class="card shadow">
  <div class="card-body">
    <table class="table table-hover table-bordered text-white">
      <thead>
        <tr>
          <th>#</th>
          <th>URL</th>
          <th>Category</th>
          <th>Added By</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $links = $db->get_results("SELECT * FROM links WHERE status='1' ORDER BY id DESC");
        if($links){
          foreach($links as $l){ ?>
            <tr>
              <td><?=$l->id?></td>
              <td><a href="<?=$l->url?>" target="_blank" class="text-warning"><?=$l->url?></a></td>
              <td><?=$l->category?></td>
              <td><?=$l->uid?></td>
              <td><?=$l->created_at?></td>
            </tr>
        <?php } } else { ?>
          <tr><td colspan="5" class="text-center">No active links found</td></tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>
<?php include "footer.php"; ?>
