<?php
// soubor: random.php (NOVÁ VERZE PRO DATABÁZI)

// Připojíme se k databázi
require_once 'includes/database.php';

try {
    // SQL dotaz, který vybere ID jedné náhodné písně z tabulky
    $sql = "SELECT id FROM zp_songs ORDER BY RAND() LIMIT 1";
    
    $stmt = $pdo->query($sql);
    
    // Načteme ID písně
    $song_id = $stmt->fetchColumn();

    if ($song_id) {
        // Pokud jsme našli ID, přesměrujeme uživatele na stránku dané písně
        header('Location: song.php?id=' . $song_id);
        exit;
    } else {
        // Pokud je databáze prázdná, přesměrujeme na úvodní stránku
        header('Location: index.php');
        exit;
    }

} catch (PDOException $e) {
    // V případě chyby zobrazíme chybovou hlášku
    die("Chyba při výběru náhodné písně: " . $e->getMessage());
}