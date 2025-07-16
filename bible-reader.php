<?php

include 'includes/conectaredb.php';  
include 'controllers/bible-reader-partial.php';  
include 'includes/functions.php';
include 'includes/header.php';
?>


<body>


  <div class="container-fluid m-3">

    <!-- Navbar mobil cu buton sandviș -->
    <nav class="navbar navbar-light bg-light d-md-none">
    <div class="container-fluid">
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        ☰ Meniu
      </button>
    </div>
  </nav>


    <div class="row g-4">
        <aside class="col-md-3 d-none d-md-block bg-light p-4">

            <a href="<?php echo BASE_URL;?>"><img class="mb-3" src="<?php echo BASE_URL;?>images/serile-biblice-OT.png" width="90%"></a>        
            
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

    <!-- MODAL TRIMITERİ -->
    <div id="xrefModal">
        <div id="xrefBox">
            <span id="xrefClose" onclick="closeXref()">&times;</span>
            <div id="xrefContent">Se încarcă…</div>
        </div>
    </div>

  <!-- Sidebar offcanvas pentru mobil -->
  <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">

    <div class="offcanvas-header">
      <h5 id="sidebarOffcanvasLabel">Meniu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
      <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="#">Link 1</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Link 2</a></li>
      </ul>
    </div>

  </div>

<script>
// ───────── Sidebar toggle capitole ─────────
document.querySelectorAll('.book-FullTitle').forEach(function(bt){
    bt.addEventListener('click', function(){
        this.classList.toggle('open');
        const ul = this.nextElementSibling;
        if(ul) ul.style.display = this.classList.contains('open') ? 'block' : 'none';
    });
});

// ───────── Modal pentru CrossReference ─────────
function closeXref(){document.getElementById('xrefModal').style.display='none';}

document.querySelectorAll('.xref').forEach(function(el){
    el.addEventListener('click', function(){
        const cr = this.dataset.cr;
        fetch('<?=basename(__FILE__)?>?cr='+encodeURIComponent(cr))
            .then(r=>r.text())
            .then(html=>{
                document.getElementById('xrefContent').innerHTML = html;
                document.getElementById('xrefModal').style.display='block';
            })
            .catch(()=>{
                document.getElementById('xrefContent').innerHTML='Eroare la încărcare';
                document.getElementById('xrefModal').style.display='block';
            });
    });
});
</script>
</body>
</html>

 
<?php
/* ───────────────────────────────────────────── FUNCȚII AUXILIARE */

/**
 * Lista tuturor cărților + nr. de capitole (o singură interogare, cached).
 * return [ [id, abbr, FullTitle, chapters], ... ]
 */
function getBooks(mysqli $conn): array {
    static $books = null;
    if ($books !== null) return $books;
    $books = [];
    $res = $conn->query('SELECT BibPosition, ListTitle, FullTitle, Chapters FROM vbor_structura ORDER BY ID');
    while ($row = $res->fetch_assoc()) {
        $books[] = [
            'id'       => (int)$row['BibPosition'],
            'abbr'     => $row['ListTitle'],
            'FullTitle'    => $row['FullTitle'] ?: $row['ListTitle'],
            'chapters' => (int)$row['Chapters']
        ];
    }
    return $books;
}

/** Abrevierea cărții – folosită în titluri */
function getBookAbbrev(mysqli $conn, int $bookId): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        $res = $conn->query('SELECT BibPosition, ListTitle FROM vbor_structura');
        while ($row=$res->fetch_assoc()) $map[(int)$row['BibPosition']] = $row['ListTitle'];
    }
    return $map[$bookId] ?? (string)$bookId;
}

/** Randează lista versetelor pentru un CrossReference raw */
function renderCrossReferenceModal(mysqli $conn, string $raw): string {
    $targets = parseCrossReference($raw);
    if (!$targets) return '<p><em>Nu există versete asociate.</em></p>';

    $clauses = []; $params = []; $types = '';
    foreach($targets as $t){
        $clauses[] = '(Book=? AND Chapter=? AND Verse=?)';
        $params[] = $t['book'];
        $params[] = $t['chapter'];
        $params[] = $t['verse'];
        $types .= 'iii';
    }
    $sql = 'SELECT Book, Chapter, Verse, Scripture FROM vbor_biblia WHERE ' . implode(' OR ', $clauses) . ' ORDER BY Book, Chapter, Verse';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = '';
    while ($row = $res->fetch_assoc()) {
        $bookId = (int)$row['Book'];
        $cap    = (int)$row['Chapter'];
        $ver    = (int)$row['Verse'];
        $text   = curata_text($row['Scripture']);
        $abbr   = htmlspecialchars(getBookAbbrev($conn, $bookId));
        $out .= "<div style=\"margin-bottom:1.2em\">";
        $out .= "<div><strong>$abbr</strong>, <a href=\"?book=$bookId&amp;chapter=$cap\">Cap.$cap</a></div>";
        $out .= "<div><sup>$ver</sup> $text</div>";
        $out .= "</div>";
    }

    return $out;
}


/** Parsează un CrossReference raw; returnează triplete book/chapter/verse */
function parseCrossReference(string $raw): array {
    $list = [];
    foreach (preg_split('/[;)]/', $raw) as $tok) {
        if (!preg_match('/\{(\d+):(\d+):([\d,\-]+)\}/', $tok, $m)) continue;
        [$_, $b, $c, $v] = $m;
        foreach (explode(',', $v) as $chunk) {
            if (strpos($chunk,'-')!==false) {
                [$vs,$ve] = array_map('intval', explode('-', $chunk));
                for($i=$vs;$i<=$ve;$i++) $list[]=['book'=>(int)$b,'chapter'=>(int)$c,'verse'=>$i];
            } else {
                $list[]=['book'=>(int)$b,'chapter'=>(int)$c,'verse'=>(int)$chunk];
            }
        }
    }
    return $list;
}
?>
