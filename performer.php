<?php require_once 'includes/gatekeeper.php'; ?>
<?php
// soubor: performer.php

// === INICIALIZAČNÍ BLOK ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/database.php';
require_once 'includes/functions.php';
// === KONEC BLOKU ===

$performer_id = $_GET['id'] ?? null;
$performer = null;
$songs = [];

if (!$performer_id) {
    // Pokud nemáme ID, nemá smysl pokračovat
    http_response_code(400); // Bad Request
    $pageTitle = "Chyba";
    include 'includes/header.php';
    echo "<div class='container message error'>Chyba: Nebylo zadáno ID interpreta.</div>";
    include 'includes/footer.php';
    exit;
}

// 1. Načteme informace o interpretovi
$stmt = $pdo->prepare("SELECT * FROM zp_performers WHERE id = ?");
$stmt->execute([$performer_id]);
$performer = $stmt->fetch();

if (!$performer) {
    // Interpret s daným ID neexistuje
    http_response_code(404); // Not Found
    $pageTitle = "Interpret nenalezen";
    include 'includes/header.php';
    echo "<div class='container message error'>Chyba: Interpret s ID " . htmlspecialchars($performer_id) . " nebyl nalezen.</div>";
    include 'includes/footer.php';
    exit;
}

// 2. Načteme všechny písně od tohoto interpreta
$stmt_songs = $pdo->prepare("
    SELECT s.id, s.title 
    FROM zp_songs s
    JOIN zp_song_performers sp ON s.id = sp.song_id
    WHERE sp.performer_id = ?
    ORDER BY s.title COLLATE utf8mb4_czech_ci
");
$stmt_songs->execute([$performer_id]);
$songs = $stmt_songs->fetchAll();

$pageTitle = $performer['name'];
include 'includes/header.php';
?>

<div class="container performer-page">
    <header class="performer-header">
        <img src="<?php echo htmlspecialchars(get_artist_image_path($performer['name'])); ?>" 
             alt="<?php echo htmlspecialchars($performer['name']); ?>" 
             class="performer-photo">
        
        <div class="performer-details">
            <h1><?php echo htmlspecialchars($performer['name']); ?></h1>
            <div class="performer-info">
                <?php 
                    // Vypisujeme HTML, jak je uloženo v databázi.
                    // Důvěřujeme obsahu, protože ho zadáváme pouze my jako administrátoři.
                    echo $performer['info'] ?? '<p><em>Zatím zde nejsou žádné informace.</em></p>'; 
                ?>
            </div>
            
            <?php if (is_admin()): ?>
                <a href="edit_performer.php?id=<?php echo $performer['id']; ?>" class="btn edit-performer-btn">
                    <span class="material-symbols-outlined">edit</span> Upravit profil
                </a>
            <?php endif; ?>
        </div>
    </header>

    <section class="performer-song-list">
        <h2>Písně (<?php echo count($songs); ?>)</h2>
        <?php if (empty($songs)): ?>
            <p>Pro tohoto interpreta nebyly nalezeny žádné písně.</p>
        <?php else: ?>
            <div class="song-list-columns">
                <?php foreach ($songs as $song): ?>
                    <div class="song-list-item-wrapper">
                        <a href="song.php?id=<?php echo htmlspecialchars($song['id']); ?>" class="song-list-item">
                            <span class="song-title"><?php echo htmlspecialchars($song['title']); ?></span>
                        </a>
                        <?php if (is_admin()): ?>
                            <a href="editor.php?id=<?php echo htmlspecialchars($song['id']); ?>" class="edit-link" title="Upravit píseň">
                                <span class="material-symbols-outlined">music_history</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>