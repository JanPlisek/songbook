<?php
// soubor: random.php

$master_list_path = 'songs/_masterlist.json';

// Zkontrolujeme, zda existuje a je čitelný soubor se seznamem písní
if (!is_readable($master_list_path)) {
    // Pokud ne, přesměrujeme na hlavní stránku s chybovou hláškou (volitelné)
    header('Location: index.php?error=masterlist_missing');
    exit;
}

$master_list_content = file_get_contents($master_list_path);
$song_list = json_decode($master_list_content, true);

// Zkontrolujeme, zda se podařilo JSON dekódovat a zda seznam není prázdný
if (empty($song_list) || json_last_error() !== JSON_ERROR_NONE) {
    header('Location: index.php?error=masterlist_invalid');
    exit;
}

// Vybereme náhodný index z pole písní
$random_index = array_rand($song_list);

// Získáme data náhodně vybrané písně
$random_song = $song_list[$random_index];

// Získáme ID písně
$random_song_id = $random_song['id'];

// Přesměrujeme uživatele na stránku s náhodně vybranou písní
header('Location: song.php?id=' . urlencode($random_song_id));
exit; // Důležité je ukončit běh skriptu po přesměrování
?>