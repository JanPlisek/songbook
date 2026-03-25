<?php
// soubor: search-api.php (FINÁLNÍ VERZE PRO DATABÁZI)

header('Content-Type: application/json; charset=utf-8');

// Připojíme se k databázi
require_once 'includes/database.php';

// Získáme hledaný výraz z URL (?q=...)
$query = $_GET['q'] ?? '';
$results = [];

// Vyhledáváme pouze pokud má dotaz alespoň 2 znaky
if (mb_strlen($query) > 1) {
    // Připravíme si hledaný výraz pro SQL dotaz LIKE (s zástupnými znaky %)
    $search_term = '%' . $query . '%';

    // Připravíme si hlavní SQL dotaz
    // Hledá ve 3 sloupcích: název písně, jméno interpreta a text písně
    $sql = "SELECT 
                s.id, 
                s.title,
                GROUP_CONCAT(p.name SEPARATOR ', ') as performers
            FROM 
                zp_songs s
            LEFT JOIN 
                zp_song_performers sp ON s.id = sp.song_id
            LEFT JOIN 
                zp_performers p ON sp.performer_id = p.id
            WHERE
                s.title LIKE ? 
                OR p.name LIKE ?
                OR CAST(s.lyrics AS CHAR) LIKE ?
            GROUP BY
                s.id
            LIMIT 7"; // Omezíme počet výsledků pro rychlý našeptávač

    try {
        $stmt = $pdo->prepare($sql);
        
        // Spustíme dotaz a předáme mu hledaný výraz pro všechna 3 místa
        $stmt->execute([$search_term, $search_term, $search_term]);
        
        $results = $stmt->fetchAll();

    } catch (PDOException $e) {
        // V případě chyby vrátíme chybu (pro ladění)
        http_response_code(500);
        $results = ['error' => 'Chyba databáze: ' . $e->getMessage()];
    }
}

// Vrátíme výsledky ve formátu JSON
echo json_encode($results);