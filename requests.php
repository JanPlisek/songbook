<?php
// soubor: requests.php
session_start();

require_once 'includes/database.php';
require_once 'includes/functions.php';

// Tato stránka je pouze pro administrátory
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Načteme všechny požadavky z databáze
// Seřadíme je tak, aby nahoře byly nevyřízené (is_completed = 0)
// a v rámci toho seřadíme od nejnovějších po nejstarší.
$stmt = $pdo->query("
    SELECT * FROM zp_requests 
    ORDER BY is_completed ASC, created_at DESC
");
$requests = $stmt->fetchAll();

$pageTitle = "Požadavky na písně";
include 'includes/header.php';
?>

<div class="container requests-page">
    <h1>Požadavky na písně</h1>
    <p>Zde je seznam písní, které si uživatelé přejí přidat do zpěvníku.</p>

    <div class="requests-list">
        <?php if (empty($requests)): ?>
            <p>Zatím zde nejsou žádné požadavky.</p>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <?php 
                    // Přidáme třídu 'completed', pokud je požadavek vyřízený
                    $item_class = $request['is_completed'] ? 'request-item completed' : 'request-item'; 
                ?>
                <div class="<?php echo $item_class; ?>" data-id="<?php echo $request['id']; ?>">
                    <div class="request-checkbox">
                        <input type="checkbox" 
                               id="request-<?php echo $request['id']; ?>"
                               <?php if ($request['is_completed']) echo 'checked'; ?>>
                    </div>
                    <div class="request-details">
                        <div class="request-main-info">
                            <h3><?php echo htmlspecialchars($request['song_title']); ?></h3>
                            <p class="requester-info">
                                Od: <strong><?php echo htmlspecialchars($request['requester_name'] ?: 'Anonym'); ?></strong>
                                <em>(<?php echo date('j. n. Y H:i', strtotime($request['created_at'])); ?>)</em>
                            </p>
                        </div>
                        <?php if (!empty($request['note'])): ?>
                            <p class="request-note"><?php echo nl2br(htmlspecialchars($request['note'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestsList = document.querySelector('.requests-list');

    if (requestsList) {
        requestsList.addEventListener('change', function(event) {
            // Reagujeme pouze na změnu u checkboxu
            if (event.target.type === 'checkbox') {
                const checkbox = event.target;
                const requestItem = checkbox.closest('.request-item');
                const requestId = requestItem.dataset.id;
                const newStatus = checkbox.checked;

                // 1. Okamžitá vizuální změna pro lepší uživatelský zážitek
                requestItem.classList.toggle('completed', newStatus);
                
                // 2. Živé přeuspořádání položky v seznamu
                if (newStatus) {
                    // Byl zaškrtnut jako hotový -> přesunout na konec seznamu
                    requestsList.appendChild(requestItem);
                } else {
                    // Byl odškrtnut -> přesunout na začátek seznamu
                    requestsList.prepend(requestItem);
                }

                // 2. Odeslání změny na server na pozadí
                fetch('api/update_request_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        requestId: requestId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        // Pokud server vrátí chybu, vrátíme vizuální změnu zpět a upozorníme uživatele
                        alert('Chyba při ukládání stavu: ' + (data.message || 'Neznámá chyba'));
                        checkbox.checked = !newStatus;
                        requestItem.classList.toggle('completed', !newStatus);
                    }
                    // Pokud je vše v pořádku, neděláme nic, změna je již vizuálně provedena.
                })
                .catch(error => {
                    alert('Došlo k technické chybě. Zkuste to prosím znovu.');
                    checkbox.checked = !newStatus;
                    requestItem.classList.toggle('completed', !newStatus);
                });
            }
        });
    }
});
</script>