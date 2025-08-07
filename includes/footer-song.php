</main>

<footer>
    <?php echo htmlspecialchars($song_data['title']); ?> / <?php echo htmlspecialchars(implode(', ', $song_data['artist'])); ?>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    const currentTheme = localStorage.getItem('theme');

    // Při načtení stránky aplikujeme uložené téma
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Funkce pro přepnutí a uložení tématu
    themeToggleButton?.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        let theme = 'light';
        if (document.body.classList.contains('dark-mode')) {
            theme = 'dark';
        }
        localStorage.setItem('theme', theme);
    });
});
</script>
</body>
</html>