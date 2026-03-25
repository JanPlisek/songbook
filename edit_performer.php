<?php require_once 'includes/gatekeeper.php'; ?>
<?php
// soubor: edit_performer.php

// === INICIALIZAČNÍ BLOK ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Tato stránka je pouze pro přihlášené uživatele
if (!is_user_logged_in()) {
    header('Location: login.php');
    exit;
}
// === KONEC BLOKU ===


// Načteme si knihovny pro ořezávač fotek
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<?php

$pageTitle = "Upravit interpreta";
include 'includes/header.php';

$message = '';
$message_type = 'info';
$form_data = ['id' => '', 'name' => '', 'info' => ''];
$performer_id = $_GET['id'] ?? $_POST['id'] ?? null;

// Zpracování uložených dat z formuláře (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $name = $_POST['name'];
    $info = $_POST['info'];

    if ($performer_id && !empty($name)) {
        $stmt = $pdo->prepare("UPDATE zp_performers SET name = ?, info = ? WHERE id = ?");
        if ($stmt->execute([$name, $info, $performer_id])) {
            $message = "Profil interpreta byl úspěšně aktualizován.";
            $message_type = 'success';
        } else {
            $message = "Nepodařilo se aktualizovat profil.";
            $message_type = 'error';
        }
    } else {
        $message = "Chybí potřebná data (ID nebo jméno).";
        $message_type = 'error';
    }
}

// Načtení dat pro předvyplnění formuláře (GET)
if ($performer_id) {
    $stmt = $pdo->prepare("SELECT * FROM zp_performers WHERE id = ?");
    $stmt->execute([$performer_id]);
    $performer = $stmt->fetch();
    if ($performer) {
        $form_data = $performer;
    } else {
        echo "<div class='container message error'>Interpret s daným ID nebyl nalezen.</div>";
        include 'includes/footer.php';
        exit;
    }
} else {
    echo "<div class='container message error'>Nebylo zadáno ID interpreta.</div>";
    include 'includes/footer.php';
    exit;
}
?>

<div class="container editor-page">
    <h1>Upravit interpreta: <?php echo htmlspecialchars($form_data['name']); ?></h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="form-section">
        <form action="edit_performer.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($form_data['id']); ?>">
            
            <fieldset>
                <legend><h3>Základní údaje</h3></legend>
                <label for="name">Jméno / Název kapely:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>

                <label for="info">Informace o interpretovi (může obsahovat HTML):</label>
                <textarea id="info" name="info" rows="10"><?php echo htmlspecialchars($form_data['info']); ?></textarea>
                
                <button type="submit" name="save_profile">Uložit změny</button>
            </fieldset>
        </form>
    </div>

    <div class="form-section">
    <fieldset>
        <legend><h3>Profilový obrázek</h3></legend>

        <div id="image-editor-container">
            <p>Aktuální obrázek:</p>
            <img src="<?php echo htmlspecialchars(get_artist_image_path($form_data['name'])); ?>?t=<?php echo time(); ?>" class="current-performer-photo" alt="Aktuální fotka">

            <p style="margin-top: 1.5rem;"><strong>Nahrát nový:</strong></p>
            
            <div id="cropper-container-inline">
                <div id="cropper-placeholder">
                    <span class="material-symbols-outlined">add_photo_alternate</span>
                    <p>Vložte obrázek ze schránky (Ctrl+V)<br>nebo</p>
                    <button type="button" id="btn-select-file" class="btn-secondary">Vyberte soubor</button>
                </div>
                <img id="image-to-crop-inline" src="" alt="">
            </div>

            <input type="file" id="image-input" accept="image/jpeg, image/png, image/webp" style="display: none;">

            <div class="image-editor-actions">
                <button type="button" id="btn-crop-and-upload" class="btn">Oříznout a nahrát</button>
                <small id="upload-status"></small>
            </div>
        </div>
        
    </fieldset>
</div>

</div>


<div id="cropper-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Oříznout obrázek interpreta</h3>
        <p style="margin-bottom: 1rem;">Pomocí rámečku vyberte požadovaný výřez a potvrďte.</p>
        <div id="cropper-container"><img id="image-to-crop" src="" alt="Náhled pro ořezání"></div>
        <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" id="crop-and-upload-btn" class="btn" style="background-color: #28a745;">Oříznout a nahrát</button>
            <small id="upload-status"></small>
            <button type="button" id="btn-close-cropper-modal" class="btn-close-modal">Zrušit</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Definice všech HTML prvků, se kterými pracujeme
    const nameInput = document.getElementById('name');
    const imageInput = document.getElementById('image-input');
    const btnSelectFile = document.getElementById('btn-select-file');
    const container = document.getElementById('cropper-container-inline');
    const placeholder = document.getElementById('cropper-placeholder');
    const imageToCrop = document.getElementById('image-to-crop-inline');
    const btnCropAndUpload = document.getElementById('btn-crop-and-upload');
    const uploadStatus = document.getElementById('upload-status');
    
    let cropper; // Proměnná pro instanci ořezávače

    // Funkce pro inicializaci ořezávače
    function initCropper(imageSrc) {
        // Skryjeme placeholder a zobrazíme obrázek
        placeholder.style.display = 'none';
        imageToCrop.style.opacity = 1;
        imageToCrop.src = imageSrc;

        // Pokud už existuje starý ořezávač, zničíme ho
        if (cropper) {
            cropper.destroy();
        }
        
        // Vytvoříme novou instanci ořezávače
        cropper = new Cropper(imageToCrop, {
            aspectRatio: 250 / 180,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            responsive: true,
            background: false,
        });
    }

    // --- ZPRACOVÁNÍ VSTUPU ---

    // 1. Kliknutí na tlačítko "Vyberte soubor"
    btnSelectFile.addEventListener('click', () => {
        imageInput.click(); // Programově klikneme na skrytý file input
    });

    // 2. Změna ve file inputu (uživatel vybral soubor)
    imageInput.addEventListener('change', (event) => {
        const files = event.target.files;
        if (files && files.length > 0) {
            const reader = new FileReader();
            reader.onload = (e) => {
                initCropper(e.target.result);
            };
            reader.readAsDataURL(files[0]);
        }
    });

    // 3. Vložení ze schránky (Ctrl+V)
    window.addEventListener('paste', event => {
        const items = event.clipboardData.files;
        if (items && items.length > 0) {
            // Hledáme, zda je mezi vloženými soubory obrázek
            const imageFile = Array.from(items).find(file => file.type.startsWith('image/'));
            if (imageFile) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    initCropper(e.target.result);
                };
                reader.readAsDataURL(imageFile);
            }
        }
    });

    // --- ULOŽENÍ OBRÁZKU ---
    btnCropAndUpload.addEventListener('click', () => {
        if (!cropper) {
            alert('Nejprve vyberte nebo vložte obrázek.');
            return;
        }

        const nameForSlug = nameInput.value.trim();
        if (nameForSlug === '') {
            alert('Vyplňte prosím jméno interpreta, aby se obrázek mohl správně pojmenovat.');
            return;
        }

        uploadStatus.textContent = 'Nahrávám...';
        
        cropper.getCroppedCanvas({ width: 500, height: 360 }).toBlob((blob) => { // Větší rozlišení pro kvalitu
            const formData = new FormData();
            formData.append('cropped_image', blob, 'image.png');
            formData.append('artist_name', nameForSlug);

            fetch('upload_image.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        uploadStatus.style.color = 'green';
                        uploadStatus.textContent = 'Úspěšně nahráno! Stránka se znovu načte.';
                        setTimeout(() => { location.reload(); }, 1500);
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