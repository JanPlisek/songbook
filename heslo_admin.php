<?php
$mojeAdminHeslo = 'zpevnik_132562306'; // !!! Zadejte své NOVÉ, soukromé heslo pro správu
$hash = password_hash($mojeAdminHeslo, PASSWORD_DEFAULT);
echo 'Váš bezpečný ADMIN HASH je:<br><br><strong>' . $hash . '</strong>';
echo '<br><br>Zkopírujte si ho a poté tento soubor smažte!';
?>