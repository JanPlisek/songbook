<?php
// soubor: admin_settings.php

require_once 'includes/gatekeeper.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Pouze pro administrátory
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';
$active_tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : (isset($_GET['tab']) ? $_GET['tab'] : 'security');

// --- ZPRACOVÁNÍ FORMULÁŘŮ ---

// 1. Změna hesla pro veřejnost
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $new_password = $_POST['new_public_password'];
    $confirm_password = $_POST['confirm_public_password'];

    if (empty($new_password)) {
        $error_message = "Heslo nesmí být prázdné.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Zadaná hesla se neshodují.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE zp_settings SET setting_value = ? WHERE setting_key = 'public_password_hash'");
            $stmt->execute([$new_hash]);
            $success_message = "Heslo do veřejné sekce bylo úspěšně změněno.";
        } catch (PDOException $e) {
            $error_message = "Chyba při ukládání: " . $e->getMessage();
        }
    }
}

// 2. Ruční uložení akordu (pro Chords tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_manual_chord') {
    $chord_name = trim($_POST['manual_chord_name']);
    $fingering = trim($_POST['manual_fingering']);
    if (!empty($chord_name) && !empty($fingering)) {
        $chords_file = __DIR__ . '/assets/data/chords.json';
        $current_data = file_exists($chords_file) ? json_decode(file_get_contents($chords_file), true) : [];
        $current_data[$chord_name] = $fingering;
        ksort($current_data);
        if (file_put_contents($chords_file, json_encode($current_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $success_message = "Akord <strong>$chord_name</strong> byl uložen.";
        }
    }
}

// 3. Načtení požadavků (pro Requests tab)
$stmt = $pdo->query("SELECT * FROM zp_requests ORDER BY is_completed ASC, created_at DESC");
$requests = $stmt->fetchAll();

// 4. Scanner akordů (pro Chords tab)
$chord_stats = ['total' => 0, 'added' => [], 'missing' => [], 'locations' => []];
$chords_file = __DIR__ . '/assets/data/chords.json';
if (file_exists($chords_file)) {
    $current_chords = json_decode(file_get_contents($chords_file), true);
    $chord_stats['total'] = is_array($current_chords) ? count($current_chords) : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_chords') {
    $master_chords = file_exists('includes/chord_dictionary.php') ? include 'includes/chord_dictionary.php' : [];
    
    function normalize_chord($n) {
        if (!$n) return '';
        $n = trim($n, " \t\n\r\0\x0B,.");
        $n = str_replace('mi', 'm', $n); 
        if (preg_match('/^As(?!us)/i', $n)) $n = 'Ab' . substr($n, 2);
        if (preg_match('/^Es/i', $n)) $n = 'Eb' . substr($n, 2);
        $n = preg_replace('/([A-G][b#]?[m]?7?)4$/', '$1sus4', $n);
        $n = preg_replace('/([A-G][b#]?[m]?7)sus$/', '$1sus4', $n);
        $n = str_replace(['4sus', '7dim', '7maj'], ['sus4', 'dim7', 'maj7'], $n);
        if (strpos($n, 'maj') !== false && !preg_match('/maj[79]/', $n)) $n = str_replace('maj', 'maj7', $n);
        $n = str_replace(['maj79', 'maj77'], ['maj9', 'maj7'], $n);
        return ($n === 'E5b') ? 'Eb5' : $n;
    }

    $stmt_songs = $pdo->query("SELECT id, title, lyrics FROM zp_songs");
    $found_chords = [];
    while ($row = $stmt_songs->fetch()) {
        $lyrics = json_decode($row['lyrics'], true);
        if (!is_array($lyrics)) continue;
        foreach ($lyrics as $line) {
            if (empty($line['chords'])) continue;
            $parts = preg_split('/[\s\(\)\/\-,]+/', $line['chords']);
            foreach ($parts as $p) {
                $p = trim($p);
                if (!$p || preg_match('/^[0-9]+x?$/i', $p) || preg_match('/^[\.,]+$/', $p) || strpos($p, '!') !== false) continue;
                $norm = normalize_chord($p);
                if ($norm) {
                    $found_chords[$norm] = true;
                    if (!isset($chord_stats['locations'][$norm])) $chord_stats['locations'][$norm] = [];
                    $exists = false;
                    foreach ($chord_stats['locations'][$norm] as $loc) { if ($loc['id'] == $row['id']) { $exists = true; break; } }
                    if (!$exists) $chord_stats['locations'][$norm][] = ['id' => $row['id'], 'title' => $row['title']];
                }
            }
        }
    }

    $current_data = file_exists($chords_file) ? json_decode(file_get_contents($chords_file), true) : [];
    foreach (array_keys($found_chords) as $chord) {
        if (!isset($current_data[$chord])) {
            if (isset($master_chords[$chord])) {
                $current_data[$chord] = $master_chords[$chord];
                $chord_stats['added'][] = $chord;
            } else {
                $base = preg_replace('/\/[A-G][b#]?/', '', $chord);
                if ($base !== $chord && isset($master_chords[$base])) {
                    $current_data[$chord] = $master_chords[$base];
                    $chord_stats['added'][] = $chord;
                } else {
                    $chord_stats['missing'][] = $chord;
                }
            }
        }
    }

    if (!empty($chord_stats['added'])) {
        ksort($current_data);
        file_put_contents($chords_file, json_encode($current_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $success_message = "Scanner dokončen. Přidáno " . count($chord_stats['added']) . " hmatů.";
    }
    $chord_stats['total'] = count($current_data);
    sort($chord_stats['missing']);
}

$pageTitle = "Nastavení";
include 'includes/header.php';
?>

<style>
    .settings-container { max-width: 1000px; margin: 2rem auto; }
    .tabs { display: flex; gap: 5px; margin-bottom: -1px; flex-wrap: wrap; }
    .tab-btn { 
        padding: 12px 24px; background: #333; border: 1px solid #444; border-bottom: none; 
        color: #888; cursor: pointer; border-radius: 8px 8px 0 0; font-weight: bold; transition: 0.2s;
        display: flex; align-items: center; gap: 8px;
    }
    .tab-btn.active { background: var(--bg-card); color: var(--accent-color); border-top: 3px solid var(--accent-color); }
    .tab-content { 
        background: var(--bg-card); padding: 2.5rem; border-radius: 0 8px 8px 8px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 1px solid #444; min-height: 400px;
    }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 8px; text-align: center; border: 1px solid rgba(255,255,255,0.05); }
    .stat-card .value { font-size: 2.5rem; font-weight: bold; color: var(--accent-color); }
    
    .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid; }
    .alert-success { background: rgba(46, 204, 113, 0.1); border-color: #2ecc71; color: #2ecc71; }
    .alert-error { background: rgba(231, 76, 60, 0.1); border-color: #e74c3c; color: #e74c3c; }

    /* Visual Editor Styles */
    .chord-editor-container { background: rgba(0,0,0,0.15); border: 1px solid #444; border-radius: 8px; padding: 1.5rem; margin-top: 2rem; display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap; }
    .fretboard { 
        display: grid; grid-template-columns: repeat(6, 45px); grid-template-rows: 35px repeat(5, 50px); 
        background: #3c3028; padding: 10px; border-radius: 4px; border: 4px solid #2a1f18; position: relative;
        user-select: none;
    }
    .fret { 
        border-bottom: 1px solid #999; position: relative; display: flex; justify-content: center; 
        align-items: center; cursor: pointer; height: 100%; width: 100%;
    }
    .fret:nth-child(-n+6) { border-bottom: 5px solid #eee; }
    .string-line { position: absolute; left: 50%; width: 2px; height: 100%; background: #ccc; z-index: 1; pointer-events: none; transform: translateX(-50%); }
    .dot { width: 28px; height: 28px; background: #c67701; border-radius: 50%; z-index: 5; display: none; box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
    .dot.active { display: block; }
    .marker { z-index: 5; font-weight: bold; font-family: monospace; font-size: 1.5rem; }
    .marker.status-x { color: #ff5252; text-shadow: 0 1px 2px rgba(0,0,0,0.8); }
    .marker.status-o { color: #4caf50; text-shadow: 0 1px 2px rgba(0,0,0,0.8); }

    /* Requests Styles */
    .requests-list { display: flex; flex-direction: column; gap: 1rem; }
    .request-item { 
        background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); 
        padding: 1.2rem; border-radius: 8px; display: flex; gap: 1rem; align-items: flex-start;
        transition: 0.3s;
    }
    .request-item.completed { opacity: 0.4; filter: grayscale(1); }
    .request-checkbox input { width: 20px; height: 20px; cursor: pointer; }
    .request-details h3 { margin: 0; font-size: 1.1rem; color: var(--accent-color); }
    .requester-info { font-size: 0.85rem; color: #888; margin: 4px 0; }
    .request-note { background: rgba(0,0,0,0.2); padding: 8px 12px; border-radius: 4px; margin-top: 8px; font-size: 0.9rem; }
    body.dark-mode .request-item.completed h3 { text-decoration: line-through; }
</style>

<div class="container settings-container">
    <header style="margin-bottom: 2rem;">
        <h1>Administrativní panel</h1>
    </header>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn <?php echo $active_tab === 'security' ? 'active' : ''; ?>" onclick="switchTab('security')">
            <span class="material-symbols-outlined">security</span> ZABEZPEČENÍ
        </button>
        <button class="tab-btn <?php echo $active_tab === 'chords' ? 'active' : ''; ?>" onclick="switchTab('chords')">
            <span class="material-symbols-outlined">straighten</span> AKORDY
        </button>
        <button class="tab-btn <?php echo $active_tab === 'requests' ? 'active' : ''; ?>" onclick="switchTab('requests')">
            <span class="material-symbols-outlined">playlist_add_check</span> POŽADAVKY
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tools' ? 'active' : ''; ?>" onclick="switchTab('tools')">
            <span class="material-symbols-outlined">build</span> NÁSTROJE
        </button>
    </div>

    <!-- TAB: SECURITY -->
    <div id="tab-security" class="tab-content" style="display: <?php echo $active_tab === 'security' ? 'block' : 'none'; ?>;">
        <h2>Přístup pro veřejnost</h2>
        <p style="color:#888; margin-bottom: 2rem;">Nastavení hesla pro ne-administrátorské uživatele.</p>
        <form action="admin_settings.php?tab=security" method="POST" style="max-width: 400px;">
            <input type="hidden" name="action" value="update_password">
            <input type="hidden" name="active_tab" value="security">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Nové heslo:</label>
                <input type="password" name="new_public_password" required style="width:100%; padding:0.8rem; background:rgba(0,0,0,0.2); border:1px solid #444; border-radius:4px; color:white;">
            </div>
            <div class="form-group" style="margin-bottom: 2rem;">
                <label>Potvrzení hesla:</label>
                <input type="password" name="confirm_public_password" required style="width:100%; padding:0.8rem; background:rgba(0,0,0,0.2); border:1px solid #444; border-radius:4px; color:white;">
            </div>
            <button type="submit" class="btn btn-primary">ULOŽIT HESLO</button>
        </form>
    </div>

    <!-- TAB: CHORDS -->
    <div id="tab-chords" class="tab-content" style="display: <?php echo $active_tab === 'chords' ? 'block' : 'none'; ?>;">
        <h2>Databáze akordů</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>V databázi</h3>
                <div class="value"><?php echo $chord_stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <form action="admin_settings.php?tab=chords" method="POST">
                    <input type="hidden" name="action" value="update_chords">
                    <input type="hidden" name="active_tab" value="chords">
                    <button type="submit" class="btn" style="background:#2ecc71; color:white; border:none; padding:12px; width:100%; border-radius:6px; cursor:pointer; font-weight:bold;">
                        SCANOVAT PÍSNĚ
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($chord_stats['missing'])): ?>
            <h3 style="color:#e67e22;">Nerozpoznané akordy</h3>
            <table style="width:100%; border-collapse: collapse; margin-bottom: 2rem;">
                <?php foreach ($chord_stats['missing'] as $m): ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding:10px; font-weight:bold;"><?php echo $m; ?></td>
                        <td style="padding:10px;">
                            <?php foreach ($chord_stats['locations'][$m] as $loc): ?>
                                <a href="song.php?id=<?php echo $loc['id']; ?>" target="_blank" style="color:#888; font-size:0.8rem; margin-right:10px;"><?php echo htmlspecialchars($loc['title']); ?></a>
                            <?php endforeach; ?>
                        </td>
                        <td style="padding:10px; text-align:right;">
                            <button class="btn btn-sm" onclick="editInEditor('<?php echo $m; ?>')" style="padding:4px 8px; font-size:0.7rem;">VYTVOŘIT</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="chord-editor-container">
            <div>
                <h3>Vizuální editor</h3>
                <div class="fretboard" id="editor-fretboard">
                    <?php for($f=0; $f<=5; $f++): ?>
                        <?php for($s=0; $s<6; $s++): ?>
                            <div class="fret" onclick="toggleFret(<?php echo $f; ?>, <?php echo $s; ?>)">
                                <?php if($f > 0): ?><div class="string-line"></div><?php endif; ?>
                                <div class="dot" id="dot-<?php echo $f; ?>-<?php echo $s; ?>"></div>
                                <div class="marker" id="marker-<?php echo $f; ?>-<?php echo $s; ?>"></div>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
                </div>
            </div>
            <div style="flex:1;">
                <form action="admin_settings.php?tab=chords" method="POST">
                    <input type="hidden" name="action" value="save_manual_chord">
                    <input type="hidden" name="active_tab" value="chords">
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label>Název:</label>
                        <input type="text" id="chord-name-input" name="manual_chord_name" required style="width:100%; padding:0.8rem; background:rgba(255,255,255,0.05); border:1px solid #444; border-radius:4px; color:white; font-size: 1.2rem;">
                    </div>
                    <div class="form-group" style="margin-bottom:1.5rem;">
                        <label>Hmat:</label>
                        <input type="text" id="chord-fingering-input" name="manual_fingering" readonly style="width:100%; padding:0.8rem; background:rgba(0,0,0,0.2); border:1px solid #333; color:#888; font-family:monospace;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; background:#2ecc71;">ULOŽIT AKORD</button>
                    <button type="button" class="btn" onclick="resetEditor()" style="width:100%; margin-top:10px; background:#444;">VYČISTIT</button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: REQUESTS -->
    <div id="tab-requests" class="tab-content" style="display: <?php echo $active_tab === 'requests' ? 'block' : 'none'; ?>;">
        <h2>Požadavky na písně</h2>
        <p style="color:#888; margin-bottom: 2rem;">Seznam přání od uživatelů. Splněné požadavky se automaticky přesouvají dolů.</p>
        
        <div class="requests-list" id="admin-requests-list">
            <?php if (empty($requests)): ?>
                <p>Žádné požadavky v databázi.</p>
            <?php else: ?>
                <?php foreach ($requests as $req): ?>
                    <div class="request-item <?php echo $req['is_completed'] ? 'completed' : ''; ?>" data-id="<?php echo $req['id']; ?>">
                        <div class="request-checkbox">
                            <input type="checkbox" <?php echo $req['is_completed'] ? 'checked' : ''; ?> onchange="toggleRequestStatus(<?php echo $req['id']; ?>, this)">
                        </div>
                        <div class="request-details">
                            <h3><?php echo htmlspecialchars($req['song_title']); ?></h3>
                            <p class="requester-info">
                                Od: <strong><?php echo htmlspecialchars($req['requester_name'] ?: 'Anonym'); ?></strong> 
                                <em>(<?php echo date('j. n. Y', strtotime($req['created_at'])); ?>)</em>
                            </p>
                            <?php if ($req['note']): ?>
                                <div class="request-note"><?php echo nl2br(htmlspecialchars($req['note'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: TOOLS -->
    <div id="tab-tools" class="tab-content" style="display: <?php echo $active_tab === 'tools' ? 'block' : 'none'; ?>;">
        <h2>Doplňkové nástroje</h2>
        <p style="color:#888;">Zde budou v budoucnu další funkce (např. export, masivní úpravy atd.).</p>
    </div>
</div>

<script>
// --- Tabs Logic ---
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabId).style.display = 'block';
    event.currentTarget.classList.add('active');
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
}

// --- Chord Editor Logic ---
let currentChords = Array(6).fill('x');
function toggleFret(fret, string) {
    if (fret === 0) {
        if (currentChords[string] === '0') currentChords[string] = 'x';
        else if (currentChords[string] === 'x') currentChords[string] = null;
        else currentChords[string] = '0';
    } else {
        if (currentChords[string] == fret) currentChords[string] = '0';
        else currentChords[string] = fret;
    }
    updateEditorUI();
}
function updateEditorUI() {
    document.querySelectorAll('.dot').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.marker').forEach(el => { el.innerHTML = ''; el.className = 'marker'; });
    currentChords.forEach((val, s) => {
        if (val === '0') {
            const m = document.getElementById(`marker-0-${s}`);
            m.innerHTML = 'O'; m.classList.add('status-o');
        } else if (val === 'x' || val === null) {
            const m = document.getElementById(`marker-0-${s}`);
            m.innerHTML = 'X'; m.classList.add('status-x');
            currentChords[s] = 'x';
        } else {
            const d = document.getElementById(`dot-${val}-${s}`);
            if (d) d.classList.add('active');
        }
    });
    document.getElementById('chord-fingering-input').value = currentChords.join(',');
}
function resetEditor() { currentChords = Array(6).fill('x'); document.getElementById('chord-name-input').value = ''; updateEditorUI(); }
function editInEditor(name) {
    document.getElementById('chord-name-input').value = name;
    document.querySelector('.chord-editor-container').scrollIntoView({ behavior: 'smooth' });
}

// --- Requests Logic ---
function toggleRequestStatus(requestId, checkbox) {
    const item = checkbox.closest('.request-item');
    const list = document.getElementById('admin-requests-list');
    const status = checkbox.checked;
    
    item.classList.toggle('completed', status);
    if (status) list.appendChild(item);
    else list.prepend(item);

    fetch('api/update_request_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ requestId: requestId, status: status })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('Chyba: ' + data.message);
            checkbox.checked = !status;
            item.classList.toggle('completed', !status);
        }
    });
}

// Init
updateEditorUI();
</script>

<?php include 'includes/footer.php'; ?>
