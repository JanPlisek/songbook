<?php
// === INICIALIZAČNÍ BLOK (přidat) ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'includes/functions.php';
// === KONEC BLOKU ===

$pageTitle = "Interpreti";
include 'includes/header.php';

// Zjistíme, jaký typ řazení si uživatel vybral (výchozí je podle jména)
$sort_mode = $_GET['sort'] ?? 'firstname';

// Načtení a zpracování dat
$songs_dir = 'songs/';
$all_songs = [];
if (is_dir($songs_dir)) {
    $files = scandir($songs_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'json' && $file !== '_masterlist.json') {
            $song_data = json_decode(file_get_contents($songs_dir . $file), true);
            if ($song_data) { $all_songs[] = $song_data; }
        }
    }
}

// Sestavíme JEDEN seznam všech performerů (osob i kapel)
$performers = [];
foreach ($all_songs as $song) {
    $artists = $song['artist'] ?? [];
    $bands = $song['band'] ?? [];
    $all_performers = array_merge(
        is_array($artists) ? $artists : (empty($artists) ? [] : [$artists]),
        is_array($bands) ? $bands : (empty($bands) ? [] : [$bands])
    );
    foreach ($all_performers as $name) {
        if (empty(trim($name))) continue;
        if (!isset($performers[$name])) {
            $is_band = in_array($name, (is_array($bands) ? $bands : (empty($bands) ? [] : [$bands])));
            $performers[$name] = ['name' => $name, 'song_count' => 0, 'image' => get_artist_image_path($name), 'is_band' => $is_band];
        }
        $performers[$name]['song_count']++;
    }
}

// Seřadíme pole performerů podle zvolené metody
if (!empty($performers)) {
    $performers_list = array_values($performers);
    // Použijeme Collator pro správné řazení, pokud je k dispozici
    if (class_exists('Collator')) {
        $collator = new Collator('cs_CZ');
        usort($performers_list, function($a, $b) use ($collator, $sort_mode) {
            $name_a = $a['name']; $name_b = $b['name'];
            if ($sort_mode === 'lastname') {
                $parts_a = explode(' ', $name_a); $parts_b = explode(' ', $name_b);
                if (count($parts_a) > 1 && !$a['is_band']) { $name_a = end($parts_a); }
                if (count($parts_b) > 1 && !$b['is_band']) { $name_b = end($parts_b); }
            }
            return $collator->compare($name_a, $name_b);
        });
    }
    // Po seřazení převedeme zpět na asociativní pole, pokud je to potřeba pro další logiku
    $performers = [];
    foreach($performers_list as $p) { $performers[$p['name']] = $p; }
}

// *** ZMĚNA ZDE: Seskupíme interprety pomocí nové funkce ***
$grouped_interprets = [];
foreach ($performers as $performer) {
    $name_to_check = $performer['name'];
    if ($sort_mode === 'lastname') {
        $parts = explode(' ', $name_to_check);
        if (count($parts) > 1 && !$performer['is_band']) { $name_to_check = end($parts); }
    }
    $first_letter = get_czech_first_letter($name_to_check); // Použití nové funkce
    $grouped_interprets[$first_letter][] = $performer;
}
?>

