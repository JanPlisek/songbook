<?php
session_start();
include_once 'includes/functions.php'; // Použijeme include_once pro jistotu

// Pokud uživatel není přihlášen, přesměrujeme ho.
if (!is_user_logged_in()) {
    header('Location: login.php');
    exit;
}

$pageTitle = "Přidat píseň z URL";
// Zde už nenačítáme functions.php znovu
include 'includes/header.php';

// --- PHP logika ---
$message = ''; 
$message_type = 'success';
$form_data = ['title' => '', 'artist' => '', 'band' => '', 'key' => '', 'lyrics_json' => ''];

// KROK 1: ZPRACOVÁNÍ URL
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
            $form_data['band'] = trim($xpath->query('//h2')->item(0)->nodeValue ?? ''); // Předpoklad, že h2 je kapela

            $lyrics_node = $xpath->query('//div[@class="lyrics"]/pre[@class="format"]')->item(0);
            $raw_lyrics_text = $lyrics_node ? $lyrics_node->nodeValue : '';

            // --- VYLEPŠENÁ LOGIKA ZPRACOVÁNÍ TEXTU ---
            // Používáme stejný robustní parser jako v editor.php
            $lines = explode("\n", str_replace("\r", "", $raw_lyrics_text));
            $lyrics_data = [];
            $chord_line_regex = '/^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,-]+$/i';

            for ($i = 0; $i < count($lines); $i++) {
                $current_line = rtrim($lines[$i]);
                if (trim($current_line) === "") continue; 

                $is_current_line_chord = preg_match($chord_line_regex, trim($current_line));
                
                if ($is_current_line_chord) {
                    $next_line_index = $i + 1;
                    $text_line = "";
                    
                    while ($next_line_index < count($lines) && trim($lines[$next_line_index]) !== "" && preg_match($chord_line_regex, trim($lines[$next_line_index]))) {
                        $lyrics_data[] = ["chords" => $current_line, "text" => ""];
                        $current_line = rtrim($lines[$next_line_index]);
                        $next_line_index++;
                    }

                    if ($next_line_index < count($lines) && trim($lines[$next_line_index]) !== "") {
                        if (!preg_match($chord_line_regex, trim($lines[$next_line_index]))) {
                           $text_line = rtrim($lines[$next_line_index]);
                           $i = $next_line_index;
                        }
                    }
                    $lyrics_data[] = ["chords" => $current_line, "text" => $text_line];
                } else {
                    $lyrics_data[] = ["chords" => "", "text" => $current_line];
                }
            }
            // --- KONEC VYLEPŠENÉ LOGIKY ---

            $form_data['lyrics_json'] = json_encode($lyrics_data);
            $message = "Data načtena. Zkontrolujte a doplňte pole, poté uložte.";
        }
    }
}

// KROK 2: FINÁLNÍ ULOŽENÍ PÍSNIČKY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_song'])) {
    $title = $_POST['title']; 
    $artist_string = $_POST['artist']; 
    $band_string = $_POST['band'];
    $key = $_POST['key']; 
    $lyrics_json = $_POST['lyrics_json'];
    
    $artists_array = !empty($artist_string) ? array_map('trim', explode(',', $artist_string)) : [];
    $bands_array = !empty($band_string) ? array_map('trim', explode(',', $band_string)) : [];
    
    $lyrics_data = json_decode($lyrics_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $message = "Chyba: Data písně jsou poškozená a nelze je uložit. Zkuste prosím načíst URL znovu.";
        $message_type = 'error';
    } else {
        $id_name = !empty($artists_array) ? $artists_array[0] : (!empty($bands_array) ? $bands_array[0] : 'neznamy');
        $id = create_slug($title . '-' . $id_name);
        $filename = 'songs/' . $id . '.json';
        
        $final_json_data = [ 'id' => $id, 'title' => $title, 'artist' => $artists_array, 'band' => $bands_array, 'originalKey' => $key, 'capo' => 0, 'lyrics' => $lyrics_data ];
        
        file_put_contents($filename, json_encode($final_json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Aktualizace _masterlist.json
        $master_list_file = 'songs/_masterlist.json';
        $master_list = file_exists($master_list_file) ? json_decode(file_get_contents($master_list_file), true) : [];
        if (!is_array($master_list)) { $master_list = []; }
        $exists_index = -1; 
        foreach($master_list as $index => $song) { if ($song['id'] === $id) { $exists_index = $index; break; } }
        $new_entry = ['id' => $id, 'title' => $title, 'artist' => $artists_array, 'band' => $bands_array];
        if ($exists_index !== -1) { $master_list[$exists_index] = $new_entry; } else { $master_list[] = $new_entry; }
        file_put_contents($master_list_file, json_encode($master_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $message = "Úspěch! Písnička '$title' byla uložena."; $message_type = 'success';
        $form_data = ['title' => '', 'artist' => '', 'band' => '', 'key' => '', 'lyrics_json' => ''];
    }
}
?>

<div class="converter-page">
    <h1>Přidání písně z URL</h1>
    <p>Vložte URL adresu písničky z <code>velkyzpevnik.cz</code> a skript se pokusí automaticky stáhnout data. Poté zkontrolujte a doplňte pole.</p>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

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