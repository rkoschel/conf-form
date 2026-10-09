# Sicherheit & Datenschutz

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 10. Sicherheit & Datenschutz

- Nur HTTPS (Redirect per `.htaccess`).
- CSRF-Token für alle POST-Formulare (auch im Admin).
- Prepared Statements, Ausgabe konsequent mit `htmlspecialchars`.
- Config, `.htpasswd` und DB außerhalb des Webroots.
- `web/app/` (Code, Templates, Schema, Bibliotheken) per `.htaccess`
  gesperrt (`Require all denied`).
- Datenschutz-Hinweis mit Link zur Datenschutzerklärung im Formular
  (keine Checkbox).
- Keine externen Ressourcen (Bootstrap selbst gehostet).
- Nach der Veranstaltung: Anmeldungen bzw. ganze Veranstaltung im Admin
  löschbar (manuell).
- Hinweis lokale Entwicklung: `php -S` wertet `.htaccess` nicht aus
  (keine Basic Auth, keine Sperre von `app/`).
