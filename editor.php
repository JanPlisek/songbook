<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/display/rulers.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/material-darker.min.css">

<?php
// soubor: editor.php (FINÁLNÍ OPRAVENÁ VERZE)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Stránka je pouze pro admina, ale gatekeeper nepotřebujeme,
// protože kontrolujeme přihlášení níže a admin má přístup ke všemu.
if (!is_admin()) { header('Location: index.php'); exit; }


// --- ZPRACOVÁNÍ DAT A NAČÍTÁNÍ ---
$message = '';
$message_type = 'info';
$form_data = ['id' => null, 'title' => '', 'artist' => '', 'band' => '', 'key' => '', 'capo' => 0, 'raw_content' => ''];

// Načtení existující písně pro editaci (GET požadavek)
if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $song_id_for_edit = $_GET['id'];
    $sql = "SELECT * FROM zp_songs WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$song_id_for_edit]);
    $song_data = $stmt->fetch();
    if ($song_data) {
        // ... (načítací logika zůstává stejná)
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
                
                // Přidáme štítek (label), pokud existuje, s křížkem na začátku
                $prefix = (!empty($line['label'])) ? "#" . $line['label'] . " " : "";
                
                if (isset($line['chords']) && $line['chords'] !== "") { 
                    $raw_content .= $prefix . $line['chords'] . "\n"; 
                    $prefix = ""; // Štítek už byl vypsán u akordů
                }
                if (isset($line['text']) && $line['text'] !== "") { 
                    $raw_content .= $prefix . $line['text'] . "\n"; 
                    $prefix = ""; // Štítek už byl vypsán u textu
                }
                // Pokud zbylo pouze označení (label) bez textu i akordů
                if ($prefix !== "") {
                    $raw_content .= rtrim($prefix) . "\n";
                }
                $is_first_block = false;
            }
        }
        $form_data['raw_content'] = $raw_content;
    } else { $message = "Chyba: Píseň s daným ID nebyla nalezena."; $message_type = 'error'; }
}

