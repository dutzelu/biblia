<?php

include 'includes/conectaredb.php';  
include 'controllers/program-zilnic-partial.php';  
include 'includes/functions.php';
include 'includes/header.php';
?>


<div class="container m-3">
  <div class="row g-4">
      <aside class="col-md-3 d-none d-md-block p-4">
        <?php include "includes/sidebar.php";?>
      </aside>

      <main class="col-12 col-md-9 p-4">

      <h1>Citește toată biblia în 365 de zile</h1>

          <!-- Titlu + citat -->
          <p class="mb-3">
            <b>Texte:</b>
            <?php echo htmlspecialchars($program['Vechiul_Testament']); ?>,
            <?php echo htmlspecialchars($program['Noul_Testament']); ?>,
            Psalmi <?php echo htmlspecialchars($program['Psalmi']); ?>,
            Pilde <?php echo htmlspecialchars($program['Pilde']); ?>
          </p>

          <!-- Navigaţie -->
          <div class="d-flex align-items-center mb-3 flex-wrap">
            <?php if ($ziua > 1): ?>
              <a class="btn btn-outline-primary sageata-st" href="?ziua=<?php echo $ziua-1; ?>"><</a>
            <?php else: ?><div></div><?php endif; ?>

            <form class="d-flex gap-2" method="get">
              <select name="ziua" class="form-select" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 365; $i++): ?>
                  <option value="<?php echo $i; ?>" <?php if ($i === $ziua) echo 'selected'; ?>>Ziua <?php echo $i; ?></option>
                <?php endfor; ?>
              </select>
            </form>

            <?php if ($ziua < 365): ?>
              <a class="btn btn-outline-primary sageata-dr" href="?ziua=<?php echo $ziua+1; ?>">></a>
            <?php else: ?><div></div><?php endif; ?>
          </div>


          <?php if ($citat): ?>
            <blockquote class="blockquote mb-4">
              <p class="mb-1"><?php echo '"' . curata_text($citat['Citate']) . '" <span class="autor_citat">(' . $citat['Autor'] . ')'; ?></span></p>
     
            </blockquote>
          <?php endif; ?>

          <!-- Conţinut -->
          <?php echo sect('Vechiul Testament', $vt, 'vtCollapse'); ?>
          <?php echo sect('Psalmi',            $ps, 'psCollapse'); ?>
          <?php echo sect('Pilde',             $pr, 'prCollapse'); ?>
          <?php echo sect('Noul Testament',    $nt, 'ntCollapse'); ?>
      </main>
      </div>
</div>
 <?php include 'includes/footer.php';?>