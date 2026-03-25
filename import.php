<?php
// soubor: import.php
// JEDNORÁZOVÝ SKRIPT PRO MIGRACI DAT Z JSON SOUBORŮ DO DATABÁZE MYSQL

// Zapneme zobrazení všech chyb, abychom viděli, co se děje
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Import písniček do databáze</h1>";

// Načteme si naše připojení k databázi
require_once 'includes/database.php';

$songs_dir = 'songs/';
$imported_count = 0;
$skipped_count = 0;

try {
    // Začátek transakce: Pokud se něco pokazí, nic se do databáze neuloží
    $pdo->beginTransaction();

    // Projdeme všechny soubory v adresáři 'songs'
    $files = scandir($songs_dir);
    foreach ($files as $file) {
        // Zpracováváme jen soubory s koncovkou .json a ignorujeme _masterlist
        if (pathinfo($file, PATHINFO_EXTENSION) == 'json' && $file !== '_masterlist.json') {
            
            $json_content = file_get_contents($songs_dir . $file);
            $song_data = json_decode($json_content, true);

            // Zkontrolujeme, zda jsou data platná
            if (!$song_data || empty($song_data['id']) || empty($song_data['title'])) {
                echo "<p style='color: orange;'>Přeskakuji soubor $file - chybí ID nebo název.</p>";
                $skipped_count++;
                continue;
            }

            echo "<p>Importuji: <strong>{$song_data['title']}</strong> (ID: {$song_data['id']})</p>";

            // --- 1. ZPRACOVÁNÍ INTERPRETŮ A KAPEL ---
            $performer_ids = [];
            
            // Sloučíme autory (typ 'person') a kapely (typ 'band') do jednoho pole pro snadnější zpracování
            $artists = $song_data['artist'] ?? [];
            $bands = $song_data['band'] ?? [];

            foreach ($artists as $artist_name) {
                // Připravíme si jméno a příjmení, pokud je to možné
                $parts = explode(' ', trim($artist_name), 2);
                $first_name = $parts[0];
                $last_name = $parts[1] ?? null;

                // Zkusíme najít, zda interpret již v databázi existuje
                $stmt = $pdo->prepare("SELECT id FROM zp_performers WHERE name = ?");
                $stmt->execute([trim($artist_name)]);
                $performer_id = $stmt->fetchColumn();

                if (!$performer_id) {
                    // Pokud neexistuje, vložíme ho
                    $stmt = $pdo->prepare("INSERT INTO zp_performers (type, name, first_name, last_name) VALUES ('person', ?, ?, ?)");
                    $stmt->execute([trim($artist_name), $first_name, $last_name]);
                    $performer_id = $pdo->lastInsertId(); // Získáme ID nově vloženého záznamu
                }
                $performer_ids[] = $performer_id;
            }

            foreach ($bands as $band_name) {
                // Zkusíme najít, zda kapela již v databázi existuje
                $stmt = $pdo->prepare("SELECT id FROM zp_performers WHERE name = ?");
                $stmt->execute([trim($band_name)]);
                $performer_id = $stmt->fetchColumn();

                if (!$performer_id) {
                    // Pokud neexistuje, vložíme ji
                    $stmt = $pdo->prepare("INSERT INTO zp_performers (type, name) VALUES ('band', ?)");
                    $stmt->execute([trim($band_name)]);
                    $performer_id = $pdo->lastInsertId();
                }
                $performer_ids[] = $performer_id;
            }

            // --- 2. VLOŽENÍ SAMOTNÉ PÍSNIČKY ---
            // Použijeme "INSERT ... ON DUPLICATE KEY UPDATE", aby bylo možné skript spustit vícekrát
            // bez toho, aby se vytvářely duplicitní písně.
            $sql = "INSERT INTO zp_songs (id, title, original_key, capo, lyrics) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    original_key = VALUES(original_key), 
                    capo = VALUES(capo), 
                    lyrics = VALUES(lyrics)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $song_data['id'],
                $song_data['title'],
                $song_data['originalKey'] ?? null,
                $song_data['capo'] ?? 0,
                json_encode($song_data['lyrics'], JSON_UNESCAPED_UNICODE) // Převedeme pole na JSON text
            ]);

            // --- 3. PROPOJENÍ PÍSNĚ A INTERPRETŮ ---
            // Nejdříve smažeme stará propojení pro případ, že aktualizujeme
            $stmt = $pdo->prepare("DELETE FROM zp_song_performers WHERE song_id = ?");
            $stmt->execute([$song_data['id']]);

            // Vložíme nová propojení
            if (!empty($performer_ids)) {
                $stmt = $pdo->prepare("INSERT INTO zp_song_performers (song_id, performer_id) VALUES (?, ?)");
                foreach ($performer_ids as $p_id) {
                    $stmt->execute([$song_data['id'], $p_id]);
                }
            }

            $imported_count++;
        }
    }

    // Konec transakce: Vše proběhlo v pořádku, takže trvale uložíme změny
    $pdo->commit();
    
    echo "<h2>Import dokončen!</h2>";
    echo "<p style='color: green;'>Úspěšně naimportováno: $imported_count písní.</p>";
    echo "<p style='color: orange;'>Přeskočeno: $skipped_count souborů.</p>";
    echo "<p>Nyní můžete zkontrolovat data v phpMyAdmin. Tento soubor (`import.php`) můžete poté smazat.</p>";


} catch (\PDOException $e) {
    // Pokud nastala chyba, vrátíme všechny změny zpět
    $pdo->rollBack();
    echo "<h2>Chyba!</h2>";
    echo "<p style='color: red;'>Při importu došlo k chybě: " . $e->getMessage() . "</p>";
    echo "<p>Databáze byla vrácena do původního stavu. Žádná data nebyla importována.</p>";
}