// Zpracování odeslaného formuláře (POST požadavek)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_song'])) {
    // --- TOTO JE PLNÁ A SPRÁVNÁ VERZE UKLÁDACÍ LOGIKY ---
    $song_id = $_POST['id'] ?: null;
    $title = trim($_POST['title']);
    $artist_string = trim($_POST['artist']);
    $band_string = trim($_POST['band']);
    $key = trim($_POST['key']);
    $capo = (int)$_POST['capo'];
    $raw_content = $_POST['song_content'];

    // 1. Zpracování textu a akordů
    $lines = explode("\n", str_replace("\r", "", $raw_content));
    $lyrics_data = [];
    $chord_line_regex = '/^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,\-\+\*]+$/i';
    $next_line_is_block_start = true;
    for ($i = 0; $i < count($lines); $i++) { 
        $current_line = rtrim($lines[$i]); 
        if (trim($current_line) === "") { $next_line_is_block_start = true; continue; } 
        $line_object = []; 
        $is_block_start = false;
        if ($next_line_is_block_start) { 
            $line_object['block_start'] = true; 
            $is_block_start = true;
            $next_line_is_block_start = false; 
        } 
        
        $trimmed = trim($current_line);
        $label = "";

        // --- Detekce štítků (verše, refrény) ---
        // Provádíme pouze na začátku bloku
        if ($is_block_start) {
            // 1. Explicitní štítek začínající křížkem (např. #1, #R, #Ref, #Mezi)
            if (preg_match('/^#([a-z0-9]+)\s*(.*)/i', $trimmed, $matches)) {
                $label = $matches[1];
                $current_line = $matches[2];
                $trimmed = trim($current_line);
            } 
            // 2. Automatická detekce (1., 2., R:, Ref:)
            elseif (preg_match('/^(([0-9]+\.)|(R:)|(Ref:)|(Mezi:))\s*(.*)/i', $trimmed, $matches)) {
                $label = $matches[1];
                $current_line = $matches[6]; // Poslední skupina v regexu
                $trimmed = trim($current_line);
            }
            if ($label) {
                $line_object['label'] = rtrim($label, ': '); // Vyčistíme koncovou dvojtečku/tečku pro hezčí zobrazení
            }
        }

        $forced_chord = (strpos($trimmed, '.') === 0);
        $forced_text = (strpos($trimmed, '!') === 0);

        if ($forced_chord) {
            $current_line_for_storage = ltrim(substr($current_line, strpos($current_line, '.') + 1));
            $is_current_line_chord = true;
        } elseif ($forced_text) {
            $current_line_for_storage = ltrim(substr($current_line, strpos($current_line, '!') + 1));
            $is_current_line_chord = false;
        } else {
            $current_line_for_storage = $current_line;
            $is_current_line_chord = preg_match($chord_line_regex, $trimmed);
        }

        if ($is_current_line_chord) { 
            $line_object['chords'] = $current_line_for_storage; 
            $next_line_index = $i + 1; 
            if ($next_line_index < count($lines)) {
                $next_raw = $lines[$next_line_index];
                $next_trimmed = trim($next_raw);
                // Je příští řádek text? (Není prázdný, nezačíná tečkou a neprojde regexem na akordy)
                if ($next_trimmed !== "" && strpos($next_trimmed, '.') !== 0 && !preg_match($chord_line_regex, $next_trimmed)) {
                    // Pokud je i tento řádek vynucený text (!), odstraníme vykřičník
                    $next_raw_clean = $next_raw;
                    if (strpos($next_trimmed, '!') === 0) {
                        $next_raw_clean = ltrim(substr($next_raw, strpos($next_raw, '!') + 1));
                    }
                    $line_object['text'] = rtrim($next_raw_clean); 
                    $i++; 
                } else {
                    $line_object['text'] = "";
                }
            } else { $line_object['text'] = ""; } 
        } else { 
            $line_object['chords'] = ""; 
            $line_object['text'] = $current_line_for_storage; 
        } 
        $lyrics_data[] = $line_object; 
    }

    try {
        $pdo->beginTransaction();

        // 2. Zpracování interpretů a kapel
        $artists_array = !empty($artist_string) ? array_map('trim', explode(',', $artist_string)) : [];
        $bands_array = !empty($band_string) ? array_map('trim', explode(',', $band_string)) : [];
        $performer_ids = [];

        foreach ($artists_array as $artist_name) {
            if (empty($artist_name)) continue;
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
            if (empty($band_name)) continue;
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

        // 3. Vytvoření ID pro novou píseň
        if (!$song_id) {
            $id_name_part = !empty($artists_array) ? $artists_array[0] : (!empty($bands_array) ? $bands_array[0] : 'neznamy');
            $song_id = create_slug($title . '-' . $id_name_part);
        }

        // 4. Uložení písně (INSERT nebo UPDATE)
        $sql = "INSERT INTO zp_songs (id, title, original_key, capo, lyrics) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), original_key = VALUES(original_key), capo = VALUES(capo), lyrics = VALUES(lyrics)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$song_id, $title, $key, $capo, json_encode($lyrics_data, JSON_UNESCAPED_UNICODE)]);
        
        // 5. Propojení s interprety
        $stmt = $pdo->prepare("DELETE FROM zp_song_performers WHERE song_id = ?");
        $stmt->execute([$song_id]);
        if (!empty($performer_ids)) {
            $stmt = $pdo->prepare("INSERT INTO zp_song_performers (song_id, performer_id) VALUES (?, ?)");
            foreach ($performer_ids as $p_id) {
                $stmt->execute([$song_id, $p_id]);
            }
        }

        $pdo->commit();
        
        // Místo zobrazení zprávy v editoru rovnou přesměrujeme na hotovou písničku
        header('Location: song.php?id=' . urlencode($song_id));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Chyba při ukládání do databáze: " . $e->getMessage();
        $message_type = 'error';
        $form_data = $_POST;
        $form_data['raw_content'] = $_POST['song_content'];
    }
}

$bodyClass = "editor-layout-body"; 
$pageTitle = "Editor písní";
include 'includes/header.php';
?>

