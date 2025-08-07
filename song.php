<?php $bodyClass = "song-view-body"; ?>
<?php
$song_id = $_GET['id'] ?? null;
$song_data = null;
$error_message = '';

if ($song_id) {
    if (preg_match('/^[a-z0-9-]+$/', $song_id)) {
        $file_path = 'songs/' . $song_id . '.json';
        if (file_exists($file_path)) {
            $json_content = file_get_contents($file_path);
            $song_data = json_decode($json_content, true);
        } else { $error_message = "Písnička s ID '$song_id' nebyla nalezena."; }
    } else { $error_message = "Neplatné ID písničky."; }
} else { $error_message = "Nebylo zadáno ID písničky."; }

$pageTitle = $song_data ? $song_data['title'] . ' - ' . implode(', ', $song_data['artist']) : 'Chyba';
include 'includes/header.php';
?>

<div class="song-page">
    <div id="song-controls-container">
        <div id="controls-popup">
            <button id="btn-decrease-font" title="Zmenšit písmo"><span class="material-symbols-outlined">text_decrease</span></button>
            <button id="btn-increase-font" title="Zvětšit písmo"><span class="material-symbols-outlined">text_increase</span></button>
            <button id="btn-reset-font" title="Původní velikost"><span class="material-symbols-outlined">text_format</span></button>
            <hr>
            <button id="btn-show-transpose" title="Transpozice"><span class="material-symbols-outlined">replace_audio</span></button>
            <hr>
            <button id="btn-print" title="Tisk"><span class="material-symbols-outlined">print</span></button>
        </div>
        <button id="btn-toggle-controls" title="Nástroje"><span class="material-symbols-outlined">build_circle</span></button>
    </div>

    <?php if ($song_data): ?>
        <div class="song-header">
            <div class="left-group">
                <h1><?php echo htmlspecialchars($song_data['title']); ?></h1>
                <?php
                    // Připravíme si pole autorů a kapel, pro případ, že by některé chybělo
                    $artists = $song_data['artist'] ?? [];
                    $bands = $song_data['band'] ?? [];
                    
                    // Sloučíme obě pole do jednoho
                    $performers = array_merge($artists, $bands);
                    
                    // Převedeme pole na text oddělený čárkami
                    $performer_string = implode(', ', $performers);
                ?>
                <h2><?php echo htmlspecialchars($performer_string); ?></h2>
            </div>
            <div class="right-group">
                <span>orig. tónina: <span id="current-key"><?php echo htmlspecialchars($song_data['originalKey']); ?></span></span>
                <?php if ($song_data['capo'] > 0): ?>
                    <span>capo: <span class="capo"><?php echo htmlspecialchars($song_data['capo']); ?></span></span>
                <?php endif; ?>
                <a href="editor.php?id=<?php echo htmlspecialchars($song_data['id']); ?>" class="edit-link-header" title="Upravit tuto píseň">
                    <span class="material-symbols-outlined">music_history</span>
                </a>
            </div>
        </div>
        <div id="song-content" class="lyrics-container"></div>
    <?php else: ?>
        <div class="error-box"><h2>Chyba</h2><p><?php echo htmlspecialchars($error_message); ?></p></div>
    <?php endif; ?>
</div>

<div id="transpose-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Vyberte cílovou tóninu</h3>
        <div class="transpose-keys">
            <button data-key="C">C</button> <button data-key="Db">Db</button>
            <button data-key="D">D</button> <button data-key="Eb">Eb</button>
            <button data-key="E">E</button> <button data-key="F">F</button>
            <button data-key="F#">F#</button> <button data-key="G">G</button>
            <button data-key="Ab">Ab</button> <button data-key="A">A</button>
            <button data-key="Bb">Bb</button> <button data-key="B">B</button>
            <button data-key="Cm">Cm</button> <button data-key="C#m">C#m</button>
            <button data-key="Dm">Dm</button> <button data-key="D#m">D#m</button>
            <button data-key="Em">Em</button> <button data-key="Fm">Fm</button>
            <button data-key="F#m">F#m</button> <button data-key="Gm">Gm</button>
            <button data-key="G#m">G#m</button> <button data-key="Am">Am</button>
            <button data-key="Bbm">Bbm</button> <button data-key="Bm">Bm</button>
        </div>
        <button id="btn-close-modal" class="btn-close-modal">Zavřít</button>
    </div>
