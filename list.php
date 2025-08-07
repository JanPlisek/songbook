<?php
$pageTitle = "Rejstřík písní";
include 'includes/header.php';

$songs_dir = 'songs/';
$all_songs = [];
$grouped_songs = [];

// Krok 1: Načteme VŠECHNY písničky do jednoho velkého pole
if (is_dir($songs_dir)) {
    $files = scandir($songs_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'json' && $file !== '_masterlist.json') {
            $json_content = file_get_contents($songs_dir . $file);
            $song_data = json_decode($json_content, true);
            if ($song_data && !empty($song_data['title'])) {
                $song_data['source_file'] = $file; // Přidáme název souboru pro ladění
                $all_songs[] = $song_data;
            }
        }
    }
}

// Krok 2: Seřadíme celé pole písní pomocí třídy Collator (spolehlivá metoda)
if (!empty($all_songs)) {
    // Vytvoříme instanci třídy Collator pro český jazyk
    $collator = new Collator('cs_CZ');
    
    // Seřadíme pole písní podle jejich názvu
    usort($all_songs, function($a, $b) use ($collator) {
        return $collator->compare($a['title'], $b['title']);
    });
}


// Krok 3: Nyní, až po správném seřazení, seskupíme písničky podle prvního písmene
foreach ($all_songs as $song) {
    $first_letter = get_czech_first_letter($song['title']); // POUŽITÍ NOVÉ FUNKCE
    $grouped_songs[$first_letter][] = $song;
}
?>

<div class="list-page">
    <div class="song-header">
        <div class="left-group">
            <h1>Seznam písní</h1>
        </div>
        <div class="right-group">
            Máme zde celkem<strong><?php echo count($all_songs); ?></strong>písní
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

                // Zkontrolujeme, zda existuje rozšíření 'intl' pro správné řazení
                if (class_exists('Collator')) {
                    // Ideální stav: máme 'intl', použijeme pokročilé řazení
                    $collator = new Collator('cs_CZ');
                    $collator->sort($letters);
                } else {
                    // Záložní stav: 'intl' chybí, použijeme obyčejné řazení
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
                            <?php
                                // Sestavíme kompletní seznam performerů pro zobrazení
                                // Sloučíme pole 'artist' a 'band', pokud existují
                                $performers = array_merge($song['artist'] ?? [], $song['band'] ?? []);
                            ?>
                            <div class="song-list-item-wrapper" data-song-id="<?php echo htmlspecialchars($song['id']); ?>">
                                <a href="song.php?id=<?php echo htmlspecialchars($song['id']); ?>" class="song-list-item" title="Zdroj: <?php echo htmlspecialchars($song['source_file']); ?>">
                                    <span class="song-title"><?php echo htmlspecialchars($song['title']); ?></span>
                                    <span class="song-artist"><?php echo htmlspecialchars(implode(', ', $performers)); ?></span>
                                </a>
                                <?php if (is_user_logged_in()): ?>
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

    // --- NOVÁ LOGIKA PRO MAZÁNÍ PÍSNÍ ---
    songListContainer.addEventListener('click', function(event) {
        // Cílíme pouze na kliknutí na odkaz pro smazání nebo jeho ikonku
        const deleteLink = event.target.closest('.delete-link');
        if (!deleteLink) {
            return; // Kliknuto mimo, nic neděláme
        }
        
        event.preventDefault(); // Zabráníme výchozí akci odkazu (přesměrování)

        const songWrapper = deleteLink.closest('.song-list-item-wrapper');
        const songId = songWrapper.dataset.songId;
        const songTitle = songWrapper.querySelector('.song-title').textContent;

        // Zobrazíme potvrzovací dialog
        if (confirm(`Opravdu si přejete trvale smazat píseň "${songTitle}"?`)) {
            // Pokud uživatel potvrdí, pošleme požadavek na server
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
                    // Pokud server odpoví úspěchem, odstraníme prvek ze stránky
                    songWrapper.style.transition = 'opacity 0.5s ease';
                    songWrapper.style.opacity = '0';
                    setTimeout(() => {
                        songWrapper.remove();
                        // Můžeme zde případně aktualizovat i celkový počet písní
                    }, 500);
                } else {
                    // Pokud nastala chyba, zobrazíme ji
                    alert('Chyba při mazání: ' + data.message);
                }
            })
            .catch(error => {
                // Zachytíme chyby sítě atd.
                console.error('Došlo k chybě:', error);
                alert('Došlo k technické chybě. Zkuste to prosím později.');
            });
        }
    });
});
</script>