</main>

<footer>
    <p>&copy; <?php echo date("Y"); ?> ZPĚVNÍK, Jan Plíšek</p>
</footer>

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

<div id="song-request-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Požadavek na přidání písně</h3>
        <form id="form-song-request" class="form-section" style="border: none; padding: 0;">
            <fieldset>
                <label for="req-song-title">Název písně (a případně interpret):</label>
                <input type="text" id="req-song-title" name="song_title" required>

                <label for="req-requester-name">Vaše jméno nebo přezdívka:</label>
                <input type="text" id="req-requester-name" name="requester_name">

                <label for="req-note">Poznámka (např. odkaz na text):</label>
                <textarea id="req-note" name="note" rows="3"></textarea>

                <button type="submit">Odeslat požadavek</button>
            </fieldset>
        </form>
        <button id="btn-close-request-modal" class="btn-close-modal">Zrušit</button>
    </div>
</div>

<script>
    // Předání informace o STAVU ADMINA z PHP do JavaScriptu
    window.isUserAdmin = <?php echo json_encode(is_admin()); ?>;
</script>
<script src="assets/js/modal-handler.js"></script>
<script src="assets/js/request-handler.js"></script>
</body>
</html>