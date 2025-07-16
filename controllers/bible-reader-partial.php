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
