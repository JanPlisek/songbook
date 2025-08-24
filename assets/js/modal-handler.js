// soubor: assets/js/modal-handler.js (S DIAGNOSTICKÝM VÝPISEM)

document.addEventListener('DOMContentLoaded', function() {
    console.log('Modal handler script successfully started.'); // VÝPIS 1: Potvrzení, že skript běží.

    const isUserLoggedIn = window.isUserLoggedIn || false; 

    const modal = document.getElementById('artist-songs-modal');
    const modalArtistName = document.getElementById('modal-artist-name');
    const modalSongList = document.getElementById('modal-song-list');
    const modalCloseBtn = document.getElementById('modal-close-btn');

    console.log('Searching for modal element:', modal); // VÝPIS 2: Ukáže nám, zda byl nalezen HTML prvek modálního okna.

    if (!modal) {
        console.error('Modal element #artist-songs-modal NOT FOUND! Script will stop.');
        return;
    }

    const openModal = (performerId, performerName) => {
        if (!performerId || !performerName) return;

        // Vytvoříme odkaz, který povede na novou stránku performer.php
        const performerLink = `<a href="performer.php?id=${performerId}" class="modal-title-link">${performerName}</a>`;

        // Místo textu vložíme do nadpisu celý HTML odkaz
        modalArtistName.innerHTML = performerLink;
        
        modalSongList.innerHTML = '<p>Načítám...</p>';
        modal.style.display = 'flex';

        fetch(`api/get_songs_by_performer.php?id=${performerId}`)
            .then(response => {
                if (!response.ok) { throw new Error(`Chyba serveru: ${response.status}`); }
                return response.json();
            })
            .then(songs => {
                if (songs.error) { throw new Error(songs.error); }
                
                let html = '';
                if (songs.length > 0) {
                    html = '<div class="song-list modal-song-list">';
                    songs.forEach(song => {
                        html += `<div class="song-list-item-wrapper">
                                    <a href="song.php?id=${song.id}" class="song-list-item">
                                        <span class="song-title">${song.title}</span>
                                    </a>`;
                        if (isUserLoggedIn) {
                            html += `<a href="editor.php?id=${song.id}" class="edit-link" title="Upravit píseň">
                                        <span class="material-symbols-outlined">music_history</span>
                                    </a>`;
                        }
                        html += `</div>`;
                    });
                    html += '</div>';
                } else {
                    html = '<p>Pro tohoto interpreta nebyly nalezeny žádné písně.</p>';
                }
                modalSongList.innerHTML = html;
            })
            .catch(error => {
                modalSongList.innerHTML = `<div class="message error"><strong>Nastala chyba:</strong><br>${error.message}</div>`;
            });
    };

    const closeModal = () => {
        modal.style.display = 'none';
    };

    document.body.addEventListener('click', function(event) {
        const triggerElement = event.target.closest('.js-show-performer-modal');
        
        if (triggerElement) {
            event.preventDefault();
            const performerId = triggerElement.dataset.performerId;
            const performerName = triggerElement.dataset.performerName;
            openModal(performerId, performerName);
        }
    });

    modalCloseBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
});