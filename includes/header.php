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
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c67701">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Zpěvník">
    <link rel="apple-touch-icon" href="assets/img/icon-192.png">
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">

<header>
    <nav class="main-nav">
        <a href="index.php" class="nav-brand">ZPĚVNÍK</a>
        
        <ul id="main-nav-links">
            <li class="search-nav-item">
                <a href="#" id="search-toggle-btn" title="Hledat">
                    <span class="material-symbols-outlined">search</span>
                    <span>Hledat</span>
                </a>
            </li>
            <li>
                <a href="list.php">
                    <span class="material-symbols-outlined">library_music</span>
                    <span>Písničky</span>
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

            <li id="share-menu-item" class="song-only-item" style="display: none;">
                <a href="#">
                    <span class="material-symbols-outlined">share</span>
                    <span>Sdílet</span>
                </a>
            </li>
            
            <?php if (is_admin()): ?>
                <li>
                    <a href="konverze.php"><span class="material-symbols-outlined">add_link</span><span>Přidat z URL</span></a>
                </li>
                <li>
                    <a href="editor.php"><span class="material-symbols-outlined">edit_document</span><span>Přidat</span></a>
                </li>
                <li>
                    <a href="admin_settings.php"><span class="material-symbols-outlined">settings</span><span>Nastavení</span></a>
                </li>
            <?php endif; ?>
            
            <li>
                <a href="#" id="fullscreen-btn" title="Celá obrazovka"><span class="material-symbols-outlined">fullscreen</span><span>Celá obrazovka</span></a>
            </li>
            <li>
                <a href="#" id="theme-toggle-btn" title="Přepnout motiv"><span class="material-symbols-outlined">contrast</span><span>Motiv</span></a>
            </li>

             <?php // Zobrazí se jen pro běžného přihlášeného uživatele
                if (is_user_logged_in() && !is_admin()): ?>
                <li>
                    <a href="#" id="btn-show-support-modal">
                        <span class="material-symbols-outlined">volunteer_activism</span>
                        <span>Podpora</span>
                    </a>
               </li>
                <?php endif; ?>   

            <li>
                <?php if (is_user_logged_in()): ?>
                    <a href="logout.php"><span class="material-symbols-outlined">logout</span><span>Odhlásit</span></a>
                <?php else: ?>
                    <a href="login.php"><span class="material-symbols-outlined">login</span><span>Přihlásit</span></a>
                <?php endif; ?>
            </li>
        </ul>

        <div class="mobile-actions">
            <a href="#" id="search-toggle-btn-mobile" class="mobile-action-icon" title="Hledat">
                <span class="material-symbols-outlined">search</span>
            </a>
            <a href="#" id="share-menu-item-mobile" class="mobile-action-icon song-only-item" style="display: none;" title="Sdílet píseň">
                <span class="material-symbols-outlined">share</span>
            </a>
            <a href="random.php" class="mobile-action-icon" title="Překvap mě">
                <span class="material-symbols-outlined">shuffle</span>
            </a>
            <a href="#" class="mobile-action-icon fullscreen-toggle-btn" title="Celá obrazovka">
                <span class="material-symbols-outlined">fullscreen</span>
            </a>
            <button id="mobile-menu-toggle" class="mobile-menu-button">
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>
    </nav>
    <div id="search-bar-container" class="search-bar-container">
        <div class="search-bar-inner">
            <div class="search-input-wrapper">
                <span class="material-symbols-outlined">search</span>
                <input type="text" id="header-search-input" placeholder="Hledat písničku nebo interpreta..." autocomplete="off">
                <button id="search-close-btn" title="Zavřít vyhledávání">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="header-search-results" class="header-search-results"></div>
        </div>
    </div>
</header>

<main class="container">