<?php
// soubor: login.php (redesignovaná verze)
session_start();
require_once 'includes/functions.php';

// === HESLA (zůstává beze změny) ===
$verejny_hash_hesla = '$2y$10$EovckdVYOwjF4os9.nJlMeQLOAsvGArEdBpArmVjJsx9a8kt5ku5S';
$admin_hash_hesla = '$2y$10$EVSCHdg8.saiw5G6mIpOG.YBe1c9dtNOApaXWji3b.UbPZMuOlugu';

$chybova_hlaska = '';

// Zjistíme, zda nám strážce poslal nějakou adresu k přesměrování
$redirect_url = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if (is_user_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $zadane_heslo = $_POST['password'];
    
    $prihlaseni_uspesne = false;
    if (password_verify($zadane_heslo, $admin_hash_hesla)) {
        $_SESSION['zpevnik_heslo_ok'] = true;
        $_SESSION['zpevnik_je_admin'] = true;
        $prihlaseni_uspesne = true;
    } 
    elseif (password_verify($zadane_heslo, $verejny_hash_hesla)) {
        $_SESSION['zpevnik_heslo_ok'] = true;
        $_SESSION['zpevnik_je_admin'] = false;
        $prihlaseni_uspesne = true;
    }

    if ($prihlaseni_uspesne) {
        // Pokud máme zapamatovanou adresu, přesměrujeme tam.
        // Pro bezpečnost ověříme, že je to platná URL.
        if (!empty($redirect_url) && filter_var($redirect_url, FILTER_VALIDATE_URL)) {
            header('Location: ' . $redirect_url);
        } else {
            // Jinak přesměrujeme na úvodní stránku jako obvykle.
            header('Location: index.php');
        }
        exit;
    } else {
        $chybova_hlaska = 'Nesprávné heslo, zkuste to znovu.';
    }
}

$bodyClass = "login-page-body";
$pageTitle = "Přihlášení do zpěvníku";
include 'includes/header.php'; 
?>

<div class="login-wrapper">
    <div class="login-box">
        <h1 class="homepage-title">Přihlášení</h1>
        <p>Pro vstup do zpěvníku zadejte heslo.</p>
        
        <form action="login.php" method="POST" class="form-section">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">

            <input type="password" id="password" name="password" required autofocus placeholder="Zadejte heslo...">
            <button type="submit">Vstoupit</button>

            <?php if (!empty($chybova_hlaska)): ?>
                <div class="message error" style="margin-top: 1.5rem; color: white; background-color: rgba(217, 4, 4, 0.5);">
                    <?php echo $chybova_hlaska; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

</body>
</html>