</main>

<footer>
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
    let menuAutohideTimer = null;

    if (mobileMenuToggle && mainNavLinks) {
        const startMenuAutohide = () => {
            clearTimeout(menuAutohideTimer);
            menuAutohideTimer = setTimeout(() => {
                mainNavLinks.classList.remove('is-open');
            }, 3000);
        };

        const cancelMenuAutohide = () => {
            clearTimeout(menuAutohideTimer);
        };

        mobileMenuToggle.addEventListener('click', () => {
            const isOpen = mainNavLinks.classList.toggle('is-open');
            if (isOpen) {
                startMenuAutohide();
            } else {
                cancelMenuAutohide();
            }
        });

        mainNavLinks.addEventListener('click', (event) => {
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

<script>
    // Předání informace o STAVU ADMINA z PHP do JavaScriptu
    window.isUserAdmin = <?php echo json_encode(is_admin()); ?>;
</script>
<script src="assets/js/search-handler.js"></script>
<script src="assets/js/modal-handler.js"></script>
</body>
</html>