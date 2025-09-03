<?php require_once 'includes/gatekeeper.php'; ?>
<?php
// === INICIALIZAČNÍ BLOK (NOVÁ VERZE) ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Připojíme databázi a funkce
require_once 'includes/database.php';
require_once 'includes/functions.php';
// === KONEC BLOKU ===

$pageTitle = "Rejstřík písní";
include 'includes/header.php';

// --- Načtení počtu nevyřízených požadavků (jen pro admina) ---
$pending_requests_count = 0;
if (is_admin()) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM zp_requests WHERE is_completed = 0");
    $pending_requests_count = $stmt->fetchColumn();
}

$all_songs = [];
$grouped_songs = [];

// Krok 1: Načteme VŠECHNY písničky jedním SQL dotazem
// Databáze nám je rovnou i seřadí podle českých pravidel (COLLATE utf8mb4_czech_ci)
$sql = "SELECT 
            s.id, 
            s.title,
            GROUP_CONCAT(p.name SEPARATOR ', ') as performers,
            GROUP_CONCAT(p.id SEPARATOR ',') as performer_ids
        FROM 
            zp_songs s
        LEFT JOIN 
            zp_song_performers sp ON s.id = sp.song_id
        LEFT JOIN 
            zp_performers p ON sp.performer_id = p.id
        GROUP BY 
            s.id
        ORDER BY 
            s.title COLLATE utf8mb4_czech_ci";

$stmt = $pdo->query($sql);
$all_songs = $stmt->fetchAll();


// Krok 2: Nyní, když máme data bleskově načtená, seskupíme je podle písmene
// Tato logika zůstává v PHP, ale pracuje s daty z databáze
foreach ($all_songs as $song) {
    $first_letter = get_czech_first_letter($song['title']);
    $grouped_songs[$first_letter][] = $song;
}
?>

<div class="list-page">
    <div class="song-header">
        <div class="left-group">
            <h1>Seznam písní</h1>
            
            <?php // TATO ČÁST JE PRO BĚŽNÉ UŽIVATELE
                // Zobrazí se vedle nadpisu a nebude se na mobilu skrývat.
            if (!is_admin() && is_user_logged_in()): ?>
                <a href="#" id="btn-show-request-modal" class="requests-link user-request-link">Požádat o píseň</a>
            <?php endif; ?>
        </div>

        <div class="right-group">
            <span>Celkem písní: <strong><?php echo count($all_songs); ?></strong></span>
            
            <?php // TATO ČÁST ZŮSTÁVÁ BEZE ZMĚNY PRO ADMINA
            if (is_admin()): ?>
                <?php if ($pending_requests_count > 0): ?>
                    <span> | </span>
                    <a href="requests.php" class="requests-link">
                        Nové požadavky (<?php echo $pending_requests_count; ?>)
                    </a>
                <?php endif; ?>
                <span> | </span><a href="editor.php">Přidat novou píseň</a>
            <?php endif; ?>
        </div>
    </div> 

    <div class="filter-box">
        <input type="text" id="text-filter-input" placeholder="Filtrovat podle názvu nebo interpreta...">
    </div>

    <?php if (!empty($grouped_songs)): ?>
        <div class="alphabet-filter">
            <button class="alpha-btn active" data-letter="all">Vše</button>
            <?php
                $letters = array_keys($grouped_songs);
                // Řazení písmen v abecedním filtru (zůstává stejné)
                if (class_exists('Collator')) {
                    $collator = new Collator('cs_CZ');
                    $collator->sort($letters);
                } else {
                    sort($letters, SORT_STRING);
                }
                foreach ($letters as $letter):
            ?>
                <button class="alpha-btn" data-letter="<?php echo htmlspecialchars($letter); ?>"><?php echo htmlspecialchars($letter); ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div id="song-list-grouped">
        <?php if (empty($grouped_songs)): ?>
            <p>V seznamu zatím nejsou žádné písničky.</p>
        <?php else: ?>
            <?php foreach ($grouped_songs as $letter => $songs_in_group): ?>
                <div class="song-group" data-group-letter="<?php echo htmlspecialchars($letter); ?>">
                    <h2 class="letter-heading"><?php echo htmlspecialchars($letter); ?></h2>
                    <div class="song-list">
                        <?php foreach ($songs_in_group as $song): ?>
                            <div class="song-list-item-wrapper" data-song-id="<?php echo htmlspecialchars($song['id']); ?>">
    
                                <div class="song-list-item">
                                    <a href="song.php?id=<?php echo htmlspecialchars($song['id']); ?>" class="song-title-link">
                                        <span class="song-title"><?php echo htmlspecialchars($song['title']); ?></span>
                                    </a>
                                    
                                    <span class="song-artist">
                                        <?php
                                        $performer_names = explode(', ', $song['performers'] ?? '');
                                        $performer_ids = explode(',', $song['performer_ids'] ?? '');

                                        foreach ($performer_names as $index => $name) {
                                            if (!empty(trim($name))) {
                                                $id = $performer_ids[$index] ?? 0;
                                                echo '<a href="#" class="performer-link js-show-performer-modal" data-performer-id="' . htmlspecialchars($id) . '" data-performer-name="' . htmlspecialchars($name) . '">' . htmlspecialchars($name) . '</a>';
                                            }
                                        }
                                        ?>
                                    </span>
                                </div>

                                <?php if (is_admin()): ?>
                                    <a href="editor.php?id=<?php echo htmlspecialchars($song['id']); ?>" class="edit-link" title="Upravit píseň">
                                        <span class="material-symbols-outlined">music_history</span>
                                    </a>
                                    <a href="#" class="delete-link" title="Smazat píseň">
                                        <span class="material-symbols-outlined">delete</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Celý JavaScript blok pro filtrování a mazání zůstává beze změny,
