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
</script>

</body>
</html>