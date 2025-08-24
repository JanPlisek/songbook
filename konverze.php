<?php
// soubor: konverze.php (FINÁLNÍ VERZE PRO DATABÁZI)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Připojíme databázi a funkce
require_once 'includes/database.php';
require_once 'includes/functions.php';

if (!is_user_logged_in()) { header('Location: login.php'); exit; }

$pageTitle = "Přidat píseň z URL";
include 'includes/header.php';

$message = ''; 
$message_type = 'success';
$form_data = ['title' => '', 'artist' => '', 'band' => '', 'key' => '', 'lyrics_json' => ''];

// =================================================================
// KROK 1: ZPRACOVÁNÍ URL (tato část zůstává téměř beze změny)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_url'])) {
    $url = $_POST['url'];
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $message = "Chyba: Vložte prosím platnou URL adresu."; 
        $message_type = 'error';
    } else {
        $html = fetch_url_content($url);
        if (!$html) {
            $message = "Chyba: Nepodařilo se načíst obsah z dané URL adresy."; 
            $message_type = 'error';
        } else {
            $doc = new DOMDocument(); 
            @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html); 
            $xpath = new DOMXPath($doc);
            
            $form_data['title'] = trim($xpath->query('//h1')->item(0)->nodeValue ?? '');
            $form_data['band'] = trim($xpath->query('//h2')->item(0)->nodeValue ?? '');

            $lyrics_node = $xpath->query('//div[@class="lyrics"]/pre[@class="format"]')->item(0);
            $raw_lyrics_text = $lyrics_node ? $lyrics_node->nodeValue : '';

            // Parser textu zůstává stejný
            $lines = explode("\n", str_replace("\r", "", $raw_lyrics_text));
            $lyrics_data = [];
            $chord_line_regex = '/^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,-]+$/i';
            $next_line_is_block_start = true;

            for ($i = 0; $i < count($lines); $i++) {
                $current_line = rtrim($lines[$i]);
                if (trim($current_line) === "") { $next_line_is_block_start = true; continue; }
                $line_object = [];
                if ($next_line_is_block_start) { $line_object['block_start'] = true; $next_line_is_block_start = false; }
                $is_current_line_chord = preg_match($chord_line_regex, trim($current_line));
                if ($is_current_line_chord) {
                    $line_object['chords'] = $current_line;
                    $next_line_index = $i + 1;
                    if ($next_line_index < count($lines) && !preg_match($chord_line_regex, trim($lines[$next_line_index]))) {
                        $line_object['text'] = rtrim($lines[$next_line_index]); $i++;
                    } else { $line_object['text'] = ""; }
                } else { $line_object['chords'] = ""; $line_object['text'] = $current_line; }
                $lyrics_data[] = $line_object;
            }

            $form_data['lyrics_json'] = json_encode($lyrics_data);
            $message = "Data načtena. Zkontrolujte a doplňte pole, poté uložte.";
        }
    }
}

