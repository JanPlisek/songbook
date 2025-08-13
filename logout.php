<?php
// soubor: logout.php

// Smaže všechny proměnné v session
session_unset();

// Zničí session
session_destroy();

// Přesměruje na přihlašovací stránku
header('Location: login.php');
exit;
?>