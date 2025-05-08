<?php
session_start();

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    //setcookie('lang', $_GET['lang'], time() + (86400 * 30), "/"); // 30 nap sutike
}

$lang = $_SESSION['lang'] ?? (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'hu');

$translations = json_decode(file_get_contents(__DIR__ . '/../translations.json'), true);

if (!isset($translations[$lang])) {
    $lang = 'hu';
}

$texts = $translations[$lang];
?>
