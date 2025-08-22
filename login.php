<?php
// soubor: login.php
// !! session_start() musí být úplně první věc ve skriptu !!
session_start();

// --- Konfigurace ---
// Zde si definujeme přihlašovací údaje.
// HESLO JE ZDE ZAHASHOVANÉ! Nikdy ho neukládej v čitelné podobě.
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$Q6VDncwkamu3ueullJZpC.xzM0ahSoSfhgE/TVULt8ifO1owv3lgq'); // Toto je hash pro heslo "Zpevnik123"

// Pokud bys chtěl změnit heslo, použij tento kód pro vygenerování nového hashe:
// echo password_hash("NoveHeslo", PASSWORD_DEFAULT);
// A výsledný hash zkopíruj do konstanty ADMIN_PASSWORD_HASH.

$error_message = '';

// Zpracování formuláře, pokud byl odeslán
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Ověření jména a hesla
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        // Přihlášení bylo úspěšné, uložíme informaci do session
        $_SESSION['user_logged_in'] = true;
        
        // Přesměrujeme uživatele na hlavní stránku se seznamem písní
        header('Location: list.php');
        exit;
    } else {
        // Přihlášení se nezdařilo
        $error_message = 'Nesprávné jméno nebo heslo.';
    }
}

// Pokud je uživatel již přihlášen, přesměrujeme ho pryč z login stránky
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: list.php');
    exit;
}

$pageTitle = "Přihlášení";
// Použijeme hlavičku, ale bez navigačního menu, které by mátlo
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Songbook</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .login-container { max-width: 400px; margin: 5rem auto; padding: 2rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--header-bg); }
        .login-container h1 { text-align: center; margin-bottom: 1.5rem; }
        .login-container .form-group { margin-bottom: 1rem; }
        .login-container label { display: block; margin-bottom: 0.5rem; }
        .login-container input { width: 100%; padding: 0.8rem; border-radius: 5px; border: 1px solid var(--border-color); }
        .login-container .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 0.8rem; border-radius: 5px; margin-bottom: 1rem; text-align: center;}
        .login-container button { width: 100%; padding: 0.8rem; font-size: 1rem; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; }
        .login-container button:hover { background-color: var(--primary-hover-color); }
    </style>
</head>
<body>
    <main class="login-container">
        <h1>Přihlášení</h1>
        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Uživatelské jméno:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Heslo:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Přihlásit se</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>