// =================================================================
// KROK 2: FINÁLNÍ ULOŽENÍ PÍSNIČKY (NOVÁ VERZE S DATABÁZÍ)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_song'])) {
    $title = $_POST['title']; 
    $artist_string = $_POST['artist']; 
    $band_string = $_POST['band']; 
    $key = $_POST['key']; 
    $lyrics_json = $_POST['lyrics_json'];
    $lyrics_data = json_decode($lyrics_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $message = "Chyba: Data písně jsou poškozená (neplatný JSON)."; 
        $message_type = 'error';
    } else {
        // Použijeme stejný postup jako v editor.php
        try {
            $pdo->beginTransaction();

            $artists_array = !empty($artist_string) ? array_map('trim', explode(',', $artist_string)) : [];
            $bands_array = !empty($band_string) ? array_map('trim', explode(',', $band_string)) : [];
            $performer_ids = [];

            foreach ($artists_array as $artist_name) {
                $stmt = $pdo->prepare("SELECT id FROM zp_performers WHERE name = ? AND type = 'person'");
                $stmt->execute([$artist_name]);
                $p_id = $stmt->fetchColumn();
                if (!$p_id) {
                    $parts = explode(' ', $artist_name, 2);
                    $stmt = $pdo->prepare("INSERT INTO zp_performers (type, name, first_name, last_name) VALUES ('person', ?, ?, ?)");
                    $stmt->execute([$artist_name, $parts[0], $parts[1] ?? null]);
                    $p_id = $pdo->lastInsertId();
                }
                $performer_ids[] = $p_id;
            }
            foreach ($bands_array as $band_name) {
                $stmt = $pdo->prepare("SELECT id FROM zp_performers WHERE name = ? AND type = 'band'");
                $stmt->execute([$band_name]);
                $p_id = $stmt->fetchColumn();
                if (!$p_id) {
                    $stmt = $pdo->prepare("INSERT INTO zp_performers (type, name) VALUES ('band', ?)");
                    $stmt->execute([$band_name]);
                    $p_id = $pdo->lastInsertId();
                }
                $performer_ids[] = $p_id;
            }

            $id_name = !empty($artists_array) ? $artists_array[0] : (!empty($bands_array) ? $bands_array[0] : 'neznamy');
            $song_id = create_slug($title . '-' . $id_name);
            
            // Zde použijeme INSERT ... ON DUPLICATE KEY pro případ, že by píseň se stejným ID již existovala
            $sql = "INSERT INTO zp_songs (id, title, original_key, capo, lyrics) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    original_key = VALUES(original_key), 
                    capo = VALUES(capo), 
                    lyrics = VALUES(lyrics)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$song_id, $title, $key, 0, json_encode($lyrics_data, JSON_UNESCAPED_UNICODE)]);

            $stmt = $pdo->prepare("DELETE FROM zp_song_performers WHERE song_id = ?");
            $stmt->execute([$song_id]);

            if (!empty($performer_ids)) {
                $stmt = $pdo->prepare("INSERT INTO zp_song_performers (song_id, performer_id) VALUES (?, ?)");
                foreach ($performer_ids as $p_id) {
                    $stmt->execute([$song_id, $p_id]);
                }
            }
            
            $pdo->commit();
            
            $edit_link = '<a href="editor.php?id=' . urlencode($song_id) . '" class="btn" style="margin-left: 1rem; background-color: #28a745;">Upravit píseň</a>';
            $message = '<div style="display: flex; align-items: center; justify-content: space-between;">' .
               "<span>Úspěch! Písnička '" . htmlspecialchars($title) . "' byla uložena.</span>" .
               $edit_link .
               '</div>';
            $form_data = ['title' => '', 'artist' => '', 'band' => '', 'key' => '', 'lyrics_json' => ''];

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Chyba při ukládání do databáze: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<div class="converter-page">
    <h1>Přidání písně z URL</h1>
    <p>Vložte URL adresu písničky z <code>velkyzpevnik.cz</code> a skript se pokusí automaticky stáhnout data. Poté zkontrolujte a doplňte pole.</p>
    <?php if ($message): ?> <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div> <?php endif; ?>
    
    <form class="form-section" action="konverze.php" method="POST">
        <fieldset>
            <legend><h3>Krok 1: Načíst data z URL</h3></legend>
            <label for="url">URL adresa</label>
            <input type="url" id="url" name="url" placeholder="https://www.velkyzpevnik.cz/zpevnik/..." required>
            <button type="submit" name="fetch_url">Načíst data</button>
        </fieldset>
    </form>
    
    <form class="form-section" action="konverze.php" method="POST">
        <fieldset <?php if(empty($form_data['lyrics_json'])) echo 'disabled'; ?>>
            <legend><h3>Krok 2: Zkontrolovat, upravit a uložit</h3></legend>
            <label for="title">Název písně:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($form_data['title']); ?>" required>
            <label for="artist">Autor / Osoba (více oddělte čárkou):</label>
            <input type="text" id="artist" name="artist" value="<?php echo htmlspecialchars($form_data['artist']); ?>">
            <label for="band">Kapela (více oddělte čárkou):</label>
            <input type="text" id="band" name="band" value="<?php echo htmlspecialchars($form_data['band']); ?>">
            <label for="key">Originální tónina:</label>
            <input type="text" id="key" name="key" value="<?php echo htmlspecialchars($form_data['key']); ?>">
            <input type="hidden" name="lyrics_json" value='<?php echo htmlspecialchars($form_data['lyrics_json'], ENT_QUOTES, 'UTF-8'); ?>'>
            <label for="preview">Náhled dat (pouze pro čtení):</label>
            <textarea id="preview" readonly style="min-height: 150px;"><?php echo htmlspecialchars(json_encode(json_decode($form_data['lyrics_json'] ?? '[]'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
            <button type="submit" name="save_song">Finálně uložit písničku</button>
        </fieldset>
    </form>
</div>

<?php include 'includes/footer.php'; ?>