<div class="editor-page">
    <form action="editor.php" method="POST" class="editor-main-grid">
        <div class="editor-metadata-column">
            <div class="form-section">
                <h1><?php echo !empty($form_data['id']) ? 'Upravit píseň' : 'Nová píseň'; ?></h1>
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
                <button type="submit" name="save_song" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span class="kb-hint" style="background-color: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); color: white;">Ctrl+S</span>
                    Zpracovat a uložit písničku
                </button>
            </div>

            <div class="form-section editor-help-box" style="margin-top: 2rem; font-size: 0.9rem; background-color: rgba(198, 119, 1, 0.05); border-left: 4px solid var(--primary-color);">
                <h3><span class="material-symbols-outlined" style="vertical-align: middle;">help_outline</span> Nápověda editoru</h3>
                <ul style="padding-left: 1.2rem; margin-top: 0.5rem;">
                    <li><strong>Sloky a Refrény:</strong> Na začátek sloky můžete napsat štítek (např. <code>#1</code>, <code>#R</code>, <code>#Ref</code>). Ten se pak zobrazí v okraji a nebude odsouvat text. Systém také automaticky pozná <code>1.</code>, <code>R:</code> atd.</li>
                    <li><strong>Akordy:</strong> Pište na samostatný řádek nad text. Systém je automaticky pozná a v editoru podbarví <span style="background-color: rgba(198, 119, 1, 0.2); padding: 0 4px; border-radius: 3px;">oranžově</span>.</li>
                    <li><strong>Manuální akordy:</strong> Pokud systém akordy nepozná, začněte řádek tečkou (např. <code>.Fmi C+</code>).</li>
                    <li><strong>Manuální text:</strong> Pokud systém řádek chybně označí jako akordy, začněte jej vykřičníkem (např. <code>!A C E</code>).</li>
                    <li><strong>Sloky:</strong> Oddělujte sloky jedním prázdným řádkem.</li>
                    <li><strong>Zarovnání:</strong> Pro správnou pozici akordů nad textem používejte k odsazení mezery (ne tabulátor).</li>
                    <li><strong>Rychlé mazání (více kurzorů):</strong>
                        <ul style="margin-top: 5px; list-style-type: '→ ';">
                            <li>Označte na 1. řádku ty 2 mezery pomocí <kbd>Shift</kbd> + <kbd>→</kbd>.</li>
                            <li>Pomocí <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>↓</kbd> (nebo <kbd>Alt</kbd> + <kbd>Shift</kbd>) přidejte výběr na další řádky.</li>
                            <li>Smažte jednou klávesou <kbd>Delete</kbd>.</li>
                            <li><em>(Tip: Nepoužívejte Ctrl při pohybu doprava, jinak se vybere celá mezera až k akordu!)</em></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
        <div class="editor-lyrics-column">
            <div class="form-section">
                <fieldset>
                    <legend><h3>Text a akordy</h3></legend>
                    <p>Pro oddělení slok použijte prázdný řádek. Pište akordy na samostatný řádek nad text.</p>
                    <textarea id="song_content" name="song_content"><?php echo htmlspecialchars($form_data['raw_content']); ?></textarea>
                </fieldset>
            </div>
        </div>
        <div class="editor-tools-column">
            <div class="form-section sticky-tools">
                <h3>Vložit štítek</h3>
                <div class="label-buttons">
                    <div class="label-button-group">
                        <button type="button" class="btn-label" data-label="auto-sloka" style="background-color: var(--primary-color); color: white; font-weight: bold; border-color: var(--primary-color);" title="Vložit novou (očíslovanou) sloku [Alt+S]"><span class="kb-hint">Alt+S</span> + Sloka</button>
                        <button type="button" class="btn-label" data-label="cycle-sloka" style="background-color: #6c757d; color: white;" title="Cyklovat mezi již existujícími slokami [Alt+Shift+S]"><span class="kb-hint">⇧S</span> Opak.</button>
                    </div>
                    <div class="label-button-group">
                        <button type="button" class="btn-label" data-label="auto-refren" style="background-color: var(--primary-color); color: white; font-weight: bold; border-color: var(--primary-color);" title="Vložit nový (očíslovaný) refrén [Alt+R]"><span class="kb-hint">Alt+R</span> + Refrén</button>
                        <button type="button" class="btn-label" data-label="cycle-refren" style="background-color: #6c757d; color: white;" title="Cyklovat mezi již existujícími refrény [Alt+Shift+R]"><span class="kb-hint">⇧R</span> Opak.</button>
                    </div>
                    <div class="label-button-group">
                        <button type="button" class="btn-label" data-label="auto-mezi" style="background-color: var(--primary-color); color: white; border-color: var(--primary-color);" title="Vložit mezihru [Alt+M]"><span class="kb-hint">Alt+M</span> + Mezihra</button>
                        <button type="button" class="btn-label" data-label="cycle-mezi" style="background-color: #6c757d; color: white;" title="Cyklovat mezihry [Alt+Shift+M]"><span class="kb-hint">⇧M</span> Opak.</button>
                    </div>
                    <div class="label-button-group">
                        <button type="button" class="btn-label" data-label="Int" title="Vložit Intro [Alt+I]"><span class="kb-hint">Alt+I</span> Intro</button>
                        <button type="button" class="btn-label" data-label="Out" title="Vložit Outro [Alt+O]"><span class="kb-hint">Alt+O</span> Outro</button>
                    </div>
                </div>
                <p style="font-size: 0.8rem; color: #666; margin-top: 1rem;">Klikněte na tlačítko pro označení aktuální sloky.</p>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer-minimal.php'; ?>

<style>
    /* Styly pro CodeMirror - zvýraznění akordů */
    .cm-chord-line {
        background-color: rgba(198, 119, 1, 0.1) !important;
        border-right: 4px solid var(--primary-color);
    }
    body.dark-mode .cm-chord-line {
        background-color: rgba(198, 119, 1, 0.2) !important;
    }

    /* VÝRAZNĚJŠÍ VÝBĚR TEXTU V EDITORU */
    .CodeMirror-selected {
        background-color: rgba(198, 119, 1, 0.35) !important;
    }
    .CodeMirror-focused .CodeMirror-selected {
        background-color: rgba(198, 119, 1, 0.5) !important;
    }
    /* Pro standardní výběr prohlížečem (pokud by se aktivoval) */
    .CodeMirror-line::selection, 
    .CodeMirror-line > span::selection, 
    .CodeMirror-line > span > span::selection { 
        background-color: rgba(198, 119, 1, 0.5) !important; 
    }

    /* Čipové štítky v editoru */
    .cm-stanza-chip {
        display: inline-block;
        background-color: var(--primary-color);
        color: white;
        padding: 0 6px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: bold;
        margin-right: 4px;
        text-transform: uppercase;
    }

    .kb-hint {
        display: inline-block;
        background-color: rgba(0,0,0,0.1);
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 0.7rem;
        margin-right: 4px;
        font-weight: normal;
        border: 1px solid rgba(0,0,0,0.1);
        vertical-align: middle;
    }
    body.dark-mode .kb-hint {
        background-color: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.1);
    }

    .editor-main-grid {
        display: grid;
        grid-template-columns: 500px 1fr 280px; /* ROZŠÍŘENÝ PRAVÝ PANEL */
        gap: 1.5rem;
        max-width: 100%; /* ROZTAŽENO NA CELOU ŠÍŘKU */
        margin: 0;
        padding: 0 1.5rem;
    }

    .label-button-group {
        display: grid;
        grid-template-columns: 1fr 100px;
        gap: 5px;
    }

    .btn-label {
        background: white;
        border: 1px solid var(--border-color);
        color: var(--text-color);
        padding: 8px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.2s;
        text-align: center;
        width: 100%;
    }

    .btn-label:hover {
        background: #f8f9fa;
        border-color: var(--primary-color);
    }

    .sticky-tools {
        position: sticky;
        top: 80px;
    }

    @media (max-width: 1200px) {
        .editor-main-grid {
            grid-template-columns: 1fr;
        }
        .editor-tools-column {
            order: -1; /* Nástroje nahoře na mobilu */
        }
        .label-buttons {
            flex-direction: row;
            flex-wrap: wrap;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Logika pro Tmavý / Světlý režim (Synchronizace s body) ---
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    const updateThemeClass = () => {
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
    };
    updateThemeClass();
    themeToggleButton?.addEventListener('click', () => {
        setTimeout(updateThemeClass, 10); // Malá prodleva po kliknutí
    });

    const songTextarea = document.getElementById('song_content');
    if (songTextarea) {
        const editor = CodeMirror.fromTextArea(songTextarea, {
            lineNumbers: true, 
            lineWrapping: true, 
            mode: 'text/plain', 
            theme: 'material-darker',
            rulers: [{ column: 70, color: "#555" }, { column: 72, color: "#722" }],
            extraKeys: {
                "Shift-Alt-Down": function(cm) {
                    var sels = cm.listSelections();
                    var last = sels[sels.length - 1];
                    if (last.head.line < cm.lineCount() - 1) {
                        cm.addSelection(
                            {line: last.anchor.line + 1, ch: last.anchor.ch},
                            {line: last.head.line + 1, ch: last.head.ch}
                        );
                    }
                },
                "Shift-Alt-Up": function(cm) {
                    var sels = cm.listSelections();
                    var last = sels[0]; // For Up, we should probably add above the first one
                    if (last.head.line > 0) {
                        cm.addSelection(
                            {line: last.anchor.line - 1, ch: last.anchor.ch},
                            {line: last.head.line - 1, ch: last.head.ch}
                        );
                    }
                },
                "Shift-Ctrl-Down": function(cm) {
                    var sels = cm.listSelections();
                    var last = sels[sels.length - 1];
                    if (last.head.line < cm.lineCount() - 1) {
                        cm.addSelection(
                            {line: last.anchor.line + 1, ch: last.anchor.ch},
                            {line: last.head.line + 1, ch: last.head.ch}
                        );
                    }
                },
                "Shift-Ctrl-Up": function(cm) {
                    var sels = cm.listSelections();
                    var last = sels[0];
                    if (last.head.line > 0) {
                        cm.addSelection(
                            {line: last.anchor.line - 1, ch: last.anchor.ch},
                            {line: last.head.line - 1, ch: last.head.ch}
                        );
                    }
                },
                "Ctrl-S": function(cm) {
                    var form = cm.getTextArea().closest('form');
                    if (form) {
                        cm.save();
                        form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
                        // Pro jistotu přímo zavoláme submit() pokud dispatchEvent nestačí
                        if (!form.noValidate) form.submit();
                    }
                }
            }
        });
        editor.setSize(null, '100%');

        const chordRegex = /^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,\-\+\*]+$/i;

        // Funkce pro vizuální dekoraci štítků (#label)
        function updateChipDecorations() {
            editor.operation(() => {
                // Odstraníme staré markery
                editor.getAllMarks().forEach(marker => {
                    if (marker.className === 'cm-stanza-chip') marker.clear();
                });

                for (let i = 0; i < editor.lineCount(); i++) {
                    const text = editor.getLine(i);
                    const match = text.match(/^#[a-z0-9]+/i);
                    if (match) {
                        editor.markText(
                            {line: i, ch: 0},
                            {line: i, ch: match[0].length},
                            {
                                className: 'cm-stanza-chip',
                                atomic: true,
                                handleMouseEvents: true
                            }
                        );
                    }
                }
            });
        }

        function updateHighlights() {
            editor.operation(() => {
                updateChipDecorations();

                for (let i = 0; i < editor.lineCount(); i++) {
                    let lineText = editor.getLine(i).trim();
                    
                    if (lineText.startsWith('#')) {
                        lineText = lineText.replace(/^#[a-z0-9]+\s*/i, '').trim();
                    } else if (lineText.match(/^(([0-9]+\.)|(R:)|(Ref:)|(Mezi:))\s*/i)) {
                        lineText = lineText.replace(/^(([0-9]+\.)|(R:)|(Ref:)|(Mezi:))\s*/i, '').trim();
                    }

                    const isForcedChord = lineText.startsWith('.');
                    const isForcedText = lineText.startsWith('!');
                    const isAutoChord = (lineText !== "" && !isForcedText && chordRegex.test(lineText));
                    
                    if (isForcedChord || isAutoChord) {
                        editor.addLineClass(i, 'background', 'cm-chord-line');
                    } else {
                        editor.removeLineClass(i, 'background', 'cm-chord-line');
                    }
                }
            });
        }

        // Logika pro tlačítka štítků (Smart Cycling)
        const handleLabelButtonClick = (btn) => {
            let label = btn.dataset.label;
            const content = editor.getValue();
            const lines = content.split('\n');
            const cur = editor.getCursor();
            let lineIndex = cur.line;
            const currentRawLine = editor.getLine(lineIndex);
            const isLineEmpty = currentRawLine.trim() === "";

            if (label === 'auto-sloka') {
                const existingVerses = [];
                lines.forEach(line => {
                    const m = line.trim().match(/^#([0-9]+)/);
                    if (m) existingVerses.push(parseInt(m[1]));
                });
                let maxNum = existingVerses.length > 0 ? Math.max(...existingVerses) : 0;
                label = (maxNum + 1).toString();
            } else if (label === 'cycle-sloka') {
                const existingVerses = [];
                lines.forEach(line => {
                    const m = line.trim().match(/^#([0-9]+)/);
                    if (m) existingVerses.push(parseInt(m[1]));
                });
                const uniqueVerses = [...new Set(existingVerses)].sort((a,b) => a-b);
                if (uniqueVerses.length === 0) {
                    label = "1";
                } else {
                    const currentMatch = currentRawLine.trim().match(/^#([0-9]+)/);
                    let nextVal = uniqueVerses[0];
                    if (currentMatch) {
                        const curVal = parseInt(currentMatch[1]);
                        const idx = uniqueVerses.indexOf(curVal);
                        nextVal = uniqueVerses[(idx + 1) % uniqueVerses.length];
                    }
                    label = nextVal.toString();
                }
            } else if (label === 'auto-refren') {
                const existingRefs = [];
                lines.forEach(line => {
                    const m = line.trim().match(/^#R([0-9]*)/i);
                    if (m) existingRefs.push(m[1] === "" ? 1 : parseInt(m[1]));
                });
                let maxNum = existingRefs.length > 0 ? Math.max(...existingRefs) : 0;
                label = (maxNum === 0) ? 'R' : 'R' + (maxNum + 1);
            } else if (label === 'cycle-refren') {
                const existingRefs = [];
                lines.forEach(line => {
                    const m = line.trim().match(/^#R([0-9]*)/i);
                    if (m) existingRefs.push(m[1] === "" ? 1 : parseInt(m[1]));
                });
                const uniqueRefs = [...new Set(existingRefs)].sort((a,b) => a-b);
                if (uniqueRefs.length === 0) {
                    label = "R";
                } else {
                    const currentMatch = currentRawLine.trim().match(/^#R([0-9]*)/i);
                    let nextVal = uniqueRefs[0];
                    if (currentMatch) {
                        const curVal = currentMatch[1] === "" ? 1 : parseInt(currentMatch[1]);
                        const idx = uniqueRefs.indexOf(curVal);
                        nextVal = uniqueRefs[(idx + 1) % uniqueRefs.length];
                    }
                    label = (nextVal === 1) ? 'R' : 'R' + nextVal;
                }
            } else if (label === 'auto-mezi') {
                const existingMezi = [];
                lines.forEach(line => {
                    const m = line.trim().match(/^#Mezi([0-9]*)/i);
                    if (m) existingMezi.push(m[1] === "" ? 1 : parseInt(m[1]));
                });
                let maxNum = existingMezi.length > 0 ? Math.max(...existingMezi) : 0;
                label = (maxNum === 0) ? 'Mezi' : 'Mezi' + (maxNum + 1);
            } else if (label === 'cycle-mezi') {
                const existingMezi = [];
                lines.forEach(line => {
                    const m = line.trim().match(/^#Mezi([0-9]*)/i);
                    if (m) existingMezi.push(m[1] === "" ? 1 : parseInt(m[1]));
                });
                const uniqueMezi = [...new Set(existingMezi)].sort((a,b) => a-b);
                if (uniqueMezi.length === 0) {
                    label = "Mezi";
                } else {
                    const currentMatch = currentRawLine.trim().match(/^#Mezi([0-9]*)/i);
                    let nextVal = uniqueMezi[0];
                    if (currentMatch) {
                        const curVal = currentMatch[1] === "" ? 1 : parseInt(currentMatch[1]);
                        const idx = uniqueMezi.indexOf(curVal);
                        nextVal = uniqueMezi[(idx + 1) % uniqueMezi.length];
                    }
                    label = (nextVal === 1) ? 'Mezi' : 'Mezi' + nextVal;
                }
            }

            if (currentRawLine.trim() !== "") {
                let tempIdx = lineIndex;
                while (tempIdx > 0 && editor.getLine(tempIdx).trim() === "") tempIdx--;
                while (tempIdx > 0 && editor.getLine(tempIdx - 1).trim() !== "") tempIdx--;
                lineIndex = tempIdx;
            }

            const lineText = editor.getLine(lineIndex);
            const hasLabel = lineText.startsWith('#');
            const hasAutoLabel = lineText.match(/^(([0-9]+\.)|(R:)|(Ref:)|(Mezi:))/i);

            if (hasLabel) {
                const newText = lineText.replace(/^#[a-z0-9]+\s*/i, '#' + label + ' ');
                editor.replaceRange(newText, {line: lineIndex, ch: 0}, {line: lineIndex, ch: lineText.length});
            } else if (hasAutoLabel) {
                const newText = lineText.replace(/^(([0-9]+\.)|(R:)|(Ref:)|(Mezi:))\s*/i, '#' + label + ' ');
                editor.replaceRange(newText, {line: lineIndex, ch: 0}, {line: lineIndex, ch: lineText.length});
            } else {
                editor.replaceRange('#' + label + ' ', {line: lineIndex, ch: 0});
            }
            editor.focus();
        };

        document.querySelectorAll('.btn-label').forEach(btn => {
            btn.addEventListener('click', () => handleLabelButtonClick(btn));
        });

        // Keyboard Shortcuts
        window.addEventListener('keydown', (e) => {
            if (e.altKey) {
                let targetLabel = null;
                const key = e.key.toLowerCase();
                
                if (key === 's') {
                    targetLabel = e.shiftKey ? 'cycle-sloka' : 'auto-sloka';
                } else if (key === 'r') {
                    targetLabel = e.shiftKey ? 'cycle-refren' : 'auto-refren';
                } else if (key === 'm') {
                    targetLabel = e.shiftKey ? 'cycle-mezi' : 'auto-mezi';
                } else if (key === 'i') {
                    targetLabel = 'Int';
                } else if (key === 'o') {
                    targetLabel = 'Out';
                }

                if (targetLabel) {
                    e.preventDefault();
                    const btn = document.querySelector(`.btn-label[data-label="${targetLabel}"]`);
                    if (btn) handleLabelButtonClick(btn);
                }
            }
            
            // Global Save Shortcut (Ctrl + S)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                const saveBtn = document.querySelector('button[name="save_song"]');
                if (saveBtn) saveBtn.click();
            }
        });

        // Aktualizujeme při změně i při načtení
        editor.on('change', updateHighlights);
        
        // Okamžitá aktualizace a refresh
        updateHighlights();
        setTimeout(() => {
            updateHighlights();
            editor.refresh();
        }, 100);
        setTimeout(() => {
            updateHighlights();
            editor.refresh();
        }, 500);

        const form = songTextarea.closest('form');
        form.addEventListener('submit', function() {
            editor.save();
        });
    }
});
</script>
