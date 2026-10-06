<?php
// Kopieren nach config.php. Dieser Ordner muss außerhalb des Domain-Webverzeichnisses liegen.
return [
 'dsn' => 'mysql:host=localhost;dbname=DATENBANKNAME;charset=utf8mb4',
 'user' => 'DATENBANKBENUTZER',
 'password' => 'DATENBANKPASSWORT',
 'origin' => 'https://ablage.DEINE-DOMAIN.de',
 // Einmaliges Installationskennwort: durch mindestens 32 zufällige Zeichen ersetzen.
 'setup_key' => 'BITTE_DURCH_EIN_ZUFAELLIGES_INSTALLATIONSKENNWORT_ERSETZEN',
];
