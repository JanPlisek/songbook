<?php
session_start();
include_once 'includes/functions.php'; // Toto je v pořádku, je to potřeba pro kontrolu níže.

// Pokud uživatel není přihlášen, ukončíme skript.
if (!is_user_logged_in()) {
    header('Location: login.php');
    exit;
}

// ------ ZDE NASTALA ZMĚNA ------
// Původní řádek: include 'includes/functions.php'; include 'includes/header.php';
// Nový řádek (už nenačítáme znovu functions.php, o to se postará header):
$pageTitle = "Editor písní";
include 'includes/header.php';

$message = ''; 
$message_type = 'success';
$form_data = ['title' => '', 'artist' => '', 'band' => '', 'key' => '', 'capo' => 0, 'raw_content' => ''];

// Načtení existující písně k editaci
if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $song_id = $_GET['id']; 
    // Bezpečnostní pojistka proti directory traversal
    if (strpos($song_id, '/') !== false || strpos($song_id, '\\') !== false || strpos($song_id, '.') !== false) {
        die('Neplatné ID písně.');
    }
    $file_path = 'songs/' . $song_id . '.json';
    if (file_exists($file_path)) {
        $song_data = json_decode(file_get_contents($file_path), true);
        if ($song_data) {
            $form_data['title'] = $song_data['title'] ?? '';
            $form_data['artist'] = implode(', ', $song_data['artist'] ?? []);
            $form_data['band'] = implode(', ', $song_data['band'] ?? []);
            $form_data['key'] = $song_data['originalKey'] ?? ''; 
            $form_data['capo'] = $song_data['capo'] ?? 0;
            $raw_content = '';
            if (isset($song_data['lyrics']) && is_array($song_data['lyrics'])) {
                foreach ($song_data['lyrics'] as $line) {
                    if (isset($line['chords']) && $line['chords'] !== "") { 
                        $raw_content .= $line['chords'] . "\n"; 
                    }
                    if (isset($line['text']) && $line['text'] !== "") {
                        $raw_content .= $line['text'] . "\n";
                    } elseif (isset($line['chords']) && $line['chords'] !== "") {
                        // Přidá prázdný řádek pro akordový řádek bez textu, aby se zachovalo formátování
                    } else {
                        $raw_content .= "\n";
                    }
                }
            }
            $form_data['raw_content'] = $raw_content;
            $message = "Načtena píseň k úpravě: " . htmlspecialchars($song_data['title']);
        }
    } else {
        $message = "Chyba: Soubor pro píseň s ID '$song_id' nebyl nalezen.";
        $message_type = 'error';
    }
}

// Zpracování a uložení formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_song'])) {
    $title = $_POST['title']; 
    $artist_string = $_POST['artist']; 
    $band_string = $_POST['band']; 
    $key = $_POST['key']; 
    $capo = (int)$_POST['capo']; 
    $raw_content = $_POST['song_content'];
    
    $lines = explode("\n", str_replace("\r", "", $raw_content));
    $lyrics_data = []; 
    $chord_line_regex = '/^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,-]+$/i';
    
    for ($i = 0; $i < count($lines); $i++) {
        $current_line = rtrim($lines[$i]);
        if ($current_line === "") continue; // Přeskakujeme prázdné řádky
        
        $is_current_line_chord = preg_match($chord_line_regex, trim($current_line));
        
        if ($is_current_line_chord) {
            $next_line_index = $i + 1;
            $text_line = "";
            // Najdeme další ne-akordový řádek
            while ($next_line_index < count($lines) && preg_match($chord_line_regex, trim($lines[$next_line_index]))) {
                // Tento řádek je také akordový, přidáme předchozí jen s akordy
                $lyrics_data[] = ["chords" => $current_line, "text" => ""];
                $current_line = rtrim($lines[$next_line_index]);
                $next_line_index++;
            }
            if ($next_line_index < count($lines)) {
                $text_line = rtrim($lines[$next_line_index]);
                $i = $next_line_index; // Posuneme hlavní index
            }
            $lyrics_data[] = ["chords" => $current_line, "text" => $text_line];
        } else { 
            $lyrics_data[] = ["chords" => "", "text" => $current_line]; 
        }
    }
    
    $artists_array = !empty($artist_string) ? array_map('trim', explode(',', $artist_string)) : [];
    $bands_array = !empty($band_string) ? array_map('trim', explode(',', $band_string)) : [];
    
    $id_name = !empty($artists_array) ? $artists_array[0] : (!empty($bands_array) ? $bands_array[0] : 'neznamy');
    
    $id = create_slug($title . '-' . $id_name);
    $filename = 'songs/' . $id . '.json';
    
    $final_json_data = [ 'id' => $id, 'title' => $title, 'artist' => $artists_array, 'band' => $bands_array, 'originalKey' => $key, 'capo' => $capo, 'lyrics' => $lyrics_data ];
    file_put_contents($filename, json_encode($final_json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Aktualizace _masterlist.json
    $master_list_file = 'songs/_masterlist.json';
    $master_list = [];
    if (file_exists($master_list_file)) {
        $master_list = json_decode(file_get_contents($master_list_file), true) ?? [];
    }
    
    $exists_index = -1; 
    foreach($master_list as $index => $song) { 
        if ($song['id'] === $id) { 
            $exists_index = $index; 
            break; 
        } 
    }
    
    $new_entry = ['id' => $id, 'title' => $title, 'artist' => $artists_array, 'band' => $bands_array];
    if ($exists_index !== -1) { 
        $master_list[$exists_index] = $new_entry; 
    } else { 
        $master_list[] = $new_entry; 
    }
    
    file_put_contents($master_list_file, json_encode($master_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $message = "Úspěch! Písnička '$title' byla uložena."; 
    $message_type = 'success';
}
?>

<div class="editor-page">
    <h1>Editor písní</h1>
    <?php if ($message): ?> <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
    <form class="form-section" action="editor.php" method="POST">
        <fieldset>
            <legend><h3>Metadata písně</h3></legend>
            <label for="title">Název písně:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($form_data['title']); ?>" required>
            <label for="artist">Autor / Osoba (více oddělte čárkou):</label>
            <input type="text" id="artist" name="artist" value="<?php echo htmlspecialchars($form_data['artist']); ?>">
            <label for="band">Kapela (více oddělte čárkou):</label>
            <input type="text" id="band" name="band" value="<?php echo htmlspecialchars($form_data['band']); ?>">
            <label for="key">Originální tónina:</label>
            <input type="text" id="key" name="key" value="<?php echo htmlspecialchars($form_data['key']); ?>" placeholder="např. G nebo Am">
            <label for="capo">Pozice kapodastru:</label>
            <input type="number" id="capo" name="capo" value="<?php echo htmlspecialchars($form_data['capo']); ?>">
        </fieldset>
        <fieldset>
            <legend><h3>Text a akordy</h3></legend>
            <p style="font-size: 0.9em; color: #666;">Pište akordy na samostatný řádek nad text. Prázdné řádky budou zachovány.</p>
            <textarea id="song_content" name="song_content" required><?php echo htmlspecialchars($form_data['raw_content']); ?></textarea>
        </fieldset>
        <button type="submit" name="save_song">Zpracovat a uložit písničku</button>
    </form>
</div>
<?php include 'includes/footer.php'; ?>