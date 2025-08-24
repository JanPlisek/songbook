<?php
// soubor: editor.php (NOVÉ DVOUSLOUPCOVÉ ROZLOŽENÍ)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/database.php';
require_once 'includes/functions.php';

if (!is_user_logged_in()) { header('Location: login.php'); exit; }

// --- Zpracování dat a načítání (TATO ČÁST ZŮSTÁVÁ BEZE ZMĚNY) ---
$message = ''; 
$message_type = 'info';
$form_data = ['id' => null, 'title' => '', 'artist' => '', 'band' => '', 'key' => '', 'capo' => 0, 'raw_content' => ''];

if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $song_id_for_edit = $_GET['id'];
    $sql = "SELECT * FROM zp_songs WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$song_id_for_edit]);
    $song_data = $stmt->fetch();
    if ($song_data) {
        $sql_artists = "SELECT p.name FROM zp_performers p JOIN zp_song_performers sp ON p.id = sp.performer_id WHERE sp.song_id = ? AND p.type = 'person'";
        $stmt_artists = $pdo->prepare($sql_artists);
        $stmt_artists->execute([$song_id_for_edit]);
        $artists = $stmt_artists->fetchAll(PDO::FETCH_COLUMN);
        $sql_bands = "SELECT p.name FROM zp_performers p JOIN zp_song_performers sp ON p.id = sp.performer_id WHERE sp.song_id = ? AND p.type = 'band'";
        $stmt_bands = $pdo->prepare($sql_bands);
        $stmt_bands->execute([$song_id_for_edit]);
        $bands = $stmt_bands->fetchAll(PDO::FETCH_COLUMN);
        $form_data['id'] = $song_data['id'];
        $form_data['title'] = $song_data['title'];
        $form_data['artist'] = implode(', ', $artists);
        $form_data['band'] = implode(', ', $bands);
        $form_data['key'] = $song_data['original_key'];
        $form_data['capo'] = $song_data['capo'];
        $lyrics_array = json_decode($song_data['lyrics'], true);
        $raw_content = '';
        if (is_array($lyrics_array)) {
            $is_first_block = true;
            foreach ($lyrics_array as $line) {
                if (isset($line['block_start']) && $line['block_start'] && !$is_first_block) { $raw_content .= "\n"; }
                if (isset($line['chords']) && $line['chords'] !== "") { $raw_content .= $line['chords'] . "\n"; }
                if (isset($line['text']) && $line['text'] !== "") { $raw_content .= $line['text'] . "\n"; }
                $is_first_block = false;
            }
        }
        $form_data['raw_content'] = $raw_content;
    } else { /* ... chyba ... */ }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_song'])) {
    // ... kompletní logika pro uložení dat (zůstává beze změny, jen zkráceno pro přehlednost) ...
    $song_id = $_POST['id'] ?: null; $title = $_POST['title']; $artist_string = $_POST['artist']; $band_string = $_POST['band']; $key = $_POST['key']; $capo = (int)$_POST['capo']; $raw_content = $_POST['song_content'];
    $lines = explode("\n", str_replace("\r", "", $raw_content)); $lyrics_data = []; $chord_line_regex = '/^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,-]+$/i'; $next_line_is_block_start = true;
    for ($i = 0; $i < count($lines); $i++) { $current_line = rtrim($lines[$i]); if (trim($current_line) === "") { $next_line_is_block_start = true; continue; } $line_object = []; if ($next_line_is_block_start) { $line_object['block_start'] = true; $next_line_is_block_start = false; } $is_current_line_chord = preg_match($chord_line_regex, trim($current_line)); if ($is_current_line_chord) { $line_object['chords'] = $current_line; $next_line_index = $i + 1; if ($next_line_index < count($lines) && !preg_match($chord_line_regex, trim($lines[$next_line_index]))) { $line_object['text'] = rtrim($lines[$next_line_index]); $i++; } else { $line_object['text'] = ""; } } else { $line_object['chords'] = ""; $line_object['text'] = $current_line; } $lyrics_data[] = $line_object; }
    try { $pdo->beginTransaction(); $artists_array = !empty($artist_string) ? array_map('trim', explode(',', $artist_string)) : []; $bands_array = !empty($band_string) ? array_map('trim', explode(',', $band_string)) : []; $performer_ids = [];
    foreach ($artists_array as $artist_name) { $stmt = $pdo->prepare("SELECT id FROM zp_performers WHERE name = ? AND type = 'person'"); $stmt->execute([$artist_name]); $p_id = $stmt->fetchColumn(); if (!$p_id) { $parts = explode(' ', $artist_name, 2); $stmt = $pdo->prepare("INSERT INTO zp_performers (type, name, first_name, last_name) VALUES ('person', ?, ?, ?)"); $stmt->execute([$artist_name, $parts[0], $parts[1] ?? null]); $p_id = $pdo->lastInsertId(); } $performer_ids[] = $p_id; }
    foreach ($bands_array as $band_name) { $stmt = $pdo->prepare("SELECT id FROM zp_performers WHERE name = ? AND type = 'band'"); $stmt->execute([$band_name]); $p_id = $stmt->fetchColumn(); if (!$p_id) { $stmt = $pdo->prepare("INSERT INTO zp_performers (type, name) VALUES ('band', ?)"); $stmt->execute([$band_name]); $p_id = $pdo->lastInsertId(); } $performer_ids[] = $p_id; }
    if (!$song_id) { $id_name = !empty($artists_array) ? $artists_array[0] : (!empty($bands_array) ? $bands_array[0] : 'neznamy'); $song_id = create_slug($title . '-' . $id_name); }
    $sql = "INSERT INTO zp_songs (id, title, original_key, capo, lyrics) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), original_key = VALUES(original_key), capo = VALUES(capo), lyrics = VALUES(lyrics)";
    $stmt = $pdo->prepare($sql); $stmt->execute([$song_id, $title, $key, $capo, json_encode($lyrics_data, JSON_UNESCAPED_UNICODE)]);
    $stmt = $pdo->prepare("DELETE FROM zp_song_performers WHERE song_id = ?"); $stmt->execute([$song_id]);
    if (!empty($performer_ids)) { $stmt = $pdo->prepare("INSERT INTO zp_song_performers (song_id, performer_id) VALUES (?, ?)"); foreach ($performer_ids as $p_id) { $stmt->execute([$song_id, $p_id]); } }
    $pdo->commit(); $message = "Úspěch! Písnička '" . htmlspecialchars($title) . "' byla uložena."; $message_type = 'success';
    if (empty($_POST['id'])) { $form_data = ['id' => null, 'title' => '', 'artist' => '', 'band' => '', 'key' => '', 'capo' => 0, 'raw_content' => '']; }
    } catch (Exception $e) { $pdo->rollBack(); $message = "Chyba při ukládání do databáze: " . $e->getMessage(); $message_type = 'error'; $form_data = $_POST; $form_data['raw_content'] = $_POST['song_content']; }
}
// --- KONEC ZPRACOVÁNÍ DAT ---

// Přidáme si speciální třídu pro body, aby se aplikovaly naše nové styly
$bodyClass = "editor-layout-body"; 
$pageTitle = "Editor písní";
include 'includes/header.php';
?>

<div class="editor-page">
    <form action="editor.php" method="POST" class="editor-main-grid">
        
        <div class="editor-metadata-column">
            <div class="form-section">
                <h1><?php echo isset($form_data['id']) ? 'Upravit píseň' : 'Nová píseň'; ?></h1>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <input type="hidden" name="id" value="<?php echo htmlspecialchars($form_data['id'] ?? ''); ?>">

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
                
                <button type="submit" name="save_song">Zpracovat a uložit písničku</button>
            </div>
        </div>

        <div class="editor-lyrics-column">
            <div class="form-section">
                <fieldset>
                    <legend><h3>Text a akordy</h3></legend>
                    <p>Pro oddělení slok použijte prázdný řádek. Pište akordy na samostatný řádek nad text.</p>
                    <textarea id="song_content" name="song_content" required><?php echo htmlspecialchars($form_data['raw_content']); ?></textarea>
                </fieldset>
            </div>
        </div>

    </form>
</div>

<?php 
// Patičku načteme bez kontejneru, protože ten už máme
include 'includes/footer.php'; 
?>