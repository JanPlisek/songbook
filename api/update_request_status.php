<?php
// soubor: api/update_request_status.php
session_start();
header('Content-Type: application/json');

require_once '../includes/database.php';
require_once '../includes/functions.php';

// Toto může měnit pouze administrátor
if (!is_admin()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění.']);
    exit;
}

// Načteme data odeslaná JavaScriptem (očekáváme JSON)
$data = json_decode(file_get_contents('php://input'), true);
$request_id = $data['requestId'] ?? null;
$is_completed = isset($data['status']) ? (bool)$data['status'] : null;

if ($request_id === null || $is_completed === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Chybějící data.']);
    exit;
}

try {
    $sql = "UPDATE zp_requests SET is_completed = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$is_completed, $request_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chyba databáze: ' . $e->getMessage()]);
}