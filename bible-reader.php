<?php

include 'includes/conectaredb.php';  
include 'controllers/bible-reader-partial.php';  
include 'includes/functions.php';
include 'includes/header.php';
?>


<div class="container m-3">

<div class="row g-4">
    <aside class="col-md-3 d-none d-md-block bg-light p-4">

           
        
        <?php foreach($books as $b): $isActive = ($b['id']===$book); ?>
            <div class="book-FullTitle<?= $isActive? ' open':''?>" data-book="<?=$b['id']?>">
                <?=htmlspecialchars($b['abbr'])?>
            </div>
            <div class="chapter-list" style="<?= $isActive? 'display:block':''?>">
                <?php for($c=1;$c<=$b['chapters'];$c++): ?>
                    <a href="?book=<?=$b['id']?>&chapter=<?=$c?>"<?=$isActive&&$c==$chapter?' ':''?>><?=$c?></a>
                <?php endfor; ?>
            </div>
        <?php endforeach; ?>
    </aside>

    <!-- CONȚINUT CAPITOL -->
    <main class="col-12 col-md-9 p-4">
        <?php 
            echo '<h1>' . $bookAbbr . '</h1>';
            if ($chapterTitle) {
                echo '<p class="fs-5">' . '<span class="text-danger">Cap. ' . $chapter . ':</span> ' . htmlspecialchars($chapterTitle) . '</p>';
            }
            
        ?>
    
        <?php foreach ($verses as $vNo=>$text): ?>
            <p class="verse" id="v<?=$vNo?>">
                <sup class="v-num"><?=$vNo?></sup>
                <?= curata_text($text) ?>
                <?php if(isset($xrefs[$vNo])): ?>
                    <sup class="xref" data-cr="<?=htmlspecialchars($xrefs[$vNo])?>">†</sup>
                <?php endif; ?>
            </p>
        <?php endforeach; ?>
    </main>

</div>
</div>

  <!-- MODAL TRIMITERI -->
  <div id="xrefModal">
      <div id="xrefBox">
          <span id="xrefClose" onclick="closeXref()">&times;</span>
          <div id="xrefContent">Se încarcă…</div>
      </div>
  </div>


 <?php include 'includes/footer.php';?>

 
 