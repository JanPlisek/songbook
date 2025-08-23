<?php
// === INICIALIZAČNÍ BLOK (přidáno pro opravu chyby) ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'includes/functions.php';
// === KONEC BLOKU ===

$bodyClass = "song-view-body";
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

// Vylepšení titulku stránky, aby nezpůsobil chybu, pokud chybí data
$performers_title = implode(', ', array_merge($song_data['artist'] ?? [], $song_data['band'] ?? []));
$pageTitle = $song_data ? $song_data['title'] . ' - ' . $performers_title : 'Chyba';
include 'includes/header.php';
?>

<div class="song-page">
    <div id="song-controls-container">
        <div id="controls-popup">
            <button id="btn-decrease-font" title="Zmenšit písmo"><span class="material-symbols-outlined">text_decrease</span></button>
            <button id="btn-increase-font" title="Zvětšit písmo"><span class="material-symbols-outlined">text_increase</span></button>
            <button id="btn-autofit-font" title="Přizpůsobit šířce"><span class="material-symbols-outlined">fit_screen</span></button>
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
                    $artists = $song_data['artist'] ?? [];
                    $bands = $song_data['band'] ?? [];
                    $performers = array_merge($artists, $bands);
                    $performer_string = implode(', ', $performers);
                ?>
                <h2><?php echo htmlspecialchars($performer_string); ?></h2>
            </div>
            <div class="right-group">
                <span>orig. tónina: <span id="current-key"><?php echo htmlspecialchars($song_data['originalKey']); ?></span></span>
                <?php if (!empty($song_data['capo']) && $song_data['capo'] > 0): ?>
                    <span>capo: <span class="capo"><?php echo htmlspecialchars($song_data['capo']); ?></span></span>
                <?php endif; ?>

                <?php if (is_user_logged_in()): ?>
                <a href="editor.php?id=<?php echo htmlspecialchars($song_data['id']); ?>" class="edit-link-header" title="Upravit tuto píseň">
                    <span class="material-symbols-outlined">music_history</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div id="song-content" class="lyrics-container lyrics-grid-container">
            <div id="song-column-1" class="lyrics-column"></div>
            <div id="song-column-2" class="lyrics-column"></div>
        </div>

    <?php else: ?>
        <div class="error-box"><h2>Chyba</h2><p><?php echo htmlspecialchars($error_message); ?></p></div>
    <?php endif; ?>
</div>

