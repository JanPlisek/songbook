<?php
// soubor: includes/functions.php

/**
 * Zkontroluje, zda je uživatel přihlášen.
 * @return bool
 */
function is_user_logged_in() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

/**
 * Vytvoří ze stringu "slug" vhodný pro URL a názvy souborů.
 * Spolehlivě odstraňuje českou diakritiku.
 */
function create_slug($text) {
    // Převod na malá písmena
    $text = mb_strtolower($text, 'UTF-8');
    // Mapa pro náhradu českých znaků
    $char_map = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n',
        'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
    ];
    $text = strtr($text, $char_map);
    // Nahradí vše, co není písmeno a-z nebo číslo, za pomlčku
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    // Odstraní případné pomlčky na začátku a na konci
    $text = trim($text, '-');
    return empty($text) ? 'n-a' : $text;
}


/**
 * Stáhne obsah z dané URL adresy pomocí cURL.
 * Tváří se jako běžný prohlížeč.
 */
function fetch_url_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

/**
 * Získá první písmeno řetězce podle českých pravidel (rozpozná "Ch").
 * @param string $string Vstupní řetězec (např. název písně).
 * @return string První písmeno ve velkém formátu.
 */
function get_czech_first_letter($string) {
    if (empty(trim($string))) {
        return ''; 
    }
    
    if (mb_strtoupper(mb_substr($string, 0, 2, 'UTF-8'), 'UTF-8') === 'CH') {
        return 'CH';
    }
    
    return mb_strtoupper(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8');
}

/**
 * Najde správnou cestu k obrázku interpreta.
 * Preferuje .png, poté hledá .jpg, a nakonec vrátí výchozí obrázek.
 * @param string $artist_name Jméno interpreta.
 * @return string Cesta k existujícímu obrázku.
 */
function get_artist_image_path($artist_name) {
    $base_dir = 'assets/img/interprets/';
    $slug = create_slug($artist_name);

    // Preferujeme novější PNG formát z ořezávače
    $png_path = $base_dir . $slug . '.png';
    if (file_exists($png_path)) {
        return $png_path;
    }

    // Pokud PNG neexistuje, zkusíme starší JPG
    $jpg_path = $base_dir . $slug . '.jpg';
    if (file_exists($jpg_path)) {
        return $jpg_path;
    }

    // Pokud neexistuje ani jeden, vrátíme výchozí
    return $base_dir . 'default.jpg';
}