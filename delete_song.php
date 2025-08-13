<?php
// soubor: delete_song.php
include_once 'includes/functions.php'; // Použijeme include_once pro jistotu

// Pokud uživatel není přihlášen, ukončíme skript s chybou.
if (!is_user_logged_in()) {
    // Pro API skripty vrátíme JSON chybu
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Přístup odepřen. Musíte být přihlášeni.']);
    } else {
        // Pro běžné stránky přesměrujeme na login
        header('Location: login.php');
    }
    exit;
}




// Povolíme, aby skript vracel odpověď ve formátu JSON
header('Content-Type: application/json');

// --- Bezpečnost a příjem dat ---
// Očekáváme, že požadavek přijde metodou POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Nesprávná metoda požadavku.']);
    exit;
}

// Získáme data z těla požadavku (očekáváme JSON)
$data = json_decode(file_get_contents('php://input'), true);
$songId = $data['id'] ?? null;

if (empty($songId)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Chybí ID písně.']);
    exit;
}

// Bezpečnostní pojistka: Zabráníme útokům typu "directory traversal"
// Ujistíme se, že ID neobsahuje tečky ani lomítka
if (strpos($songId, '.') !== false || strpos($songId, '/') !== false || strpos($songId, '\\') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Neplatný formát ID písně.']);
    exit;
}


// --- Logika mazání ---
$songsDir = 'songs/';
$songFilePath = $songsDir . $songId . '.json';
$masterListPath = $songsDir . '_masterlist.json';
$response = ['success' => true, 'message' => 'Píseň byla úspěšně smazána.'];

// 1. Smazání souboru písně (.json)
if (file_exists($songFilePath)) {
    if (!unlink($songFilePath)) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Nepodařilo se smazat soubor písně.']);
        exit;
    }
} else {
    // Pokud soubor neexistuje, nebereme to jako chybu, ale poznamenáme si to
    $response['message'] .= ' (Soubor písně již neexistoval.)';
}


// 2. Aktualizace _masterlist.json
if (file_exists($masterListPath)) {
    // Použijeme zamykání souboru pro bezpečný zápis
    $masterListContent = file_get_contents($masterListPath);
    $masterList = json_decode($masterListContent, true);

    if (is_array($masterList)) {
        // Vyfiltrujeme pole a odstraníme položku s daným ID
        $updatedList = array_filter($masterList, function($song) use ($songId) {
            return $song['id'] !== $songId;
        });

        // Přeindexujeme pole, aby bylo stále polem a ne objektem
        $updatedList = array_values($updatedList);

        // Zapíšeme aktualizovaný obsah zpět do souboru
        $writeResult = file_put_contents(
            $masterListPath,
            json_encode($updatedList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
        
        if ($writeResult === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Nepodařilo se aktualizovat masterlist.']);
            exit;
        }
    }
} else {
     $response['message'] .= ' (Masterlist nebyl nalezen pro aktualizaci.)';
}

// Pokud vše proběhlo v pořádku, odešleme úspěšnou odpověď
http_response_code(200);
echo json_encode($response);
?>