# Konferenz-Anmeldung – Spezifikation

Stand: 09.10.2026

## 1. Ziel

Anmeldung zu einer (kostenlosen) Konferenz inkl. Infoseite, Warteliste und
schlanker Admin-Oberfläche. Technisch möglichst einfach und wartungsarm.

## Aufbau

Die Spezifikation ist auf mehrere Dateien verteilt. Die Abschnittsnummern
sind über alle Dateien durchgehend. Verweise wie „SPEC §5.1“ in Code,
Kommentaren und Tests meinen den Abschnitt mit dieser Nummer, die Tabelle
zeigt die Datei dazu.

| § | Datei | Inhalt |
|---|---|---|
| 1 | diese Datei | Ziel |
| 2 | [operations.md](operations.md) | Technik, Verzeichnisse auf dem Server, Deployment (2.1), Konfiguration |
| 3, 4, 6 | [public-pages.md](public-pages.md) | URLs, Infoseite, Absage per Link |
| 5 | [registration-form.md](registration-form.md) | Anmeldeformular: Felder (5.1), Spamschutz (5.2), Logik beim Absenden (5.3), Zählweise Programmpunkte, Zielgruppe und Kinderbetreuung (5.4) |
| 7 | [admin.md](admin.md) | Veranstaltungen (7.1), Anfragen (7.2), Statuswechsel (7.3), Bearbeiten (7.4), Einstellungen (7.5), Löschen (7.6), Auswertung (7.7), Team-Zugang (7.8), Menü und Hilfe (7.9) |
| 8 | [data-model.md](data-model.md) | SQLite-Schema, Migrationen |
| 9 | [mails.md](mails.md) | Mail-Typen und Absender |
| 10 | [security.md](security.md) | Sicherheit und Datenschutz |
| 11 | [testing.md](testing.md) | Testebenen, Werkzeuge, Vorgehen |
| 12 | [repo-structure.md](repo-structure.md) | Verzeichnisse und Dateien im Repo |
| 13, 14 | [open-points.md](open-points.md) | Weitere Punkte, offene und geplante Punkte |

Neue Abschnitte bekommen die nächste freie Nummer und kommen in die
passende Datei (oder eine neue, dann hier eintragen).
