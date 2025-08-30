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
            <li>
                <a href="list.php">
                    <span class="material-symbols-outlined">library_music</span>
                    <span>Rejstřík písní</span>
                </a>
            </li>
            <li>
                <a href="interprets.php">
                    <span class="material-symbols-outlined">mic_external_on</span>
                    <span>Interpreti</span>
                </a>
            </li>
            <li>
                <a href="random.php">
                    <span class="material-symbols-outlined">shuffle</span>
                    <span>Překvap mě</span>
                </a>
            </li>

            <?php if (is_admin()): ?>
                <li>
                    <a href="konverze.php">
                        <span class="material-symbols-outlined">add_link</span>
                        <span>Přidat z URL</span>
                    </a>
                </li>
                <li>
                    <a href="editor.php">
                        <span class="material-symbols-outlined">edit_document</span>
                        <span>Přidat ručně</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li>
                <a href="#" id="fullscreen-btn" title="Přepnout režim celé obrazovky">
                    <span class="material-symbols-outlined">fullscreen</span>
                    <span>Celá obrazovka</span>
                </a>
            </li>
            
            <li>
                <a href="#" id="theme-toggle-btn" title="Přepnout motiv">
                    <span class="material-symbols-outlined">contrast</span>
                    <span>Motiv</span>
                </a>
            </li>
            <li>
                <?php if (is_user_logged_in()): ?>
                    <a href="logout.php">
                        <span class="material-symbols-outlined">logout</span>
                        <span>Odhlásit</span>
                    </a>
                <?php else: ?>
                    <a href="login.php">
                        <span class="material-symbols-outlined">login</span>
                        <span>Přihlásit</span>
                    </a>
                <?php endif; ?>
            </li>
        </ul>

        <button id="mobile-menu-toggle" class="mobile-menu-button">
            <span class="material-symbols-outlined">menu</span>
        </button>
    </nav>
</header>

<main class="container">