<?php

function curata_text($text) {
    // Elimină atât </br> cât și ^
    $text = str_replace(['</br>', '^'], '', $text);
    return $text;
}
