# conf-form

Anmeldung zu einer Konferenz mit Infoseite, Warteliste und Admin-Oberfläche.
Plain PHP 8.2, SQLite, Bootstrap 5.3 (selbst gehostet), PHPMailer.

## Lokale Entwicklung

Voraussetzung: `php-cli`, `php-sqlite3`, `php-mbstring`.

```bash
cp config.example.php config.local.php   # base_url '', db_path, app_secret, debug anpassen
php -r 'echo bin2hex(random_bytes(32)), "\n";'   # → app_secret
CONF_FORM_CONFIG=$PWD/config.local.php php -S localhost:8000 -t web dev/router.php
```

Die SQLite-DB wird beim ersten Aufruf automatisch aus `web/app/schema.sql`
angelegt. `dev/router.php` sperrt lokal `app/` (ersetzt die `.htaccess`, die
`php -S` nicht auswertet). Lokal gibt es keine Basic Auth für `admin/`.

## Server-Einrichtung (einmalig, manuell)

```
/home/www/konferenz/          ← Inhalt von web/ (per Deploy)
/home/conf-form/config.php    ← aus config.example.php, mit echten Zugangsdaten
/home/conf-form/.htpasswd     ← htpasswd -c -B /home/conf-form/.htpasswd admin
/home/conf-form/data/         ← für PHP beschreibbar
```

## Secrets

`config.php`, `config.local.php`, `.htpasswd` und die Datenbank sind per
`.gitignore` ausgeschlossen und dürfen nie ins Repo.
