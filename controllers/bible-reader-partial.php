<?php

/* ────────────────────────────────────────────────────────────────────
 * 1. Endpoint AJAX → returnează HTML cu textele versetelor dintr‑o trimitere
 * ──────────────────────────────────────────────────────────────────── */
if (isset($_GET['cr'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo renderCrossReferenceModal($conn, $_GET['cr']);
    exit;
}

/* ────────────────────────────────────────────────────────────────────
 * 2. Parametri de interogare pentru capitolul curent (fallback Gen 1)
 * ──────────────────────────────────────────────────────────────────── */
$book    = isset($_GET['book'])    ? max(1, (int)$_GET['book'])    : 1;
$chapter = isset($_GET['chapter']) ? max(1, (int)$_GET['chapter']) : 1;

/* ────────────────────────────────────────────────────────────────────
 * 3. Preluăm textul capitolului + CrossReference‑urile aferente
 * ──────────────────────────────────────────────────────────────────── */
$verses = [];
$stmt = $conn->prepare("SELECT Verse, Scripture FROM vbor_biblia WHERE Book=? AND Chapter=? ORDER BY Verse");
$stmt->bind_param('ii', $book, $chapter);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $verses[(int)$row['Verse']] = $row['Scripture'];
$stmt->close();


$xrefs = [];
$stmt = $conn->prepare("SELECT Verse, CrossReference FROM vbor_trimiteri WHERE Book=? AND Chapter=? AND CrossReference <> ''");
$stmt->bind_param('ii', $book, $chapter);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $xrefs[(int)$row['Verse']] = $row['CrossReference'];
$stmt->close();

/* ────────────────────────────────────────────────────────────────────
 * 4. HTML OUTPUT
 *     – sidebar cu cărți + capitole
 *     – conținut capitol curent
 * ──────────────────────────────────────────────────────────────────── */
$books = getBooks($conn);              // datele pt. bara laterală
$bookAbbr = htmlspecialchars(getBookAbbrev($conn, $book));


// Titlul capitolului curent (din vbor_titluri)
$stmt = $conn->prepare("SELECT Title FROM vbor_titluri WHERE Book=? AND Chapter=? LIMIT 1");
$stmt->bind_param('ii', $book, $chapter);
$stmt->execute();
$res = $stmt->get_result();
$chapterTitle = '';
if ($row = $res->fetch_assoc()) {
    $chapterTitle = $row['Title'];
}
$stmt->close();


function curata_text($text) {
    // Elimină atât </br> cât și ^
    $text = str_replace(['</br>', '^'], '', $text);
    return $text;
}

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