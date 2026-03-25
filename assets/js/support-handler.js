document.addEventListener('DOMContentLoaded', function() {
    // --- NASTAVENÍ ---
    const iban = 'CZ8430300000001277429023'; // !!! ZDE JE VLOŽEN VÁŠ IBAN !!!

    // --- PRVKY ---
    const showModalBtn = document.getElementById('btn-show-support-modal');
    const supportModal = document.getElementById('support-modal');
    const closeModalBtn = document.getElementById('btn-close-support-modal');
    const qrCodeContainer = document.getElementById('support-qr-code');
    const qrPlaceholder = document.getElementById('qr-code-placeholder');
    const amountButtons = document.querySelectorAll('.amount-selector button');

    if (!showModalBtn || !supportModal) return;

    // --- FUNKCE ---
    const generateQrCode = (amount) => {
        // Formát pro české bankovní QR platby (Short Payment Descriptor)
        const spdString = `SPD*1.0*ACC:${iban}*AM:${amount}.00*CC:CZK*MSG:Podpora-zpevniku`;
        
        qrPlaceholder.style.display = 'none'; // Skryjeme text
        qrCodeContainer.innerHTML = ''; // Vyčistíme starý kód
        
        new QRCode(qrCodeContainer, {
            text: spdString,
            width: 200,
            height: 200,
        });
    };

    const openModal = () => {
        supportModal.style.display = 'flex';
        // Po otevření rovnou vygenerujeme kód pro aktivní tlačítko
        const activeAmount = document.querySelector('.amount-selector button.active').dataset.amount;
        generateQrCode(activeAmount);
    };

    const closeModal = () => {
        supportModal.style.display = 'none';
    };

    // --- PŘIŘAZENÍ UDÁLOSTÍ ---
    showModalBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);
    supportModal.addEventListener('click', (e) => {
        if (e.target === supportModal) closeModal();
    });

    amountButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Zrušíme aktivní stav u všech tlačítek
            amountButtons.forEach(btn => btn.classList.remove('active'));
            // Nastavíme aktivní stav jen kliknutému tlačítku
            button.classList.add('active');
            // Vygenerujeme nový QR kód
            generateQrCode(button.dataset.amount);
        });
    });
});