<?php
// soubor: login.php (redesignovaná verze)
session_start();
require_once 'includes/functions.php';

// === HESLA (zůstává beze změny) ===
$verejny_hash_hesla = '$2y$10$EovckdVYOwjF4os9.nJlMeQLOAsvGArEdBpArmVjJsx9a8kt5ku5S';
$admin_hash_hesla = '$2y$10$eCZX.09CTqXSPXmWbsUhd.oX.M7oBj65MVNw44HOWJ6KYYSPgvFKG';

$chybova_hlaska = '';

if (is_user_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $zadane_heslo = $_POST['password'];
    
    if (password_verify($zadane_heslo, $admin_hash_hesla)) {
        $_SESSION['zpevnik_heslo_ok'] = true;
        $_SESSION['zpevnik_je_admin'] = true;
        header('Location: index.php');
        exit;
    } 
    elseif (password_verify($zadane_heslo, $verejny_hash_hesla)) {
        $_SESSION['zpevnik_heslo_ok'] = true;
        $_SESSION['zpevnik_je_admin'] = false;
        header('Location: index.php');
        exit;
    } else {
        $chybova_hlaska = 'Nesprávné heslo, zkuste to znovu.';
    }
}

// Nastavíme nové třídy pro body a titulek
$bodyClass = "login-page-body";
$pageTitle = "Přihlášení do zpěvníku";
// Hlavičku načteme, ale CSS ji skryje
include 'includes/header.php'; 
?>

<div class="login-wrapper">
    <div class="login-box">
        <h1 class="homepage-title">Přihlášení</h1>
        <p>Pro vstup do zpěvníku zadejte heslo.</p>
        
        <form action="login.php" method="POST" class="form-section">
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

<?php 
// Patičku zde nenahrajeme, aby se zachoval celoobrazovkový design
// include 'includes/footer.php'; 
?>
</body>
</html>