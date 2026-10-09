# Technik & Betrieb

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 2. Technik & Betrieb

| Thema | Festlegung |
|---|---|
| Hosting | PHP-Webhost (PHP 8.2), kein vhost nötig |
| Sprache | Plain PHP (kein Framework), PDO |
| Datenbank | SQLite (`pdo_sqlite` vorhanden) |
| Frontend | Serverseitig gerendertes HTML, wenig Vanilla-JS, kein Build-Schritt |
| Datum & Uhrzeit | Anzeige und Eingabe immer `TT.MM.JJJJ` und `HH:MM` (24 h). Eingabe als Textfelder, nicht als native Date/Time-Picker (deren Format hängt von der Browser-Sprache ab, z. B. US-Format/AM-PM). Gespeichert wird ISO (`Y-m-d`, `H:i`). |
| UI-Framework | Bootstrap 5.3.8, **selbst gehostet** unter `assets/vendor/bootstrap/` (kein CDN, Datenschutz) |
| Spamschutz | Honeypot-Feld + signierter Zeitstempel + Rate-Limit pro IP (kein Captcha), siehe [§5.2](registration-form.md) |
| Mail | PHPMailer über SMTP (Postfach beim Hoster), Bibliothek im Repo eingecheckt (kein Composer) |
| Admin-Auth | HTTP Basic Auth per `.htaccess`/`.htpasswd` (ein Admin) |
| Repo | GitHub (nur Versionsverwaltung, keine Actions) |
| Deploy | Lokales Skript `deploy.sh` mit `lftp` über FTPS – lädt nur geänderte Dateien aus `web/` nach `/home/www/konferenz/`, siehe §2.1 |
| Entwicklung | Ubuntu-Laptop, `php -S localhost:8000 -t web dev/router.php` |
| Tests | PHPUnit 11 + PHPStan als `.phar` (kein Composer), `./test.sh`, Smoke-Test nach jedem Deploy, siehe [§11](testing.md) |

### Verzeichnisse auf dem Server

```
/home/www/konferenz/          Webroot der App (per Deploy, = Inhalt von web/)
/home/conf-form/config.php    Konfiguration + Secrets (per `deploy.sh setup`, nicht im Repo)
/home/conf-form/.htpasswd     Admin-Zugang (per `deploy.sh setup`, nicht im Repo)
/home/conf-form/data/         SQLite-DB (wird beim ersten Aufruf automatisch angelegt)
```

Die Landingpage des Veranstalters liegt im selben Webroot (`/home/www`).
Das Deployment darf **ausschließlich** in `konferenz/` schreiben und nie
außerhalb löschen.

### 2.1 Deployment (`deploy.sh`)

Bash-Skript im Repo, wird lokal auf dem Laptop ausgeführt. Voraussetzung:
`lftp` (`sudo apt install lftp`). Kein Ansible: Der Hoster bietet nur
FTP/FTPS (kein SSH), Ansible brächte dort keinen Mehrwert.

**Zugangsdaten:** in `deploy.env` (per `.gitignore` ausgeschlossen, Vorlage
`deploy.env.example`): `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`,
`FTP_APP_DIR` (FTP-Pfad zu `/home/www/konferenz`), `FTP_PRIVATE_DIR`
(FTP-Pfad zu `/home/conf-form`), optional `FTP_VERIFY_CERT` (Standard `true`) und
`APP_URL` (öffentliche URL für den Smoke-Test).
`FTP_HOST` ist der Servername des Hosters `ds12.serverdomain.org` (nicht
`christen-in-hamm.de`), weil das FTPS-Zertifikat auf `*.serverdomain.org`
ausgestellt ist. Das FTP-Login startet in `/` (Root),
die FTP-Pfade entsprechen also den Server-Pfaden: `FTP_APP_DIR=/home/www/konferenz`,
`FTP_PRIVATE_DIR=/home/conf-form`. `/home/conf-form/` ist angelegt
(01.10.2026). Die Pfade bleiben trotzdem konfigurierbar.
Erster Deploy erfolgreich (02.10.2026): PHP liest `/home/conf-form/config.php`
und legt die DB in `data/` an; PHP läuft unter dem FTP-User (daher
`config.php` und `data/` mit 600/700 möglich).

**Verbindung:** explizites FTPS auf Port 21, TLS erzwungen für Steuer- und
Datenverbindung (`ftp:ssl-force`, `ftp:ssl-protect-data`), Zertifikat wird
geprüft. Der Server unterstützt keine TLS-Session-Resumption auf der
Datenverbindung; das wird als Restrisiko akzeptiert (Zugangsdaten bleiben
verschlüsselt, unverschlüsseltes FTP ist keine Option).

**Befehle:**

| Aufruf | Wirkung |
|---|---|
| `./deploy.sh` | Führt `./test.sh` aus (Abbruch bei Fehlern), spiegelt dann `web/` nach `FTP_APP_DIR` (`lftp mirror --reverse`): lädt nur neue/geänderte Dateien hoch und entfernt Dateien, die in `web/` nicht mehr existieren – **nur innerhalb** von `FTP_APP_DIR`. Danach läuft `tests/smoke.sh` gegen `APP_URL`. |
| `./deploy.sh --dry-run` | Zeigt nur an, was hochgeladen/gelöscht würde. |
| `./deploy.sh setup` | Einmalig bzw. bei Änderung: legt `FTP_PRIVATE_DIR/data/` an und lädt `config.prod.php` → `config.php` sowie `.htpasswd` hoch (beide lokal, per `.gitignore` ausgeschlossen). `data/` wird nie überschrieben oder gelöscht. |

**Sicherheitsprüfungen im Skript:**
- Abbruch, wenn `FTP_APP_DIR` nicht auf `/konferenz` endet (Schutz der
  Landingpage im selben Webroot).
- Abbruch, wenn der Arbeitsbaum nicht sauber ist oder nicht `main`
  ausgecheckt ist oder die Tests fehlschlagen (übersteuerbar mit `--force`).
- Es werden nur Inhalte aus `web/` übertragen; Repo-Dateien wie
  `config.example.php`, `dev/`, `README.md` nie.

### Konfiguration

Pfad: Umgebungsvariable `CONF_FORM_CONFIG`, sonst `/home/conf-form/config.php`.
Lokal z. B.: `CONF_FORM_CONFIG=$PWD/config.local.php php -S localhost:8000 -t web dev/router.php`.

Inhalt (Vorlage: `config.example.php`):

- `base_url` – z. B. `/konferenz` (Server) bzw. `''` (lokal)
- `db_path` – Pfad zur SQLite-Datei
- `app_secret` – zufälliger Schlüssel für HMAC (Zeitstempel, IP-Hash)
- `smtp` – Host, Port, Verschlüsselung, Benutzer, Passwort, Absender-Adresse/-Name
- `privacy_url` – Link zur Datenschutzerklärung
- `rate_limit` – max. Absendeversuche und Zeitfenster (Standard: 10 pro 60 Min.)
