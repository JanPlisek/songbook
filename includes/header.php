<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | Songbook' : 'Songbook'; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Martian+Mono:wght@400;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/song.css"> 
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">

<header>
    <nav class="main-nav">
        <a href="index.php" class="nav-brand">ZPĚVNÍK</a>
        
        <ul id="main-nav-links">
            <li><a href="list.php">Rejstřík písní</a></li>
            <li><a href="interprets.php">Interpreti</a></li>
            <li><a href="random.php">Překvap mě</a></li>
            <?php if (is_user_logged_in()): // Zobrazí se jen po přihlášení ?>
                <li><a href="konverze.php">Přidat z URL</a></li>
                <li><a href="editor.php">Editor</a></li>
            <?php endif; ?>
            <li>
                <button id="theme-toggle-btn" class="theme-toggle-button" title="Přepnout motiv">
                    <span class="material-symbols-outlined">contrast</span>
                </button>
            </li>
            <li>
                <button id="fullscreen-btn" class="theme-toggle-button" title="Celá obrazovka">
                    <span class="material-symbols-outlined">fullscreen</span>
                </button>
            </li>
            
            <li>
                <?php if (is_user_logged_in()): ?>
                    <a href="logout.php">Odhlásit</a>
                <?php else: ?>
                    <a href="login.php">Přihlásit</a>
                <?php endif; ?>
            </li>
        </ul>

        <button id="mobile-menu-toggle" class="mobile-menu-button">
            <span class="material-symbols-outlined">menu</span>
        </button>
    </nav>
</header>

<main class="container">