<div id="transpose-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Vyberte cílovou tóninu</h3>
        <div class="transpose-keys">
            <button data-key="C">C</button> <button data-key="Db">Db</button><button data-key="D">D</button> <button data-key="Eb">Eb</button><button data-key="E">E</button> <button data-key="F">F</button><button data-key="F#">F#</button> <button data-key="G">G</button><button data-key="Ab">Ab</button> <button data-key="A">A</button><button data-key="Bb">Bb</button> <button data-key="B">B</button><button data-key="Cm">Cm</button> <button data-key="C#m">C#m</button><button data-key="Dm">Dm</button> <button data-key="D#m">D#m</button><button data-key="Em">Em</button> <button data-key="Fm">Fm</button><button data-key="F#m">F#m</button> <button data-key="Gm">Gm</button><button data-key="G#m">G#m</button> <button data-key="Am">Am</button><button data-key="Bbm">Bbm</button> <button data-key="Bm">Bm</button>
        </div>
        <button id="btn-close-modal" class="btn-close-modal">Zavřít</button>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lyricsContainer = document.getElementById('song-content');
    <?php if ($song_data && !empty($song_data['lyrics'])): ?>
    
    function findSmartSplitPoint(lyrics) {
        const totalLines = lyrics.length;
        if (totalLines < 12) { return totalLines; }
        const idealSplitPoint = totalLines / 2;
        const possibleSplitPoints = [];
        for (let i = 1; i < totalLines; i++) {
            if (lyrics[i].block_start) {
                possibleSplitPoints.push(i);
            }
        }
        if (possibleSplitPoints.length === 0) { return Math.ceil(idealSplitPoint); }
        let bestPoint = possibleSplitPoints[0];
        let minDistance = Math.abs(bestPoint - idealSplitPoint);
        possibleSplitPoints.forEach(point => {
            const distance = Math.abs(point - idealSplitPoint);
            if (distance < minDistance) {
                minDistance = distance;
                bestPoint = point;
            }
        });
        return bestPoint;
    }

    function renderSong(lyrics) {
        const col1 = document.getElementById('song-column-1');
        const col2 = document.getElementById('song-column-2');
        const singleContainer = document.getElementById('song-content');

        const createHtmlForLines = (lines) => {
            let html = '';
            lines.forEach(line => {
                const groupClass = line.block_start ? 'lyrics-line-group stanza-start' : 'lyrics-line-group';
                html += `<div class="${groupClass}">`;
                if (line.chords && line.chords.trim() !== '') { html += `<div class="chords-line">${line.chords}</div>`; }
                html += `<div class="text-line">${line.text || '&nbsp;'}</div>`;
                html += '</div>';
            });
            return html;
        };

        if (col1 && col2) {
            const splitIndex = findSmartSplitPoint(lyrics);
            const firstHalf = lyrics.slice(0, splitIndex);
            const secondHalf = lyrics.slice(splitIndex);
            col1.innerHTML = createHtmlForLines(firstHalf);
            col2.innerHTML = createHtmlForLines(secondHalf);
        } else {
            singleContainer.innerHTML = createHtmlForLines(lyrics);
        }
    }
    
    const songLyricsData = <?php echo json_encode($song_data['lyrics']); ?>;
    let originalKey = '<?php echo $song_data['originalKey'] ?? 'C'; ?>';

    // OVLÁDACÍ PANEL
    let currentFontSize = 12;
    const updateFontSize = () => { if(lyricsContainer) lyricsContainer.style.fontSize = `${currentFontSize}px`; };
    document.getElementById('btn-increase-font')?.addEventListener('click', () => { currentFontSize++; updateFontSize(); });
    document.getElementById('btn-decrease-font')?.addEventListener('click', () => { if (currentFontSize > 8) { currentFontSize--; updateFontSize(); } });
    document.getElementById('btn-reset-font')?.addEventListener('click', () => { currentFontSize = 12; if(lyricsContainer) lyricsContainer.style.fontSize = ''; });
    document.getElementById('btn-print')?.addEventListener('click', () => window.print());
    const toggleBtn = document.getElementById('btn-toggle-controls');
    const popup = document.getElementById('controls-popup');
    toggleBtn?.addEventListener('click', (event) => { event.stopPropagation(); popup.classList.toggle('visible'); });
    document.addEventListener('click', (event) => { if (popup && !popup.contains(event.target) && !toggleBtn.contains(event.target)) { popup.classList.remove('visible'); } });

    // AUTO-FIT FUNKCE
    const autofitButton = document.getElementById('btn-autofit-font');
    function autofitFontSize() {
        if (!lyricsContainer) return;
        lyricsContainer.style.fontSize = '';
        if (window.innerWidth >= 992) return; // Na desktopu neaktivujeme
        const containerWidth = lyricsContainer.clientWidth * 0.95;
        let baseFontSize = parseFloat(window.getComputedStyle(lyricsContainer).fontSize);
        let longestLineWidth = 0;
        const lines = lyricsContainer.querySelectorAll('.chords-line, .text-line');
        lines.forEach(line => { if (line.scrollWidth > longestLineWidth) { longestLineWidth = line.scrollWidth; } });
        if (longestLineWidth > containerWidth) {
            const ratio = containerWidth / longestLineWidth;
            let newFontSize = Math.floor(baseFontSize * ratio);
            if (newFontSize < 9) newFontSize = 9;
            lyricsContainer.style.fontSize = newFontSize + 'px';
            currentFontSize = newFontSize;
        }
    }
    if (autofitButton) { autofitButton.addEventListener('click', autofitFontSize); }

    // TRANSPOZICE
    const transposeModal = document.getElementById('transpose-modal');
    const currentKeySpan = document.getElementById('current-key');
    const scale = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"];
    const flatToSharp = { "Db": "C#", "Eb": "D#", "Gb": "F#", "Ab": "G#", "Bb": "A#" };
    function getChordRootInfo(chord) { let n = chord; if (n.toUpperCase().startsWith('H')) { n = 'B' + n.substring(1); } const m = n.match(/^[A-G](b|#)?/); if (!m) return null; const r = m[0]; const s = n.substring(r.length); const nr = flatToSharp[r] || r; const i = scale.indexOf(nr); return { root:r, suffix:s, index:i }; }
    function transposeChord(chord, amount) { const i = getChordRootInfo(chord); if (!i || i.index === -1) return chord; const ni = (i.index + amount + 12) % 12; let nr = scale[ni]; if (chord.toUpperCase().startsWith('H') && nr === 'B') { nr = 'H'; } else if (nr === 'B') { nr = 'H'; } return nr + i.suffix; }
    function transposeLine(line, amount) { if (!line.trim()) return line; return line.split(/(\s+)/).map(p => p.trim() ? transposeChord(p, amount) : p).join(''); }
    document.getElementById('btn-show-transpose')?.addEventListener('click', () => { if (!transposeModal) return; const iom = originalKey.includes('m'); document.querySelectorAll('.transpose-keys button').forEach(b => { const ibkm = b.dataset.key.includes('m'); b.style.display = iom === ibkm ? 'inline-block' : 'none'; }); transposeModal.style.display = 'flex'; });
    document.getElementById('btn-close-modal')?.addEventListener('click', () => { if(transposeModal) transposeModal.style.display = 'none'; });
    document.querySelectorAll('.transpose-keys button').forEach(b => { b.addEventListener('click', () => { const tk = b.dataset.key; const oki = getChordRootInfo(originalKey); const tki = getChordRootInfo(tk); if (!oki || !tki) return; const ta = tki.index - oki.index; const nl = songLyricsData.map(l => ({ chords: transposeLine(l.chords, ta), text: l.text })); renderSong(nl); if(currentKeySpan) currentKeySpan.textContent = tk; if(transposeModal) transposeModal.style.display = 'none'; }); });

    // PRVNÍ SPUŠTĚNÍ
    renderSong(songLyricsData);
    autofitFontSize();
    window.addEventListener('resize', autofitFontSize);

    <?php else: ?>
    // Kód pro případ, že se píseň nenačte
    <?php endif; ?>
});
</script>