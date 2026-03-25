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

    // --- NOVÁ VYLEPŠENÁ LOGIKA PRO MOBILNÍ MENU ---
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mainNavLinks = document.getElementById('main-nav-links');
    let menuAutohideTimer = null; // Zde budeme ukládat časovač

    if (mobileMenuToggle && mainNavLinks) {
        // Funkce pro spuštění časovače
        const startMenuAutohide = () => {
            clearTimeout(menuAutohideTimer); // Vždy zrušíme starý časovač
            menuAutohideTimer = setTimeout(() => {
                mainNavLinks.classList.remove('is-open');
            }, 3000); // Zavře se po 3 sekundách
        };

        // Funkce pro zrušení časovače
        const cancelMenuAutohide = () => {
            clearTimeout(menuAutohideTimer);
        };

        // Při kliknutí na hamburger tlačítko
        mobileMenuToggle.addEventListener('click', () => {
            const isOpen = mainNavLinks.classList.toggle('is-open');
            if (isOpen) {
                startMenuAutohide(); // Pokud se menu otevřelo, spustíme časovač
            } else {
                cancelMenuAutohide(); // Pokud se zavřelo, zrušíme časovač
            }
        });

        // Pokud uživatel interaguje s menu, restartujeme časovač
        mainNavLinks.addEventListener('click', (event) => {
            // Pokud se nekliklo přímo na odkaz, zastavíme zavření
            // a restartujeme časovač
            if (event.target.tagName !== 'A') {
                 event.stopPropagation();
            }
            startMenuAutohide();
        });
        mainNavLinks.addEventListener('touchstart', startMenuAutohide, { passive: true });
    }
});

// --- Logika pro Fullscreen ---
document.querySelectorAll('#fullscreen-btn, .fullscreen-toggle-btn').forEach(btn => {
    btn.addEventListener('click', (event) => {
        event.preventDefault();
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
});
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

<div id="support-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Podpořit zpěvník</h3>
        <p>Pokud se vám zpěvník líbí, můžete mě podpořit libovolnou částkou. Děkuji!</p>
        
        <div class="amount-selector">
            <button data-amount="10" class="active">10 Kč</button>
            <button data-amount="20">20 Kč</button>
            <button data-amount="50">50 Kč</button>
        </div>

        <div id="support-qr-code-container">
            <div id="support-qr-code"></div>
            <p id="qr-code-placeholder">Zvolte částku pro vygenerování QR kódu.</p>
        </div>

        <div class="support-revolut-link">
            <span>Nebo přes Revolut:</span>
            <a href="https://revolut.me/jplisek78" target="_blank" class="btn">Revolut Pay</a>
        </div>
        
        <button id="btn-close-support-modal" class="btn-close-modal">Zavřít</button>
    </div>
</div>

<script>
    // Předání informace o STAVU ADMINA z PHP do JavaScriptu
    window.isUserAdmin = <?php echo json_encode(is_admin()); ?>;
</script>
<script src="assets/js/search-handler.js"></script>
<script src="assets/js/modal-handler.js"></script>
<script src="assets/js/request-handler.js"></script>
<script src="assets/js/support-handler.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js')
            .then(reg => console.log('Service Worker registered', reg))
            .catch(err => console.error('Service Worker registration failed', err));
    });
}
</script>
</body>
</html>