</div>
<?php include 'includes/footer.php'; ?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- ČÁST 1: Vykreslení písničky (beze změny) ---
    const lyricsContainer = document.getElementById('song-content');
    <?php if ($song_data && !empty($song_data['lyrics'])): ?>
    function renderSong(lyrics) {
        let html = '';
        const stanzaRegex = /^\s*(\d+\.|[a-zA-Z]+:)/;
        lyrics.forEach(line => {
            const isStanzaStart = stanzaRegex.test(line.text);
            const groupClass = isStanzaStart ? 'lyrics-line-group stanza-start' : 'lyrics-line-group';
            html += `<div class="${groupClass}">`;
            if (line.chords && line.chords.trim() !== '') {
                html += `<div class="chords-line">${line.chords}</div>`;
            }
            html += `<div class="text-line">${line.text || '&nbsp;'}</div>`;
            html += '</div>';
        });
        lyricsContainer.innerHTML = html;
    }
    const songLyricsData = <?php echo json_encode($song_data['lyrics']); ?>;
    let originalKey = '<?php echo $song_data['originalKey'] ?? 'C'; ?>';
    renderSong(songLyricsData);
    <?php else: ?>
    let originalKey = 'C';
    <?php endif; ?>

    // --- ČÁST 2: Ovládací panel (beze změny) ---
    // ... logika pro font-size, dark-mode, tisk, zobrazení/skrytí panelu ...
    
    // --- ČÁST 3: Logika pro transpozici (s opravou pro akord H) ---
    const transposeModal = document.getElementById('transpose-modal');
    const currentKeySpan = document.getElementById('current-key');
    const scale = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"];
    const flatToSharp = { "Db": "C#", "Eb": "D#", "Gb": "F#", "Ab": "G#", "Bb": "A#" };

    // ZDE JE KLÍČOVÁ OPRAVA
    function getChordRootInfo(chord) {
        // Nejprve normalizujeme 'H' na 'B' pro interní zpracování
        let normalizedChord = chord;
        if (normalizedChord.toUpperCase().startsWith('H')) {
            normalizedChord = 'B' + normalizedChord.substring(1);
        }

        const match = normalizedChord.match(/^[A-G](b|#)?/);
        if (!match) return null;

        const root = match[0];
        const suffix = normalizedChord.substring(root.length);
        const normalizedRoot = flatToSharp[root] || root;
        const index = scale.indexOf(normalizedRoot);
        return { root, suffix, index };
    }

    function transposeChord(chord, amount) {
        const info = getChordRootInfo(chord);
        if (!info || info.index === -1) return chord; // Pokud akord nerozpoznáme, vrátíme ho beze změny
        
        const newIndex = (info.index + amount + 12) % 12;
        let newRoot = scale[newIndex];

        // Zpětný převod B na H pro čitelnost, pokud původní akord byl H
        if (chord.toUpperCase().startsWith('H') && newRoot === 'B') {
            newRoot = 'H';
        } else if (chord.toUpperCase().startsWith('B') && newRoot === 'B' && !chord.toUpperCase().startsWith('BB')) {
            // toto osetruje, aby se "B" nezmenilo na H (B je Bb)
        } else if (newRoot === 'B') {
             // pokud je original jiny nez H, tak B prevedeme na H
            newRoot = 'H';
        }
        
        return newRoot + info.suffix;
    }

    function transposeLine(lineOfChords, amount) {
        if (!lineOfChords.trim()) return lineOfChords;
        return lineOfChords.split(/(\s+)/).map(part => {
            return part.trim() ? transposeChord(part, amount) : part;
        }).join('');
    }

    document.getElementById('btn-show-transpose')?.addEventListener('click', () => {
        if (!transposeModal) return;
        const isOriginalKeyMinor = originalKey.includes('m');
        document.querySelectorAll('.transpose-keys button').forEach(button => {
            const isButtonKeyMinor = button.dataset.key.includes('m');
            if (isOriginalKeyMinor === isButtonKeyMinor) {
                button.style.display = 'inline-block';
            } else {
                button.style.display = 'none';
            }
        });
        transposeModal.style.display = 'flex';
    });

    document.getElementById('btn-close-modal')?.addEventListener('click', () => { if(transposeModal) transposeModal.style.display = 'none'; });
    
    document.querySelectorAll('.transpose-keys button').forEach(button => {
        button.addEventListener('click', () => {
            const targetKey = button.dataset.key;
            const originalKeyInfo = getChordRootInfo(originalKey);
            const targetKeyInfo = getChordRootInfo(targetKey);
            if (!originalKeyInfo || !targetKeyInfo) return;
            const transposeAmount = targetKeyInfo.index - originalKeyInfo.index;
            const newLyrics = songLyricsData.map(line => ({
                chords: transposeLine(line.chords, transposeAmount),
                text: line.text
            }));
            renderSong(newLyrics);
            if(currentKeySpan) currentKeySpan.textContent = targetKey;
            if(transposeModal) transposeModal.style.display = 'none';
        });
    });

    // Zde je pro úplnost i kód ovládacího panelu
    let currentFontSize = 16;
    const updateFontSize = () => { if(lyricsContainer) lyricsContainer.style.fontSize = `${currentFontSize}px`; };
    document.getElementById('btn-increase-font')?.addEventListener('click', () => { currentFontSize++; updateFontSize(); });
    document.getElementById('btn-decrease-font')?.addEventListener('click', () => { if (currentFontSize > 8) { currentFontSize--; updateFontSize(); } });
    document.getElementById('btn-reset-font')?.addEventListener('click', () => { currentFontSize = 16; updateFontSize(); });
    document.getElementById('btn-print')?.addEventListener('click', () => window.print());
    const toggleBtn = document.getElementById('btn-toggle-controls');
    const popup = document.getElementById('controls-popup');
    toggleBtn?.addEventListener('click', (event) => { event.stopPropagation(); popup.classList.toggle('visible'); });
    document.addEventListener('click', (event) => { if (popup && !popup.contains(event.target) && !toggleBtn.contains(event.target)) { popup.classList.remove('visible'); } });
});
</script>