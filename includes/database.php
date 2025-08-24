<?php
// soubor: includes/database.php
// Tento skript se stará o připojení k databázi a poskytuje
// připojovací objekt PDO pro další části aplikace.

// Nastavení pro připojení k databázi
$host = '127.0.0.1';       // Nebo 'localhost'
$db_name = 'zpevnik_db';   // Název databáze, kterou jsme právě vytvořili
$username = 'root';        // Výchozí uživatel pro XAMPP
$password = '';            // Výchozí heslo pro XAMPP je prázdné

// DSN - Data Source Name - řetězec s informacemi pro připojení
$dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";

// Možnosti pro PDO pro lepší zpracování chyb a výsledků
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hlaste chyby jako výjimky
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Načítejte data jako asociativní pole
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Používejte nativní připravené dotazy
];

try {
    // Pokusíme se vytvořit novou instanci PDO (připojit se k databázi)
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // Pokud se připojení nepodaří, zachytíme chybu a ukončíme skript
    // srozumitelnou chybovou hláškou.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Pokud vše proběhlo v pořádku, máme v proměnné $pdo aktivní připojení k databázi.
// Tuto proměnnou budeme používat v ostatních souborech.