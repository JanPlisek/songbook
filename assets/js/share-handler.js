document.addEventListener('DOMContentLoaded', function() {
    // Najdeme všechny prvky, které budeme potřebovat
    const shareMenuItem = document.getElementById('share-menu-item');
    const shareMenuItemMobile = document.getElementById('share-menu-item-mobile');
    const shareModal = document.getElementById('share-modal');
    const closeShareModalBtn = document.getElementById('btn-close-share-modal');
    const qrCodeContainer = document.getElementById('qr-code-container');
    const btnCopyLink = document.getElementById('btn-copy-link');
    const btnNativeShare = document.getElementById('btn-native-share');
    const copySuccessMessage = document.getElementById('copy-link-success');

    // Zjistíme, jestli jsme na stránce s písní (podle přítomnosti #song-content)
    const isSongPage = document.getElementById('song-content') !== null;
    const currentUrl = window.location.href;
    const songTitle = document.querySelector('.song-header h1')?.textContent || 'Písnička';

    // --- 1. Zobrazení tlačítek a inicializace ---
    if (isSongPage) {
        // Pokud jsme na stránce písně, zobrazíme tlačítka pro sdílení
        if (shareMenuItem) shareMenuItem.style.display = 'list-item';
        if (shareMenuItemMobile) shareMenuItemMobile.style.display = 'flex';

        // Funkce, která se spustí po kliknutí na jakékoliv sdílecí tlačítko
        const openShareModal = (event) => {
            event.preventDefault();
            
            // Vyčistíme starý QR kód, pokud tam je
            qrCodeContainer.innerHTML = '';
            
            // Vytvoříme nový QR kód pomocí knihovny
            new QRCode(qrCodeContainer, {
                text: currentUrl,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            shareModal.style.display = 'flex';
        };

        // Připojíme funkci na obě tlačítka (desktop i mobilní)
        shareMenuItem.addEventListener('click', openShareModal);
        shareMenuItemMobile.addEventListener('click', openShareModal);
    }

    // --- 2. Ovládání modálního okna a akcí v něm ---
    if (shareModal) {
        // Funkce pro zavření modálního okna
        const closeModal = () => shareModal.style.display = 'none';
        closeShareModalBtn.addEventListener('click', closeModal);
        shareModal.addEventListener('click', (e) => {
            if (e.target === shareModal) closeModal();
        });

        // Akce pro tlačítko "Zkopírovat odkaz"
        btnCopyLink.addEventListener('click', () => {
            navigator.clipboard.writeText(currentUrl).then(() => {
                copySuccessMessage.style.display = 'block';
                setTimeout(() => { copySuccessMessage.style.display = 'none'; }, 2000);
            }).catch(err => {
                alert('Nepodařilo se zkopírovat odkaz.');
            });
        });

        // Akce pro tlačítko "Sdílet přes..." (otevře nativní dialog v telefonu/prohlížeči)
        // Schováme tlačítko, pokud prohlížeč tuto funkci nepodporuje
        if (navigator.share) {
            btnNativeShare.addEventListener('click', async () => {
                try {
                    await navigator.share({
                        title: `Zpěvník: ${songTitle}`,
                        text: `Podívej se na písničku ${songTitle}`,
                        url: currentUrl,
                    });
                } catch (err) {
                    console.error("Chyba při sdílení:", err);
                }
            });
        } else {
            btnNativeShare.style.display = 'none';
        }
    }
});