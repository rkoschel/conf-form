# Tests

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 11. Tests

Ziel: Die Fachregeln (Kontingent, Warteliste, Validierung, Spamschutz) sind
automatisiert abgesichert; Aufwand und Werkzeuge bleiben klein.

### 11.1 Ebenen

| Ebene | Verzeichnis | Inhalt |
|---|---|---|
| Unit | `tests/Unit/` | Reine Fachlogik ohne HTTP und DB: Status-Entscheidung (bevorzugter Ort + Kontingent, alle Personengruppen zählen, Grenzfälle), Personengruppen je Veranstaltung, Validierung (Pflichtfelder, „keine E-Mail“ → Telefon, ≥ 1 Person, Aufteilung ≤ Gruppenzahl), Normalisierung des Ortsnamens, Dublettenerkennung, Frist in Event-Zeitzone (inkl. Sommerzeit-Umstellung), Spamschutz (Signatur, Mindest-/Höchstalter). |
| Integration | `tests/Integration/` | Echte SQLite-DB (temporäre Datei pro Test, echtes Schema): Cascades, „nur ein Event aktiv“, Anmeldung in Transaktion, Rate-Limit inkl. Aufräumen, Auswertungs-Abfragen. |
| HTTP | `tests/Http/` | Echte Requests gegen `php -S` (pro Testklasse gestartet, eigene DB, Mail-Transport `log`): Hauptpfade wie Anmeldung absenden, CSRF fehlt, Honeypot, Frist abgelaufen, Absage per GET (nur Anzeige) und POST. Wenige Tests, nur die wichtigen Abläufe. |
| Smoke | `tests/smoke.sh <url>` | Gegen den Server nach jedem Deploy: Seiten erreichbar, `admin/` verlangt Login, `app/` und `.htaccess` gesperrt, Repo-Dateien nicht vorhanden, Sicherheits-Header gesetzt, kein `X-Powered-By`, keine PHP-Fehler, HTTP → HTTPS. |
| Statisch | `phpstan.neon` | PHPStan Level 5 über `web/` und `dev/` (ohne PHPMailer und Templates). |
| Manuell | – | Kurze Browser-Checkliste vor dem Öffnen der Anmeldung (Mobil-Layout, JS-Umschaltungen, Mails im echten Postfach). |

Bewusst nicht: Browser-Automatisierung (Playwright/Selenium), Coverage-Ziele.

### 11.2 Werkzeuge

- PHPUnit 11 (läuft auf PHP 8.2 wie der Server) und PHPStan 2 als `.phar`
  unter `tools/`, geladen von `tools/install.sh` mit fester Version und
  SHA-256-Prüfsumme; per `.gitignore` ausgeschlossen.
- Lokal benötigt: `php-xml` (für PHPUnit).
- `./test.sh`: `php -l` → PHPStan → PHPUnit; Argumente gehen an PHPUnit
  (z. B. `./test.sh --testsuite unit`).
- Tests nutzen `tests/config.test.php` (keine echten Zugangsdaten) und ein
  temporäres Verzeichnis, das nach dem Lauf gelöscht wird. Tests können per
  `db(db_connect($pfad))` eine eigene DB setzen.
- `tests/` und `tools/` liegen außerhalb von `web/` und werden nie deployt.

### 11.3 Vorgehen

- Tests entstehen zusammen mit jeder Funktion, nicht nachträglich; bei der
  Kontingent- und Statuslogik zuerst der Test.
- Seiten (`web/**/index.php`) bleiben dünn (Eingabe lesen → Logik aufrufen →
  rendern); die Logik liegt testbar als Funktionen in `web/app/*.php`.
