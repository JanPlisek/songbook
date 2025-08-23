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

</body>
</html>