<?php
// soubor: includes/gatekeeper.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pokud session proměnná neexistuje nebo není true, přesměruj na login.
if (!isset($_SESSION['zpevnik_heslo_ok']) || $_SESSION['zpevnik_heslo_ok'] !== true) {
    header('Location: login.php');
    exit;
}
?>