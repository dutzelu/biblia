<?php
/*************************************************************************
  Fişier: program-zilnic.php
  Framework: Bootstrap 5
  DOAR Biblia Sinodală (vbor_)
  — remedieri: modalul de trimiteri nu mai este gol —
*************************************************************************/

// 0. Conexiune la baza de date
$conn = new mysqli('localhost', 'root', '', 'biblia');
if ($conn->connect_error) {
    die('Eroare BD: ' . $conn->connect_error);
}
$conn->set_charset('utf8');
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Parametru URL
$ziua = max(1, min(365, intval($_GET['ziua'] ?? 1)));

// 2. Tabele
$tblBib = 'vbor_biblia';
$tblStr = 'vbor_structura';
$tblTit = 'vbor_titluri';
$tblXrf = 'vbor_trimiteri';

// ---------------------------------------------------------------------
// 3. Helperi
// ---------------------------------------------------------------------
function normalizeDashes(string $text): string {
    return str_replace(['—', '–', '-'], '-', $text);
}

function curata_text(string $text): string {
    // elimină tag‑ul </br> şi simbolul ^
    return str_replace(['</br>', '^'], '', $text);
}

/**
 * Caută o carte după abreviere SAU după cod numeric (BibPosition).
 */
function findBook(mysqli $db, string $key, string $structTable): ?array {
    $key = trim($key, " .\t\r\n");
    // dacă primim doar cifre, considerăm că este BibPosition
    if (ctype_digit($key)) {
        $sql = "SELECT BibPosition AS pos, COALESCE(DisplayTitle, FullTitle) AS title
                  FROM {$structTable}
                 WHERE BibPosition = " . intval($key) . " LIMIT 1";
    } else {
        $esc = $db->real_escape_string(strtolower($key));
        $sql = "SELECT BibPosition AS pos, COALESCE(DisplayTitle, FullTitle) AS title
                  FROM {$structTable}
                 WHERE LOWER(REPLACE(Abbreviation,'.','')) = '{$esc}'
                    OR LOWER(REPLACE(Abbreviation,'.','')) LIKE '{$esc}%'
                 LIMIT 1";
    }
    $row = $db->query($sql)->fetch_assoc();
    return $row ?: null;
}

/**
 * Parsează un şir de referinţe. Suportă atât forma clasică „Ps 23:1‑6; Ioan 3”
 * cât şi forma numerică din tabela de trimiteri „19:23:1‑6; 43:3:16”.
 */
function parseRef(string $refText, ?string $defaultBook = null): array {
    // 1. Curăţare globală
    $refText = normalizeDashes($refText);
    $refText = str_replace(['{', '}', '(', ')'], '', $refText); // elimină delimitatorii din tabel
    $refText = str_replace([','], ';', $refText);                // virgulă -> punct şi virgulă
    $refText = trim($refText);
    if ($refText === '') return [];

    $parts = preg_split('/\s*;\s*/', $refText);
    $out   = [];
    $currentBook = $defaultBook;

    foreach ($parts as $part) {
        if ($part === '') continue;
        $book = $currentBook;
        $rest = $part;

        // a) formă numerică: 19:88:11‑12, 43:3:16 etc.
        if (preg_match('/^(\d+):(\d+):(\d+)(?:-(\d+))?$/', $part, $m)) {
            $out[] = [
                'book' => $m[1],
                'cs'   => (int)$m[2],
                'ce'   => (int)$m[2],
                'vs'   => (int)$m[3],
                've'   => isset($m[4]) ? (int)$m[4] : (int)$m[3],
            ];
            continue;
        }
        // b) formă numerică pe mai multe capitole: 19:88:11‑89:5
        if (preg_match('/^(\d+):(\d+):(\d+)-(\d+):(\d+)$/', $part, $m)) {
            $out[] = [
                'book' => $m[1],
                'cs'   => (int)$m[2],
                'ce'   => (int)$m[4],
                'vs'   => (int)$m[3],
                've'   => (int)$m[5],
            ];
            continue;
        }

        // c) formă text + capitol/verset (vechiul parser)
        if (preg_match('/^[\p{L}\d]+\s/u', $part)) {
            [$book, $rest] = preg_split('/\s+/u', $part, 2);
            $currentBook = $book;
        } else {
            $book = $currentBook;
        }
        if (!$book || !$rest) continue;

        switch (true) {
            case preg_match('/^(\d+):(\d+)-(\d+):(\d+)$/', $rest, $m):
                $out[] = ['book'=>$book,'cs'=>(int)$m[1],'ce'=>(int)$m[3],'vs'=>(int)$m[2],'ve'=>(int)$m[4]];
                break;
            case preg_match('/^(\d+)-(\d+):(\d+)$/', $rest, $m):
                $out[] = ['book'=>$book,'cs'=>(int)$m[1],'ce'=>(int)$m[2],'vs'=>null,'ve'=>(int)$m[3]];
                break;
            case preg_match('/^(\d+):(\d+)-(\d+)$/', $rest, $m):
                $out[] = ['book'=>$book,'cs'=>(int)$m[1],'ce'=>(int)$m[1],'vs'=>(int)$m[2],'ve'=>(int)$m[3]];
                break;
            case preg_match('/^(\d+):(\d+)$/', $rest, $m):
                $out[] = ['book'=>$book,'cs'=>(int)$m[1],'ce'=>(int)$m[1],'vs'=>(int)$m[2],'ve'=>(int)$m[2]];
                break;
            case preg_match('/^(\d+)-(\d+)$/', $rest, $m):
                $out[] = ['book'=>$book,'cs'=>(int)$m[1],'ce'=>(int)$m[2],'vs'=>null,'ve'=>null];
                break;
            case ctype_digit($rest):
                $c = (int)$rest;
                $out[] = ['book'=>$book,'cs'=>$c,'ce'=>$c,'vs'=>null,'ve'=>null];
                break;
        }
    }
    return $out;
}

