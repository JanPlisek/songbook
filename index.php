<?php
// --- DŮLEŽITÉ DOPLNĚNÍ PRO INDEX.PHP ---
// Protože tato stránka nepoužívá viditelnou hlavičku, musíme zde ručně spustit session
// a načíst si naši pomocnou funkci pro kontrolu přihlášení.
session_start();
include_once 'includes/functions.php'; // Použijeme include_once pro jistotu

$bodyClass = "homepage-body"; 
$pageTitle = "Vítejte";
// Hlavičku sice načítáme, ale CSS ji skryje. Děláme to proto, aby se zachovala struktura a načetly se CSS soubory.
include 'includes/header.php'; 
?>

<div class="homepage-auth-link">
    <?php if (is_user_logged_in()): ?>
        <a href="logout.php">Odhlásit</a>
    <?php else: ?>
        <a href="login.php">Přihlásit</a>
    <?php endif; ?>
</div>

<div class="homepage-container">
    <div class="search-wrapper">
    <h1 class="homepage-title">Zpěvník</h1>
    <div>
        <div class="search-box">
            <input type="text" id="search-input" placeholder="Hledat písničku nebo interpreta...">
            <div id="search-results" class="search-results-dropdown"></div>
        </div>
        <div class="homepage-links">
            <a href="list.php" class="btn">Seznam písní</a>
            <a href="interprets.php" class="btn">Seznam interpretů</a>
        </div>
    </div>
    </div>
</div>

<style>
/* Styl pro přihlašovací odkaz v rohu */
.homepage-auth-link {
    position: absolute;
    top: 20px;
    right: 25px;
    z-index: 10;
}
.homepage-auth-link a {
    color: white;
    text-decoration: none;
    padding: 10px 15px;
    background-color: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
    transition: background-color 0.2s;
}
.homepage-auth-link a:hover {
    background-color: rgba(0, 0, 0, 0.6);
}
</style>

<?php 
// Patičku zde nenačítáme, abychom zachovali design
// include 'includes/footer.php'; 
?>
<script>
// ... Tvůj stávající JavaScript pro vyhledávání zde zůstává beze změny ...
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    let activeIndex = -1;

    searchInput.addEventListener('input', function() {
        const query = this.value;
        if (query.length < 2) { hideResults(); return; }
        fetch(`search-api.php?q=${encodeURIComponent(query)}&_=${new Date().getTime()}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '<ul>';
                    data.forEach((song, index) => {
                        const artists = song.artist || [];
                        const bands = song.band || [];
                        const performers = [...artists, ...bands].join(', ');

                        html += `<li data-index="${index}"><a href="song.php?id=${song.id}">
                                    <strong>${song.title}</strong> - ${performers}
                                 </a></li>`;
                    });
                    html += '</ul>';
                    searchResults.innerHTML = html;
                    showResults();
                    document.querySelectorAll('.search-results-dropdown li').forEach(item => {
                        item.addEventListener('mouseover', () => {
                            activeIndex = parseInt(item.dataset.index);
                            updateActiveSuggestion();
                        });
                    });
                } else {
                    searchResults.innerHTML = '<div class="no-results">Žádné výsledky</div>';
                    showResults();
                }
            });
    });

    searchInput.addEventListener('keydown', function(e) { const items = searchResults.querySelectorAll('li'); if (items.length === 0) return; switch (e.key) { case 'ArrowDown': e.preventDefault(); activeIndex++; if (activeIndex >= items.length) activeIndex = 0; updateActiveSuggestion(); break; case 'ArrowUp': e.preventDefault(); activeIndex--; if (activeIndex < 0) activeIndex = items.length - 1; updateActiveSuggestion(); break; case 'Enter': e.preventDefault(); if (activeIndex > -1) { window.location.href = items[activeIndex].querySelector('a').href; } break; case 'Escape': hideResults(); break; } });
    function updateActiveSuggestion() { const items = searchResults.querySelectorAll('li'); items.forEach((item, index) => { item.classList.toggle('active', index === activeIndex); }); }
    function showResults() { searchResults.style.display = 'block'; activeIndex = -1; }
    function hideResults() { searchResults.innerHTML = ''; searchResults.style.display = 'none'; activeIndex = -1; }
    document.addEventListener('click', function(event) { if (!searchInput.contains(event.target)) { hideResults(); } });
});
</script>
</body>
</html>