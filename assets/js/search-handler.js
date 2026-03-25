document.addEventListener('DOMContentLoaded', function () {
    const searchToggleBtn = document.getElementById('search-toggle-btn');
    const searchToggleBtnMobile = document.getElementById('search-toggle-btn-mobile');
    const searchBarContainer = document.getElementById('search-bar-container');
    const searchCloseBtn = document.getElementById('search-close-btn');
    const headerSearchInput = document.getElementById('header-search-input');
    const headerSearchResults = document.getElementById('header-search-results');
    const mainNavLinks = document.getElementById('main-nav-links');

    let activeIndex = -1;

    function toggleSearch(show) {
        if (show === undefined) {
            show = !searchBarContainer.classList.contains('active');
        }

        if (show) {
            searchBarContainer.classList.add('active');
            headerSearchInput.focus();
            // Pokud je otevřené mobilní menu, zavřeme ho
            if (mainNavLinks) {
                mainNavLinks.classList.remove('is-open');
            }
        } else {
            searchBarContainer.classList.remove('active');
            headerSearchInput.value = '';
            headerSearchResults.innerHTML = '';
            headerSearchResults.style.display = 'none';
        }
    }

    if (searchToggleBtn) {
        searchToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSearch();
        });
    }

    if (searchToggleBtnMobile) {
        searchToggleBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSearch();
        });
    }

    if (searchCloseBtn) {
        searchCloseBtn.addEventListener('click', () => {
            toggleSearch(false);
        });
    }

    // Skrytí při kliknutí mimo
    document.addEventListener('click', (e) => {
        if (!searchBarContainer.contains(e.target) &&
            !searchToggleBtn?.contains(e.target) &&
            !searchToggleBtnMobile?.contains(e.target)) {
            toggleSearch(false);
        }
    });

    // ESC pro zavření
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && searchBarContainer.classList.contains('active')) {
            toggleSearch(false);
        }
    });

    // Logika vyhledávání (inspirováno index.php)
    headerSearchInput.addEventListener('input', function () {
        const query = this.value;
        if (query.length < 2) {
            headerSearchResults.innerHTML = '';
            headerSearchResults.style.display = 'none';
            return;
        }

        fetch(`search-api.php?q=${encodeURIComponent(query)}&_=${new Date().getTime()}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '<ul>';
                    data.forEach((song, index) => {
                        const performerText = song.performers ? ` - ${song.performers}` : '';
                        html += `<li data-index="${index}">
                                    <a href="song.php?id=${song.id}">
                                        <strong>${song.title}</strong>${performerText}
                                    </a>
                                 </li>`;
                    });
                    html += '</ul>';
                    headerSearchResults.innerHTML = html;
                    headerSearchResults.style.display = 'block';
                    activeIndex = -1;

                    // Mouseover pro aktivní položku
                    headerSearchResults.querySelectorAll('li').forEach(item => {
                        item.addEventListener('mouseover', () => {
                            activeIndex = parseInt(item.dataset.index);
                            updateActiveSuggestion();
                        });
                    });
                } else {
                    headerSearchResults.innerHTML = '<div class="no-results">Žádné výsledky</div>';
                    headerSearchResults.style.display = 'block';
                }
            });
    });

    headerSearchInput.addEventListener('keydown', function (e) {
        const items = headerSearchResults.querySelectorAll('li');
        if (items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeIndex++;
                if (activeIndex >= items.length) activeIndex = 0;
                updateActiveSuggestion();
                break;
            case 'ArrowUp':
                e.preventDefault();
                activeIndex--;
                if (activeIndex < 0) activeIndex = items.length - 1;
                updateActiveSuggestion();
                break;
            case 'Enter':
                e.preventDefault();
                if (activeIndex > -1) {
                    window.location.href = items[activeIndex].querySelector('a').href;
                }
                break;
        }
    });

    function updateActiveSuggestion() {
        const items = headerSearchResults.querySelectorAll('li');
        items.forEach((item, index) => {
            item.classList.toggle('active', index === activeIndex);
        });
    }
});
