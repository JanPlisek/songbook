<?php
$pageTitle = "Generátor hesla";
include 'includes/header.php';
$password_hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
}
?>

<div class="form-section" style="max-width: 600px; margin: 2rem auto;">
    <h1>Generátor nového hesla</h1>
    <p>Zadejte nové heslo, které chcete používat pro přihlášení.</p>
    <form action="" method="POST">
        <label for="password">Nové heslo:</label>
        <input type="text" id="password" name="password" style="margin-bottom: 1rem;" autofocus>
        <button type="submit">Vygenerovat hash</button>
    </form>
    <?php if ($password_hash): ?>
        <div style="margin-top: 2rem;">
            <h3>Nový hash pro vaše heslo:</h3>
            <p>Zkopírujte celý tento řetězec a vložte ho do souboru <code>login.php</code>.</p>
            <textarea readonly style="width: 100%; height: 80px; font-family: monospace; font-size: 1rem; padding: 10px;"><?php echo htmlspecialchars($password_hash); ?></textarea>
        </div>
    <?php endif; ?>
    <p style="margin-top: 2rem; color: #c00;"><strong>DŮLEŽITÉ:</strong> Po nastavení hesla tento soubor (<code>generuj_heslo.php</code>) ihned smažte!</p>
</div>

<?php include 'includes/footer.php'; ?>