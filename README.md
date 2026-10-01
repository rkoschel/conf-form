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

## Deployment

Deployment runs locally via `deploy.sh` (lftp over FTPS, no CI).
Requirements: `lftp`, and `apache2-utils` for `htpasswd`.

```
/home/www/konferenz/          ← contents of web/ (./deploy.sh)
/home/conf-form/config.php    ← config.prod.php (./deploy.sh setup)
/home/conf-form/.htpasswd     ← .htpasswd       (./deploy.sh setup)
/home/conf-form/data/         ← SQLite database, must be writable by PHP
```

One-time setup:

```bash
cp deploy.env.example deploy.env        # FTP credentials
cp config.example.php config.prod.php   # set app_secret, SMTP credentials
htpasswd -c -B .htpasswd admin          # admin login
./deploy.sh setup
```

Deploy (only committed files from `web/` on `main`):

```bash
./deploy.sh --dry-run   # show what would change
./deploy.sh
```

The script refuses to run if the target directory does not end in
`/konferenz` (the organizer's landing page shares the web root), if the
private directory is inside the web root, or if `web/` has uncommitted or
ignored files (override the Git checks with `--force`).

## Secrets

`config.local.php`, `config.prod.php`, `deploy.env`, `.htpasswd` and the
database are excluded via `.gitignore` and must never be committed.
