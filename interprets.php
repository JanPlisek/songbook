<?php
// soubor: interprets.php (FINÁLNÍ VERZE PRO DATABÁZI)

// === INICIALIZAČNÍ BLOK ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/database.php';
require_once 'includes/functions.php';
// === KONEC BLOKU ===

$pageTitle = "Interpreti";
include 'includes/header.php';

// Zjistíme, jaký typ řazení si uživatel vybral (výchozí je podle jména)
$sort_mode = $_GET['sort'] ?? 'firstname';

// --- NOVÉ NAČÍTÁNÍ DAT Z DATABÁZE ---
// Připravíme si část SQL dotazu pro řazení
$order_sql = "ORDER BY p.name COLLATE utf8mb4_czech_ci"; // Výchozí řazení
if ($sort_mode === 'lastname') {
    // Složitější řazení: pokud je to osoba a má příjmení, řadíme podle něj, jinak podle jména
    $order_sql = "ORDER BY
                    CASE
                        WHEN p.type = 'person' AND p.last_name IS NOT NULL AND p.last_name != '' THEN p.last_name
                        ELSE p.name
                    END COLLATE utf8mb4_czech_ci";
}

// Hlavní SQL dotaz, který načte všechny interprety a rovnou spočítá jejich písně
$sql = "SELECT 
            p.id, p.type, p.name, p.first_name, p.last_name,
            COUNT(sp.song_id) as song_count
        FROM 
            zp_performers p
        LEFT JOIN 
            zp_song_performers sp ON p.id = sp.performer_id
        GROUP BY 
            p.id
        $order_sql";

$stmt = $pdo->query($sql);
$performers_list = $stmt->fetchAll();

// Seskupíme interprety podle počátečního písmene pro abecední filtr
$grouped_interprets = [];
foreach ($performers_list as $performer) {
    // Zjistíme, podle jakého jména se má řadit do skupin
    $name_to_check = $performer['name'];
    if ($sort_mode === 'lastname' && $performer['type'] === 'person' && !empty($performer['last_name'])) {
        $name_to_check = $performer['last_name'];
    }
    
    $first_letter = get_czech_first_letter($name_to_check);
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

    <div id="interprets-grid-grouped">
         <?php if (empty($grouped_interprets)): ?>
            <p>V seznamu zatím nejsou žádní interpreti.</p>
        <?php else: ?>
            <?php foreach ($grouped_interprets as $letter => $interprets_in_group): ?>
                <div class="interpret-group" data-group-letter="<?php echo htmlspecialchars($letter); ?>">
                    <h2 class="letter-heading"><?php echo htmlspecialchars($letter); ?></h2>
                    <div class="interprets-grid">
                        <?php foreach ($interprets_in_group as $interpret): ?>
                            <a href="#" 
                               class="interpret-card js-show-performer-modal" 
                               data-performer-id="<?php echo htmlspecialchars($interpret['id']); ?>"
                               data-performer-name="<?php echo htmlspecialchars($interpret['name']); ?>">
                                
                                <img src="<?php echo htmlspecialchars(get_artist_image_path($interpret['name'])); ?>" alt="<?php echo htmlspecialchars($interpret['name']); ?>">
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

<?php include 'includes/footer.php'; ?>

<script>
// Tento JavaScript řeší pouze filtrování na této konkrétní stránce
document.addEventListener('DOMContentLoaded', function() {
    const textFilterInput = document.getElementById('text-filter-input');
    const alphaButtons = document.querySelectorAll('.alpha-btn');
    const interpretGroups = document.querySelectorAll('.interpret-group');

    // Filtrování textem
    if(textFilterInput) {
        textFilterInput.addEventListener('input', function() {
            const filterText = this.value.toLowerCase().trim();
            const allBtn = document.querySelector('.alpha-btn[data-letter="all"]');
            
            alphaButtons.forEach(btn => btn.classList.remove('active'));
            if(allBtn) allBtn.classList.add('active');

            interpretGroups.forEach(group => {
                let groupHasVisibleCards = false;
                group.querySelectorAll('.interpret-card').forEach(card => {
                    const performerName = card.dataset.performerName.toLowerCase();
                    if (performerName.includes(filterText)) {
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

    // Filtrování abecedou
    alphaButtons.forEach(button => {
        button.addEventListener('click', function() {
            const selectedLetter = this.dataset.letter;
            if(textFilterInput) textFilterInput.value = '';
            
            alphaButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            interpretGroups.forEach(group => {
                group.querySelectorAll('.interpret-card').forEach(card => card.style.display = 'block');
                if (selectedLetter === 'all' || group.dataset.groupLetter === selectedLetter) {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    });
});
</script>