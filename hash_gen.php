<?php
echo password_hash('132562306', PASSWORD_BCRYPT);
unlink(__FILE__); // Skript se po spuštění sám smaže
?>
