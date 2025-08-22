<?php
// soubor: logout.php
session_start();

// Smaže všechny proměnné v session
session_unset();

// Zničí session
session_destroy();

// Přesměruje na přihlašovací stránku
header('Location: login.php');
exit;
?>