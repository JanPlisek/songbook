document.addEventListener('DOMContentLoaded', function() {
    const showModalBtn = document.getElementById('btn-show-request-modal');
    const requestModal = document.getElementById('song-request-modal');
    // Ujistíme se, že hledáme zavírací tlačítko uvnitř správného modálního okna
    const closeModalBtn = document.querySelector('#song-request-modal .btn-close-modal');
    const requestForm = document.getElementById('form-song-request');
    
    // Tuto proměnnou si schováme, abychom mohli formulář po úspěchu obnovit
    let originalModalContent = null;
    if (requestModal) {
        originalModalContent = requestModal.querySelector('.modal-content').innerHTML;
    }

    // Pokud na stránce nejsou potřebné prvky, nic dalšího neděláme
    if (!showModalBtn || !requestModal || !requestForm || !closeModalBtn) {
        return;
    }

    const openModal = () => {
        // Vždy obnovíme původní obsah, kdyby se okno zavřelo křížkem po úspěšné zprávě
        requestModal.querySelector('.modal-content').innerHTML = originalModalContent;
        // Musíme znovu "najít" formulář a tlačítko uvnitř obnoveného obsahu
        document.getElementById('form-song-request').addEventListener('submit', handleFormSubmit);
        document.querySelector('#song-request-modal .btn-close-modal').addEventListener('click', closeModal);
        requestModal.style.display = 'flex';
    };

    const closeModal = () => {
        requestModal.style.display = 'none';
        // Formulář se automaticky resetuje při příštím otevření
    };

    const handleFormSubmit = function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.textContent = 'Odesílám...';
        submitButton.disabled = true;
        
        fetch('api/submit_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modalContent = requestModal.querySelector('.modal-content');
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 2rem 0;">
                        <span class="material-symbols-outlined" style="font-size: 3.5rem; color: green;">check_circle</span>
                        <h3>Děkujeme!</h3>
                        <p>Váš požadavek byl úspěšně zaznamenán.</p>
                        <p><small>Okno se za 3 sekundy samo zavře.</small></p>
                    </div>
                `;
                
                setTimeout(() => {
                    closeModal();
                }, 3000); // 3000 milisekund = 3 sekundy

            } else {
                throw new Error(data.message || 'Neznámá chyba při odesílání.');
            }
        })
        .catch(error => {
            alert('Chyba: ' + error.message);
            // Obnovíme tlačítko do původního stavu
            submitButton.textContent = 'Odeslat požadavek';
            submitButton.disabled = false;
        });
    };

    showModalBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);
    requestModal.addEventListener('click', (e) => {
        if (e.target === requestModal) {
            closeModal();
        }
    });

    requestForm.addEventListener('submit', handleFormSubmit);
});