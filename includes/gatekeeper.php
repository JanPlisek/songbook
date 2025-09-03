<?php
// soubor: includes/gatekeeper.php (VYLEPŠENÁ VERZE)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pokud session proměnná neexistuje nebo není true...
if (!isset($_SESSION['zpevnik_heslo_ok']) || $_SESSION['zpevnik_heslo_ok'] !== true) {
    
    // ...zapamatujeme si, na jakou stránku chtěl uživatel původně jít.
    $redirectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    // Přesměrujeme na přihlašovací stránku a předáme jí původní cíl jako parametr.
    header('Location: login.php?redirect=' . urlencode($redirectUrl));
    exit;
}
?>