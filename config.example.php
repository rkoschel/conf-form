<?php
// Vorlage für die Konfiguration. Kopieren nach:
//   Server: /home/conf-form/config.php
//   Lokal:  ./config.local.php  (per .gitignore ausgeschlossen)
// Echte Zugangsdaten gehören NIE in dieses Repo.

return [
    // URL-Präfix der App: '/konferenz' auf dem Server, '' lokal
    'base_url' => '/konferenz',

    // Öffentliche Adresse der App für Links in Mails (Absage-Link)
    'app_url' => 'https://christen-in-hamm.de/konferenz',

    // SQLite-Datei; Verzeichnis muss für PHP beschreibbar sein
    'db_path' => '/home/conf-form/data/conf-form.sqlite',

    // Zufälliger Schlüssel für HMAC (Zeitstempel, IP-Hash), erzeugen mit:
    //   php -r 'echo bin2hex(random_bytes(32)), "\n";'
    'app_secret' => '',

    // Fehler im Browser anzeigen (nur lokal true)
    'debug' => false,

    // 'smtp' = echter Versand, 'log' = Mails nur in mail_log_file schreiben
    'mail_transport' => 'smtp',
    'mail_log_file' => '/home/conf-form/data/mail.log',

    'smtp' => [
        'host' => '',
        'port' => 587,
        'encryption' => 'tls', // 'tls' (STARTTLS) oder 'ssl'
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => 'Konferenz-Anmeldung',
    ],

    'privacy_url' => 'https://christen-in-hamm.de/datenschutzerklaerung/',
    'info_url' => 'https://christen-in-hamm.de/info/',

    'rate_limit' => [
        'max_attempts' => 10,
        'window_minutes' => 60,
    ],
];
