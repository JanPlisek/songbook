</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Logika pro Tmavý / Světlý režim ---
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
    themeToggleButton?.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        let theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
    });

    // --- Logika pro Mobilní menu ---
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mainNavLinks = document.getElementById('main-nav-links');
    mobileMenuToggle?.addEventListener('click', () => {
        mainNavLinks.classList.toggle('is-open');
    });
});

const fullscreenButton = document.getElementById('fullscreen-btn');
if (fullscreenButton) {
    fullscreenButton.addEventListener('click', (event) => { // Přidali jsme "(event)"
        event.preventDefault(); // Zabráníme výchozí akci odkazu

        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                alert(`Chyba při pokusu o zapnutí režimu celé obrazovky: ${err.message}`);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    });
}
</script>


<div id="artist-songs-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content artist-songs-modal-content">
        <h2 id="modal-artist-name"></h2>
        <div id="modal-song-list"></div>
        <button id="modal-close-btn" class="btn-close-modal">Zavřít</button>
    </div>
</div>

<script>
    // Předání informace o stavu přihlášení z PHP do JavaScriptu
    window.isUserLoggedIn = <?php echo json_encode(is_user_logged_in()); ?>;
</script>
<script src="assets/js/modal-handler.js"></script>
</body>
</html>