// protože pracuje s HTML strukturou, která se nezměnila.
document.addEventListener('DOMContentLoaded', function() {
    const textFilterInput = document.getElementById('text-filter-input');
    const alphaButtons = document.querySelectorAll('.alpha-btn');
    const songGroups = document.querySelectorAll('.song-group');
    const songListContainer = document.getElementById('song-list-grouped');

    // --- Logika pro textový filtr ---
    textFilterInput.addEventListener('input', function() {
        const filterText = this.value.toLowerCase();
        
        alphaButtons.forEach(btn => btn.classList.remove('active'));
        document.querySelector('.alpha-btn[data-letter="all"]').classList.add('active');

        songGroups.forEach(group => {
            let groupHasVisibleSongs = false;
            const songsInGroup = group.querySelectorAll('.song-list-item-wrapper');
            
            songsInGroup.forEach(wrapper => {
                const itemText = wrapper.textContent.toLowerCase();
                if (itemText.includes(filterText)) {
                    wrapper.style.display = 'flex';
                    groupHasVisibleSongs = true;
                } else {
                    wrapper.style.display = 'none';
                }
            });

            if (groupHasVisibleSongs) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
    });

    // --- Logika pro abecední filtr ---
    alphaButtons.forEach(button => {
        button.addEventListener('click', function() {
            const selectedLetter = this.dataset.letter;
            textFilterInput.value = '';
            alphaButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            songGroups.forEach(group => {
                group.querySelectorAll('.song-list-item-wrapper').forEach(item => item.style.display = 'flex');

                if (selectedLetter === 'all' || group.dataset.groupLetter === selectedLetter) {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    });

    // --- Logika pro mazání písní (zůstává stejná) ---
    songListContainer.addEventListener('click', function(event) {
        const deleteLink = event.target.closest('.delete-link');
        if (!deleteLink) {
            return;
        }
        
        event.preventDefault();

        const songWrapper = deleteLink.closest('.song-list-item-wrapper');
        const songId = songWrapper.dataset.songId;
        const songTitle = songWrapper.querySelector('.song-title').textContent;

        if (confirm(`Opravdu si přejete trvale smazat píseň "${songTitle}"?`)) {
            fetch('delete_song.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: songId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    songWrapper.style.transition = 'opacity 0.5s ease';
                    songWrapper.style.opacity = '0';
                    setTimeout(() => {
                        songWrapper.remove();
                    }, 500);
                } else {
                    alert('Chyba při mazání: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Došlo k chybě:', error);
                alert('Došlo k technické chybě. Zkuste to prosím později.');
            });
        }
    });
});
</script>