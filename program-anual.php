<?php
$conn = new mysqli("localhost", "root", "", "biblia");
$conn->set_charset("utf8");
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Program Anual Biblic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    tr.clickable-row { cursor: pointer; }
  </style>
</head>
<body class="container py-4">
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
  </div>
</body>
</html>
