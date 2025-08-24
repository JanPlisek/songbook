<?php
// soubor: delete_song.php (FINÁLNÍ VERZE PRO DATABÁZI)

// Tento soubor funguje jako API, takže bude vracet odpověď ve formátu JSON
header('Content-Type: application/json');

// Spustíme session pro kontrolu přihlášení a připojíme databázi
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Výchozí odpověď
$response = ['success' => false, 'message' => 'Neznámá chyba.'];

// Ověříme, že je uživatel přihlášen
if (!is_user_logged_in()) {
    $response['message'] = 'Chyba: Nejste přihlášen.';
    echo json_encode($response);
    exit;
}

// Načteme data odeslaná JavaScriptem (očekáváme JSON)
$data = json_decode(file_get_contents('php://input'), true);
$song_id = $data['id'] ?? null;

if (empty($song_id)) {
    $response['message'] = 'Chyba: Nebylo poskytnuto ID písně ke smazání.';
} else {
    try {
        // Připravíme si jednoduchý dotaz pro smazání
        $sql = "DELETE FROM zp_songs WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        // Spustíme dotaz
        $stmt->execute([$song_id]);

        // Zkontrolujeme, zda byl skutečně nějaký řádek smazán
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Píseň byla úspěšně smazána.';
        } else {
            $response['message'] = 'Chyba: Píseň s daným ID nebyla v databázi nalezena.';
        }

    } catch (PDOException $e) {
        // V případě chyby databáze
        $response['message'] = 'Chyba databáze: ' . $e->getMessage();
    }
}

// Odešleme odpověď zpět JavaScriptu
echo json_encode($response);