// ---------------------------------------------------------------------
// 4. Extrage textul biblic (+ trimiteri)
// ---------------------------------------------------------------------
function getBibleText(
    mysqli $db,
    array $refs,
    string $bibTable,
    string $structTable,
    string $titluriTable,
    string $xrfTable,
    bool   $withXrefs = true
): array {
    $html = [];
    foreach ($refs as $r) {
        $bk = findBook($db, (string)$r['book'], $structTable);
        if (!$bk) {
            $html[] = '<p class="text-danger">Cartea <strong>' . htmlspecialchars($r['book']) . '</strong> nu există.</p>';
            continue;
        }
        $pos     = (int)$bk['pos'];
        $bkTitle = htmlspecialchars($bk['title']);

        for ($cap = $r['cs']; $cap <= $r['ce']; $cap++) {
            // titlu de capitol
            $titRow   = $db->query("SELECT Title FROM {$titluriTable} WHERE Book={$pos} AND Chapter={$cap} AND Verse=0")
                           ->fetch_assoc();
            $capTitle = $titRow['Title'] ?? '';

            // range‑uri versete
            if ($r['cs'] === $r['ce']) {                // un singur capitol
                $vsStart = $r['vs'] ?? 1;
                $vsEnd   = $r['ve'] ?? 9999;
            } elseif ($cap === $r['cs']) {             // primul capitol din range
                $vsStart = $r['vs'] ?? 1;
                $vsEnd   = 9999;
            } elseif ($cap === $r['ce']) {             // ultimul capitol
                $vsStart = 1;
                $vsEnd   = $r['ve'] ?? 9999;
            } else {                                   // capitol intermediar
                $vsStart = 1;
                $vsEnd   = 9999;
            }

            // antet capitol
            $html[] = '<h6 class="fw-bold mt-3 mb-1">' . $bkTitle . ' ' . $cap . '</h6>';
            if ($capTitle) {
                $html[] = '<p class="fst-italic mb-1">' . htmlspecialchars(curata_text($capTitle)) . '</p>';            }

            // versete propriu‑zise
            $versRes = $db->query("SELECT Verse, Scripture
                                      FROM {$bibTable}
                                     WHERE Book={$pos} AND Chapter={$cap} AND Verse BETWEEN {$vsStart} AND {$vsEnd}
                                     ORDER BY Verse");
            if ($versRes->num_rows === 0) {
                $html[] = '<p class="text-warning">Versetele ' . $vsStart . '-' . $vsEnd . ' lipsesc din BD.</p>';
            }

            while ($v = $versRes->fetch_assoc()) {
                $vNr  = (int)$v['Verse'];
                $vTxt = htmlspecialchars(curata_text($v['Scripture']));

                $xrefStr = '';
                if ($withXrefs) {
                    $xr = $db->query("SELECT CrossReference FROM {$xrfTable} WHERE Book={$pos} AND Chapter={$cap} AND Verse={$vNr} LIMIT 1")
                              ->fetch_assoc();
                    if ($xr && trim($xr['CrossReference']) !== '') {
                        $xrefStr = trim($xr['CrossReference']);
                    }
                }

                if ($xrefStr) {
                    $modalId   = "xrf{$pos}_{$cap}_{$vNr}";
                    $xrefLines = getBibleText($db, parseRef($xrefStr), $bibTable, $structTable, $titluriTable, $xrfTable, false);
                    $modalBody = implode("\n", $xrefLines);

                    // verset + link la modal
                    $html[] = '<p class="mb-1"><sup>' . $vNr . '</sup> ' . $vTxt . ' '
                            . '<a href="#' . $modalId . '" data-bs-toggle="modal" data-bs-target="#' . $modalId . '"><sup>†</sup></a>'
                            . '</p>';

                    // modal propriu‑zis
                    $html[] = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true">'
                            .   '<div class="modal-dialog modal-dialog-scrollable modal-lg">'
                            .     '<div class="modal-content">'
                            .       '<div class="modal-header">'
                            .         '<h5 class="modal-title">Trimiteri – ' . $bkTitle . ' ' . $cap . ':' . $vNr . '</h5>'
                            .         '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>'
                            .       '</div>'
                            .       '<div class="modal-body">' . $modalBody . '</div>'
                            .     '</div>'
                            .   '</div>'
                            . '</div>';
                } else {
                    $html[] = '<p class="mb-1"><sup>' . $vNr . '</sup> ' . $vTxt . '</p>';
                }
            }
        }
    }
    return $html;
}

// ---------------------------------------------------------------------
// 5. Helper pentru "Citeşte mai departe" (primele 5 versete)
// ---------------------------------------------------------------------
function sect(string $titlu, array $linii, string $id): string {
    $preview = [];
    $cntVers = 0;
    foreach ($linii as $l) {
        $preview[] = $l;
        if (strpos($l, '<p class="mb-1"><sup>') === 0) $cntVers++;
        if ($cntVers >= 5) break;
    }
    $rest = array_slice($linii, count($preview));
    ob_start(); ?>
    <h4 class="mt-4"><?php echo $titlu; ?></h4>
    <?php echo implode("\n", $preview); ?>
    <?php if ($rest): ?>
      <div class="collapse" id="<?php echo $id; ?>">
        <?php echo implode("\n", $rest); ?>
      </div>
      <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="collapse" data-bs-target="#<?php echo $id; ?>">
        Citeşte mai departe
      </button>
    <?php endif; ?>
    <?php return ob_get_clean();
}

// ---------------------------------------------------------------------
// 6. Programul zilei + citatul
// ---------------------------------------------------------------------
$program = $conn->query("SELECT * FROM program_anual WHERE Ziua={$ziua}")->fetch_assoc();
if (!$program) die('Ziua inexistentă');
$citat = $conn->query("SELECT Citate, Autor FROM citate WHERE id={$ziua}")->fetch_assoc();

// ---------------------------------------------------------------------
// 7. Obţinem textele
// ---------------------------------------------------------------------
$vt = getBibleText($conn, parseRef($program['Vechiul_Testament']),   $tblBib, $tblStr, $tblTit, $tblXrf);
$ps = getBibleText($conn, parseRef($program['Psalmi'], 'Ps'),        $tblBib, $tblStr, $tblTit, $tblXrf);
$pr = getBibleText($conn, parseRef($program['Pilde'], 'Pild'),       $tblBib, $tblStr, $tblTit, $tblXrf);
$nt = getBibleText($conn, parseRef($program['Noul_Testament']),      $tblBib, $tblStr, $tblTit, $tblXrf);

// ---------------------------------------------------------------------
// 8. HTML
// ---------------------------------------------------------------------
?><!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Ziua <?php echo $ziua; ?> – Lecturi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">

  <!-- Navigaţie -->
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <?php if ($ziua > 1): ?>
      <a class="btn btn-outline-primary" href="?ziua=<?php echo $ziua-1; ?>">← Ziua <?php echo $ziua-1; ?></a>
    <?php else: ?><div></div><?php endif; ?>

    <form class="d-flex gap-2" method="get">
      <select name="ziua" class="form-select" onchange="this.form.submit()">
        <?php for ($i = 1; $i <= 365; $i++): ?>
          <option value="<?php echo $i; ?>" <?php if ($i === $ziua) echo 'selected'; ?>>Ziua <?php echo $i; ?></option>
        <?php endfor; ?>
      </select>
    </form>

    <?php if ($ziua < 365): ?>
      <a class="btn btn-outline-primary" href="?ziua=<?php echo $ziua+1; ?>">Ziua <?php echo $ziua+1; ?> →</a>
    <?php else: ?><div></div><?php endif; ?>
  </div>

  <!-- Titlu + citat -->
  <h3 class="mb-1">Ziua <?php echo $ziua; ?></h3>
  <p class="mb-3">
    Texte:
    <?php echo htmlspecialchars($program['Vechiul_Testament']); ?>,
    <?php echo htmlspecialchars($program['Noul_Testament']); ?>,
    Psalmi <?php echo htmlspecialchars($program['Psalmi']); ?>,
    Pilde <?php echo htmlspecialchars($program['Pilde']); ?>
  </p>

  <?php if ($citat): ?>
    <blockquote class="blockquote mb-4">
      <p class="mb-1"><?php echo htmlspecialchars(curata_text($citat['Citate'])); ?></p>
      <?php if (!empty($citat['Autor'])): ?>
        <footer class="blockquote-footer"><?php echo htmlspecialchars($citat['Autor']); ?></footer>
      <?php endif; ?>
    </blockquote>
  <?php endif; ?>

  <!-- Conţinut -->
  <?php echo sect('Vechiul Testament', $vt, 'vtCollapse'); ?>
  <?php echo sect('Psalmi',            $ps, 'psCollapse'); ?>
  <?php echo sect('Pilde',             $pr, 'prCollapse'); ?>
  <?php echo sect('Noul Testament',    $nt, 'ntCollapse'); ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
