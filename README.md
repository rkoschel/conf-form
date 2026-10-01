# conf-form

Conference registration with an info page, waiting list and admin interface.
Plain PHP 8.2, SQLite, Bootstrap 5.3 (self-hosted), PHPMailer.

## Local development

Requirements: `php-cli`, `php-sqlite3`, `php-mbstring`.

```bash
cp config.example.php config.local.php   # adjust base_url '', db_path, app_secret, debug
php -r 'echo bin2hex(random_bytes(32)), "\n";'   # → app_secret
CONF_FORM_CONFIG=$PWD/config.local.php php -S localhost:8000 -t web dev/router.php
```

The SQLite database is created automatically from `web/app/schema.sql` on the
first request. `dev/router.php` blocks access to `app/` locally (replacing the
`.htaccess`, which `php -S` ignores). There is no Basic Auth for `admin/`
locally.

## Server setup (one-time, manual)

```
/home/www/konferenz/          ← contents of web/ (via deploy)
/home/conf-form/config.php    ← from config.example.php, with real credentials
/home/conf-form/.htpasswd     ← htpasswd -c -B /home/conf-form/.htpasswd admin
/home/conf-form/data/         ← writable by PHP
```

## Secrets

`config.php`, `config.local.php`, `.htpasswd` and the database are excluded
via `.gitignore` and must never be committed.
