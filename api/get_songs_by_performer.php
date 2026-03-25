<?php
// soubor: api/get_songs_by_performer.php

header('Content-Type: application/json');
require_once '../includes/database.php'; // Cesta o úroveň výš

$performer_id = $_GET['id'] ?? null;

if (!$performer_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Chybí ID interpreta.']);
    exit;
}

try {
    // Najdeme všechny písně (id, title), které jsou spojeny s daným ID interpreta
    $sql = "SELECT s.id, s.title 
            FROM zp_songs s
            JOIN zp_song_performers sp ON s.id = sp.song_id
            WHERE sp.performer_id = ?
            ORDER BY s.title COLLATE utf8mb4_czech_ci";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$performer_id]);
    $songs = $stmt->fetchAll();

    echo json_encode($songs);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Chyba databáze: ' . $e->getMessage()]);
}