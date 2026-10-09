# Mails

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 9. Mails

| Anlass | `mail_log.type` | Inhalt |
|---|---|---|
| Eingang, bestätigt | `received_confirmed` | Bestätigung, Eckdaten, Personenzahlen, Absage-Link |
| Eingang, Warteliste | `received_waitlist` | Hinweis Warteliste, Personenzahlen, Absage-Link |
| Admin: bestätigt (optional) | `confirmed` | Bestätigung, Absage-Link |
| Admin: abgelehnt (optional) | `rejected` | Absage |

Absender: konfiguriertes SMTP-Postfach; Reply-To: Veranstalter-E-Mail.
Texte als einfache PHP-Templates im Repo.