<div class="interprets-page">
    <div class="song-header">
        <div class="left-group">
            <h1>Interpreti</h1>
        </div>
        <div class="right-group">
            <div class="sort-options">
                <form action="interprets.php" method="GET">
                    Řadit podle:
                    <label>
                        <input type="radio" name="sort" value="firstname" onchange="this.form.submit()" <?php if ($sort_mode !== 'lastname') echo 'checked'; ?>>
                        Jména
                    </label>
                    <label>
                        <input type="radio" name="sort" value="lastname" onchange="this.form.submit()" <?php if ($sort_mode === 'lastname') echo 'checked'; ?>>
                        Příjmení
                    </label>
                </form>
            </div>
        </div>    
    </div>
    
    <div class="filter-box">
        <input type="text" id="text-filter-input" placeholder="Filtrovat interprety...">
    </div>

    <?php if (!empty($grouped_interprets)): ?>
        <div class="alphabet-filter">
            <button class="alpha-btn active" data-letter="all">Vše</button>
            <?php
                $letters = array_keys($grouped_interprets);
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

    <div id="interprets-grid-grouped">
         <?php if (empty($grouped_interprets)): ?>
            <p>V seznamu zatím nejsou žádní interpreti.</p>
        <?php else: ?>
            <?php foreach ($grouped_interprets as $letter => $interprets_in_group): ?>
                <div class="interpret-group" data-group-letter="<?php echo htmlspecialchars($letter); ?>">
                    <h2 class="letter-heading"><?php echo htmlspecialchars($letter); ?></h2>
                    <div class="interprets-grid">
                        <?php foreach ($interprets_in_group as $interpret): ?>
                            <a href="#" class="interpret-card" data-artist="<?php echo htmlspecialchars($interpret['name']); ?>">
                                <img src="<?php echo htmlspecialchars($interpret['image']); ?>" alt="<?php echo htmlspecialchars($interpret['name']); ?>" ... >
                                <div class="card-overlay">
                                    <h3><?php echo htmlspecialchars($interpret['name']); ?> <span>(<?php echo $interpret['song_count']; ?>)</span></h3>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="artist-songs-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content artist-songs-modal-content">
        <h2 id="modal-artist-name"></h2>
        <div id="modal-song-list"></div>
        <button id="modal-close-btn" class="btn-close-modal">Zavřít</button>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Proměnná z PHP, která říká, zda je uživatel přihlášen
    const isUserLoggedIn = <?php echo json_encode(is_user_logged_in()); ?>;

    // --- PRVKY PRO FILTROVÁNÍ ---
    const textFilterInput = document.getElementById('text-filter-input');
    const alphaButtons = document.querySelectorAll('.alpha-btn');
    const interpretGroups = document.querySelectorAll('.interpret-group');

    // --- PRVKY PRO MODÁLNÍ OKNO ---
    const modal = document.getElementById('artist-songs-modal');
    const modalArtistName = document.getElementById('modal-artist-name');
    const modalSongList = document.getElementById('modal-song-list');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const allInterpretCards = document.querySelectorAll('.interpret-card');

    // --- FUNKCE PRO FILTROVÁNÍ ---
    
    // 1. Filtrování textem
    if(textFilterInput) {
        textFilterInput.addEventListener('input', function() {
            const filterText = this.value.toLowerCase().trim();
            
            // Zrušíme aktivní tlačítko abecedy a aktivujeme "Vše"
            alphaButtons.forEach(btn => btn.classList.remove('active'));
            const allBtn = document.querySelector('.alpha-btn[data-letter="all"]');
            if(allBtn) allBtn.classList.add('active');

            interpretGroups.forEach(group => {
                let groupHasVisibleCards = false;
                group.querySelectorAll('.interpret-card').forEach(card => {
                    const artistName = card.dataset.artist.toLowerCase();
                    if (artistName.includes(filterText)) {
                        card.style.display = 'block';
                        groupHasVisibleCards = true;
                    } else {
                        card.style.display = 'none';
                    }
                });
                group.style.display = groupHasVisibleCards ? 'block' : 'none';
            });
        });
    }

    // 2. Filtrování abecedou
    alphaButtons.forEach(button => {
        button.addEventListener('click', function() {
            const selectedLetter = this.dataset.letter;
            if(textFilterInput) textFilterInput.value = ''; // Vyčistíme textový filtr
            
            alphaButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            interpretGroups.forEach(group => {
                // Zobrazíme všechny karty uvnitř skupiny (pro případ, že byly skryty textovým filtrem)
                group.querySelectorAll('.interpret-card').forEach(card => card.style.display = 'block');

                // Zobrazíme nebo skryjeme celou skupinu podle vybraného písmene
                if (selectedLetter === 'all' || group.dataset.groupLetter === selectedLetter) {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    });

    // --- LOGIKA PRO MODÁLNÍ OKNO ---

    // Otevření okna po kliknutí na kartu
    allInterpretCards.forEach(card => {
        card.addEventListener('click', function(event) {
            event.preventDefault();
            const artistName = this.dataset.artist;

            if(modalArtistName) modalArtistName.textContent = `Písně od: ${artistName}`;
            if(modalSongList) modalSongList.innerHTML = '<p>Načítám...</p>';
            if(modal) modal.style.display = 'flex';

            fetch(`search-api.php?artist=${encodeURIComponent(artistName)}&_=${new Date().getTime()}`)
                .then(response => {
                    if (!response.ok) { throw new Error(`Chyba serveru: ${response.status}`); }
                    return response.json();
                })
                .then(songs => {
                    if (songs.error) { throw new Error(songs.error); }
                    
                    let html = '';
                    if (songs.length > 0) {
                        html = '<div class="song-list modal-song-list">';
                        songs.forEach(song => {
                            html += `<div class="song-list-item-wrapper">
                                        <a href="song.php?id=${song.id}" class="song-list-item">
                                            <span class="song-title">${song.title}</span>
                                        </a>`;
                            if (isUserLoggedIn) {
                                html += `<a href="editor.php?id=${song.id}" class="edit-link" title="Upravit píseň">
                                            <span class="material-symbols-outlined">music_history</span>
                                        </a>`;
                            }
                            html += `</div>`;
                        });
                        html += '</div>';
                    } else {
                        html = '<p>Pro tohoto interpreta nebyly nalezeny žádné písně.</p>';
                    }
                    if(modalSongList) modalSongList.innerHTML = html;
                })
                .catch(error => {
                    if(modalSongList) modalSongList.innerHTML = `<div class="message error"><strong>Nastala chyba:</strong><br>${error.message}</div>`;
                });
        });
    });

    // Zavření okna
    const closeModal = () => {
        if(modal) modal.style.display = 'none';
    };
    if(modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
    if(modal) modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
});
</script>