<?php
session_start();

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    //setcookie('lang', $_GET['lang'], time() + (86400 * 30), "/"); // 30 nap sutike
}

// Nyelv beállítása (cookie -> session -> alapértelmezett: magyar)
$lang = $_SESSION['lang'] ?? (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'hu');

$translations = json_decode(file_get_contents(__DIR__ . '/../translations.json'), true);

// Ha a kiválasztott nyelv nincs benne a JSON-ben, állítsuk vissza magyarra
if (!isset($translations[$lang])) {
    $lang = 'hu';
}

$texts = $translations[$lang];
?>
