# Repo-Struktur

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 12. Repo-Struktur

```
conf-form/                         (Repo)
├── web/                           → 1:1 nach /home/www/konferenz/
│   ├── .htaccess                  HTTPS-Redirect, DirectoryIndex, Options -Indexes
│   ├── index.php                  Infoseite
│   ├── register/index.php
│   ├── cancel/index.php
│   ├── admin/
│   │   ├── .htaccess              Basic Auth (AuthUserFile /home/conf-form/.htpasswd)
│   │   ├── index.php              Anfragen-Tabelle
│   │   ├── registration.php       Bearbeiten / Statuswechsel / Löschen
│   │   ├── events.php             Liste der Veranstaltungen
│   │   ├── event.php              Anlegen / Bearbeiten / Löschen
│   │   ├── stats.php              Auswertung
│   │   └── settings.php           Einstellungen (bevorzugte Orte, Texte)
│   ├── assets/
│   │   ├── app.css
│   │   ├── form.js                Anmeldeformular (Aufteilung, „keine E-Mail“)
│   │   ├── admin.js               Dialoge
│   │   └── vendor/bootstrap/      bootstrap.min.css, bootstrap.bundle.min.js
│   └── app/                       .htaccess: Require all denied
│       ├── bootstrap.php          Config laden, Session, Autoload
│       ├── db.php                 PDO, foreign_keys, Auto-Init aus schema.sql
│       ├── schema.sql
│       ├── *.php                  mailer, spam, csrf, registrations, events, …
│       ├── templates/             Seiten-Layouts und Mail-Templates
│       └── lib/PHPMailer/         eingecheckt (PHPMailer.php, SMTP.php, Exception.php)
├── dev/router.php                 nur lokal: sperrt app/ beim PHP-Server
├── tests/                         Unit/, Integration/, Http/, Support/, smoke.sh (siehe §11)
├── tools/install.sh               lädt phpunit.phar, phpstan.phar (gitignored)
├── test.sh                        Lint, PHPStan, PHPUnit
├── phpunit.xml, phpstan.neon
├── config.example.php             wird nicht deployt
├── README.md
├── deploy.sh                      Deployment per lftp/FTPS (siehe §2.1)
├── deploy.env.example             Vorlage für FTP-Zugangsdaten und APP_URL
├── CLAUDE.md                      Hinweise für Agents
└── architecture/                  Spezifikation (overview.md + Einzeldateien)
```
