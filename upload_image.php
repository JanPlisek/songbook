<?php
// soubor: upload_image.php

// Inicializace a bezpečnost
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'includes/functions.php';

// Budeme odpovídat ve formátu JSON
header('Content-Type: application/json');

// 1. Kontrola, zda je uživatel přihlášen
if (!is_user_logged_in()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Přístup odepřen.']);
    exit;
}

// 2. Kontrola, zda byla data odeslána správně
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_FILES['cropped_image']) ||
    !isset($_POST['artist_name']) ||
    empty(trim($_POST['artist_name']))
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Chybný požadavek. Chybí obrázek nebo jméno interpreta.']);
    exit;
}

// 3. Zpracování nahraného souboru
$image_file = $_FILES['cropped_image'];
$artist_name = $_POST['artist_name'];

// Kontrola chyb při nahrávání
if ($image_file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Chyba při nahrávání souboru.']);
    exit;
}

// Vytvoření bezpečného názvu souboru (vždy bude .png, protože ho vytváříme z plátna v prohlížeči)
$filename = create_slug($artist_name) . '.png';
$upload_dir = 'assets/img/interprets/';
$destination = $upload_dir . $filename;

// Přesuneme dočasný soubor na finální místo
if (move_uploaded_file($image_file['tmp_name'], $destination)) {
    // Úspěch! Vrátíme úspěšnou odpověď a cestu k souboru.
    echo json_encode([
        'success' => true,
        'message' => 'Obrázek byl úspěšně nahrán a oříznut.',
        'filepath' => $destination
    ]);
} else {
    // Chyba při ukládání
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Nepodařilo se uložit soubor na server.']);
}

exit;
?>