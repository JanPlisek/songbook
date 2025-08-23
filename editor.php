<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once 'includes/functions.php';

if (!is_user_logged_in()) { header('Location: login.php'); exit; }

// === PŘIDÁNÍ KNIHOVNY CROPPER.JS (začátek) ===
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<?php
// === PŘIDÁNÍ KNIHOVNY CROPPER.JS (konec) ===

$pageTitle = "Editor písní";
include 'includes/header.php';

$message = ''; 
$message_type = 'success';
$form_data = ['title' => '', 'artist' => '', 'band' => '', 'key' => '', 'capo' => 0, 'raw_content' => ''];

// Načtení existující písně k editaci
if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $song_id = $_GET['id']; 
    if (strpos($song_id, '/') !== false || strpos($song_id, '\\') !== false || strpos($song_id, '.') !== false) { die('Neplatné ID písně.'); }
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
                $is_first_block = true;
                foreach ($song_data['lyrics'] as $line) {
                    if (isset($line['block_start']) && $line['block_start'] && !$is_first_block) {
                        $raw_content .= "\n"; // Přidáme prázdný řádek mezi bloky
                    }
                    if (isset($line['chords']) && $line['chords'] !== "") { $raw_content .= $line['chords'] . "\n"; }
                    if (isset($line['text']) && $line['text'] !== "") { $raw_content .= $line['text'] . "\n"; }
                    $is_first_block = false;
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
    // ... (metadata processing remains the same) ...
    $title = $_POST['title']; $artist_string = $_POST['artist']; $band_string = $_POST['band']; $key = $_POST['key']; $capo = (int)$_POST['capo']; 
    $raw_content = $_POST['song_content'];
    
    // === VYLEPŠENÝ PARSER TEXTU S ROZPOZNÁVÁNÍM BLOKŮ ===
    $lines = explode("\n", str_replace("\r", "", $raw_content));
    $lyrics_data = []; 
    $chord_line_regex = '/^[A-HMIsc\s#b\/susmajdimaugadd0-9()\[\]:.,-]+$/i';
    $next_line_is_block_start = true; // První řádek je vždy začátek bloku

    for ($i = 0; $i < count($lines); $i++) {
        $current_line = rtrim($lines[$i]);

        if (trim($current_line) === "") {
            $next_line_is_block_start = true;
            continue; // Přeskočíme prázdný řádek, ale poznačíme si, že další řádek je začátek
        }
        
        $line_object = [];
        if ($next_line_is_block_start) {
            $line_object['block_start'] = true;
            $next_line_is_block_start = false;
        }

        $is_current_line_chord = preg_match($chord_line_regex, trim($current_line));
        
        if ($is_current_line_chord) {
            $line_object['chords'] = $current_line;
            $next_line_index = $i + 1;
            if ($next_line_index < count($lines) && !preg_match($chord_line_regex, trim($lines[$next_line_index]))) {
                $line_object['text'] = rtrim($lines[$next_line_index]);
                $i++; // Posuneme index, protože jsme zpracovali dva řádky
            } else {
                $line_object['text'] = "";
            }
        } else { 
            $line_object['chords'] = "";
            $line_object['text'] = $current_line; 
        }
        $lyrics_data[] = $line_object;
    }
    // === KONEC PARSERU ===

    $artists_array = !empty($artist_string) ? array_map('trim', explode(',', $artist_string)) : [];
    $bands_array = !empty($band_string) ? array_map('trim', explode(',', $band_string)) : [];
    // === NOVÝ BLOK PRO ZPRACOVÁNÍ OBRÁZKU ===
    
    // === KONEC BLOKU PRO OBRÁZEK ===
    $id_name = !empty($artists_array) ? $artists_array[0] : (!empty($bands_array) ? $bands_array[0] : 'neznamy');
    $id = create_slug($title . '-' . $id_name);
    $filename = 'songs/' . $id . '.json';
    $final_json_data = [ 'id' => $id, 'title' => $title, 'artist' => $artists_array, 'band' => $bands_array, 'originalKey' => $key, 'capo' => $capo, 'lyrics' => $lyrics_data ];
    file_put_contents($filename, json_encode($final_json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // ... (updating _masterlist.json remains the same) ...
    $master_list_file = 'songs/_masterlist.json';
    $master_list = file_exists($master_list_file) ? json_decode(file_get_contents($master_list_file), true) ?? [] : [];
    $exists_index = -1; 
    foreach($master_list as $index => $song) { if ($song['id'] === $id) { $exists_index = $index; break; } }
    $new_entry = ['id' => $id, 'title' => $title, 'artist' => $artists_array, 'band' => $bands_array];
    if ($exists_index !== -1) { $master_list[$exists_index] = $new_entry; } else { $master_list[] = $new_entry; }
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
            
            <label for="artist_image">Obrázek interpreta (nepovinné):</label>
            <input type="file" id="artist_image" name="artist_image" accept="image/jpeg, image/png, image/webp">
            
            <label for="key">Originální tónina:</label>
            <input type="text" id="key" name="key" value="<?php echo htmlspecialchars($form_data['key']); ?>" placeholder="např. G nebo Am">
            <label for="capo">Pozice kapodastru:</label>
            <input type="number" id="capo" name="capo" value="<?php echo htmlspecialchars($form_data['capo']); ?>">
        </fieldset>
        <fieldset>
            <legend><h3>Text a akordy</h3></legend>
            <p style="font-size: 0.9em; color: #666;">Pro oddělení slok použijte prázdný řádek. Pište akordy na samostatný řádek nad text.   -----------------------------|</p>
            <textarea id="song_content" name="song_content" required><?php echo htmlspecialchars($form_data['raw_content']); ?></textarea>
        </fieldset>
        <button type="submit" name="save_song">Zpracovat a uložit písničku</button>
    </form>
</div>

<div id="cropper-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Oříznout obrázek interpreta</h3>
        <p style="margin-bottom: 1rem;">Pomocí rámečku vyberte požadovaný výřez a potvrďte.</p>

        <div id="cropper-container">
            <img id="image-to-crop" src="" alt="Náhled pro ořezání">
        </div>

        <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" id="crop-and-upload-btn" class="btn" style="background-color: #28a745;">Oříznout a nahrát</button>
            <small id="upload-status"></small>
            <button type="button" id="btn-close-cropper-modal" class="btn-close-modal">Zrušit</button>
        </div>
    </div>
</div>


<?php include 'includes/footer.php'; ?>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Prvky pro ořezávání ---
    const fileInput = document.getElementById('artist_image');
    const image = document.getElementById('image-to-crop');
    const cropperModal = document.getElementById('cropper-modal');
    const uploadButton = document.getElementById('crop-and-upload-btn');
    const closeButton = document.getElementById('btn-close-cropper-modal');
    const uploadStatus = document.getElementById('upload-status');
    const artistInput = document.getElementById('artist');
    const bandInput = document.getElementById('band'); // Přidali jsme odkaz na pole s kapelou
    
    let cropper; 

    // --- Zobrazí modal a inicializuje cropper (beze změny) ---
    fileInput.addEventListener('change', (event) => {
        const files = event.target.files;
        if (files && files.length > 0) {
            const reader = new FileReader();
            reader.onload = (e) => {
                image.src = e.target.result;
                cropperModal.style.display = 'flex';
                if (cropper) { cropper.destroy(); }
                cropper = new Cropper(image, {
                    aspectRatio: 25 / 18, viewMode: 1, dragMode: 'move',
                    autoCropArea: 0.9, responsive: true, background: false,
                });
            };
            reader.readAsDataURL(files[0]);
        }
    });

    // --- Skryje modal (beze změny) ---
    const closeModal = () => {
        cropperModal.style.display = 'none';
        if (cropper) { cropper.destroy(); }
        fileInput.value = '';
        uploadStatus.textContent = '';
    };
    closeButton.addEventListener('click', closeModal);

    // --- Ořízne a nahraje obrázek (VYLEPŠENÁ LOGIKA) ---
    uploadButton.addEventListener('click', () => {
        if (!cropper) {
            alert('Nejprve vyberte obrázek.');
            return;
        }

        // === ZMĚNA ZDE: Inteligentní výběr jména pro soubor ===
        let nameForSlug = '';
        const artistValue = artistInput.value.trim();
        const bandValue = bandInput.value.trim();

        if (artistValue !== '') {
            // Prioritu má autor, vezmeme jen prvního v pořadí
            nameForSlug = artistValue.split(',')[0].trim();
        } else if (bandValue !== '') {
            // Pokud není autor, vezmeme kapelu (opět jen první)
            nameForSlug = bandValue.split(',')[0].trim();
        }

        if (nameForSlug === '') {
            alert('Vyplňte prosím alespoň pole "Autor / Osoba" nebo "Kapela", aby se obrázek mohl správně pojmenovat.');
            return;
        }
        // === KONEC ZMĚNY ===

        uploadStatus.textContent = 'Nahrávám...';
        
        cropper.getCroppedCanvas({ width: 500, height: 500 }).toBlob((blob) => {
            const formData = new FormData();
            formData.append('cropped_image', blob, 'image.png');
            formData.append('artist_name', nameForSlug); // Použijeme chytře vybrané jméno

            fetch('upload_image.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    uploadStatus.style.color = 'green';
                    uploadStatus.textContent = 'Úspěšně nahráno!';
                    setTimeout(closeModal, 1500);
                } else {
                    throw new Error(data.message || 'Neznámá chyba.');
                }
            })
            .catch(error => {
                uploadStatus.style.color = 'red';
                uploadStatus.textContent = 'Chyba: ' + error.message;
            });
        }, 'image/png');
    });
});
</script>