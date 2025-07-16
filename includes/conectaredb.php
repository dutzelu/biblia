<?php

$conn = new mysqli("localhost", "root", "", "biblia");
$conn->set_charset("utf8");


if ($conn->connect_error) {
    die("Conectare eșuată: " . $conn->connect_error);
}


// Definirea URL-ului de bază
define("BASE_URL", "http://localhost/biblia/");
define("ROOT_PATH", $_SERVER["DOCUMENT_ROOT"] . "/biblia");
