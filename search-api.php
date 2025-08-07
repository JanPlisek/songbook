<?php
// === FINÁLNÍ A DEFINITIVNÍ VERZE search-api.php ===

header('Content-Type: application/json; charset=utf-8');

// --- Načtení dat ---
$master_list_file = 'songs/_masterlist.json';
if (!file_exists($master_list_file)) {
    echo json_encode([]);
    exit();
}
$songs = json_decode(file_get_contents($master_list_file), true);
if (!is_array($songs)) { $songs = []; }

// --- Zpracování parametrů ---
$artist_filter = $_GET['artist'] ?? '';
$query = $_GET['q'] ?? '';
$results = [];

// --- Logika je nyní striktně oddělena ---

// 1. FILTROVÁNÍ PODLE INTERPRETA (pro modální okno)
if (!empty($artist_filter)) {
    foreach ($songs as $song) {
        $song_artists = $song['artist'] ?? [];
        $song_bands = $song['band'] ?? [];
        if (in_array($artist_filter, $song_artists) || in_array($artist_filter, $song_bands)) {
            $results[] = $song;
        }
    }
    
    // Seřadíme VŠECHNY nalezené výsledky
    if (!empty($results)) {
        $collator = new Collator('cs_CZ');
        usort($results, function($a, $b) use ($collator) {
            return $collator->compare($a['title'], $b['title']);
        });
    }

    echo json_encode($results);
    exit();
}

// 2. VYHLEDÁVÁNÍ V NAŠEPTÁVAČI
if (strlen($query) > 1) { 
    foreach ($songs as $song) {
        $song_artists = $song['artist'] ?? [];
        $song_bands = $song['band'] ?? [];
        $all_performers = array_merge($song_artists, $song_bands);
        if (stripos($song['title'], $query) !== false || stripos(implode(', ', $all_performers), $query) !== false) {
            $results[] = $song;
        }
        // Aplikujeme limit POUZE zde
        if (count($results) >= 7) {
            break;
        }
    }

    echo json_encode($results);
    exit();
}

// Pokud žádná podmínka nebyla splněna, vrátíme prázdné pole
echo json_encode([]);