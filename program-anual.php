<?php
  include 'includes/conectaredb.php';  
  include 'includes/functions.php';
  include 'includes/header.php';
?>



<div class="container m-3">
<div class="row g-4">
  <aside class="col-md-3 d-none d-md-block p-3">
      <?php include "includes/sidebar.php";?>
  </aside>

  <main class="col-12 col-md-9 p-4">
  <h1 class="mb-4">Program anual de citire a Bibliei</h1>
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Ziua</th>
          <th>Vechiul Testament</th>
          <th>Psalmi</th>
          <th>Pilde</th>
          <th>Noul Testament</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = $conn->query("SELECT * FROM program_anual ORDER BY Ziua ASC");
        while ($row = $res->fetch_assoc()):
        ?>
          <tr class="clickable-row" onclick="window.location='program-zilnic.php?ziua=<?= $row['Ziua'] ?>'">
            <td><?= $row['Ziua'] ?></td>
            <td><?= $row['Vechiul_Testament'] ?></td>
            <td><?= $row['Psalmi'] ?></td>
            <td><?= $row['Pilde'] ?></td>
            <td><?= $row['Noul_Testament'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </main>
</div>
        </div>

<?php include 'includes/footer.php';?>