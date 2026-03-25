<?php
// soubor: api/submit_request.php
session_start();
header('Content-Type: application/json');
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Zkontrolujeme, zda je uživatel přihlášen (jako ochrana)
if (!is_user_logged_in()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Nejste přihlášen.']);
    exit;
}

// Načteme data z POST požadavku
$song_title = $_POST['song_title'] ?? '';
$requester_name = $_POST['requester_name'] ?? '';
$note = $_POST['note'] ?? '';

if (empty($song_title)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Název písně je povinný.']);
    exit;
}

try {
    $sql = "INSERT INTO zp_requests (song_title, requester_name, note) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$song_title, $requester_name, $note]);

    echo json_encode(['success' => true, 'message' => 'Požadavek byl úspěšně zaznamenán. Děkujeme!']);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Chyba databáze: ' . $e->getMessage()]);
}