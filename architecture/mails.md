# Mails

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 9. Mails

| Anlass | `mail_log.type` | Inhalt |
|---|---|---|
| Eingang, bestätigt | `received_confirmed` | Bestätigung, Eckdaten, Personenzahlen, Absage-Link |
| Eingang, Warteliste | `received_waitlist` | Hinweis Warteliste, Personenzahlen, Absage-Link |
| Admin: bestätigt (optional) | `confirmed` | Bestätigung, Absage-Link |
| Admin: abgelehnt (optional) | `rejected` | Absage |

| Neue Anmeldung, an den Admin (optional) | – (nicht protokolliert) | Status, Name, Heimatversammlung, E-Mail bzw. Telefon, Personenzahlen, Programmpunkte (inkl. Kinder in der Betreuung), Nachricht, Link in den Admin |

Absender: konfiguriertes SMTP-Postfach; Reply-To: Veranstalter-E-Mail, bei
der Admin-Benachrichtigung die E-Mail des Teilnehmers (ohne E-Mail kein
Reply-To). Texte als einfache PHP-Templates im Repo; die Texte der
Admin-Benachrichtigung sind fest.

**Admin-Benachrichtigung:** Ein- und ausschaltbar mit Empfängeradresse unter
Einstellungen → Mailversand ([§7.5](admin.md), Schlüssel `admin_notify`
und `admin_notify_email` in `settings`). Geht nach jeder neu gespeicherten
Anmeldung raus (nicht bei Honeypot-Treffern, nicht bei Änderungen im Admin).
Schlägt sie fehl, steht der Fehler nur im PHP-Fehlerlog; die Anmeldung und
die Mail an den Teilnehmer sind davon nicht betroffen.
