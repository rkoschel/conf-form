# Admin-Oberfläche

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 7. Admin `/konferenz/admin/`

Alle Seiten mit Bootstrap; Rückfragen (Mail senden?, Löschen?) als
Bootstrap-Modal.

### 7.1 Veranstaltungen

Felder: Titel, Datum, Ort, Anmeldefrist (Datum + Uhrzeit), **Zeitzone**
(Auswahl aus `DateTimeZone::listIdentifiers()`, Standard `Europe/Berlin`),
Beschreibung (Klartext), max. Teilnehmer, Veranstalter (Name, E-Mail),
aktiv ja/nein, **Personengruppen**, **Individuelle Aufteilung erlauben**
(Schalter, Standard an; aus = Teilnehmer kreuzen je Programmpunkt nur an,
die Option „Anzahl individuell aufteilen“ erscheint nicht und wird auch
serverseitig ignoriert; jederzeit änderbar, bestehende Aufteilungen bleiben
erhalten und im Admin-Bearbeiten sichtbar).

**Personengruppen:** fünf feste Plätze mit fester Art – Gruppe 1 =
Erwachsene, Gruppe 2 = Jugendliche, Gruppen 3–5 = Kindergruppen. Je
Veranstaltung per Checkbox wählbar, welche Gruppen bei der Anmeldung
vorkommen (mindestens eine), und je gewählter Gruppe ein Name (Pflicht;
Standardnamen Erwachsene, Jugendliche, Kindergruppe 1, Kindergruppe 2,
Kindergruppe 3). Eine neue Veranstaltung übernimmt Auswahl und Namen der
zuletzt angelegten (sonst Standard). Die **Auswahl** ist gesperrt, sobald es
Anmeldungen gibt; die **Namen** bleiben änderbar. Alle Gruppen zählen zur
Belegung. Nur gewählte Kindergruppen sind für die Kinderbetreuung wählbar.

Validierung (serverseitig, Fehler verhindern das Speichern):
- Pflicht: Titel, Datum, Ort, Anmeldefrist, Zeitzone, max. Teilnehmer.
- Datum und Frist gültig; Zeitzone aus `DateTimeZone::listIdentifiers()`;
  max. Teilnehmer ganze Zahl ≥ 1; Veranstalter-E-Mail optional, aber gültig.
- **Die Anmeldefrist liegt spätestens zum Beginn des ersten Programmpunkts**
  (Datum der Veranstaltung + früheste Uhrzeit im Ablauf, beides in der
  Zeitzone der Veranstaltung). Ohne Programmpunkte: spätestens am
  Veranstaltungstag, 23:59.

- Ablauf: Liste von Programmpunkten (Uhrzeit `HH:MM`, Bezeichnung; beides
  Pflicht, komplett leere Zeilen werden ignoriert). Hinzufügen per Button
  „+ Programmpunkt“, Entfernen per × je Zeile. **Sortierung automatisch nach
  Uhrzeit** (kein manuelles Verschieben; `sort` wird intern gesetzt).
  Je Programmpunkt Schalter **„Kinderbetreuung“**; wenn an, Auswahl der
  betreuten Gruppen – nur **Kindergruppen (3–5), die bei der Veranstaltung
  gewählt sind** –, mindestens eine Pflicht. Anzeige mit den Gruppennamen
  („A“, „A und B“, „A, B und C“). Die Kinderbetreuung ist wie der Ablauf
  gesperrt, sobald es Anmeldungen gibt.
  Je Programmpunkt Schalter **„Zielgruppe festlegen“**; wenn an, Auswahl aus
  den bei der Veranstaltung gewählten Personengruppen (mindestens eine), für
  die der Programmpunkt gilt. Anzeige „Zielgruppe: <Namen>“ (wie bei der
  Kinderbetreuung verbunden). Ebenfalls gesperrt, sobald es Anmeldungen gibt.
  **Zielgruppe und Kinderbetreuung schließen sich aus:** im Formular wird der
  jeweils andere Schalter gesperrt; serverseitig Fehler „Programmpunkt n:
  Zielgruppe und Kinderbetreuung sind nicht kombinierbar.“ Wird auf
  Veranstaltungsebene eine Personengruppe abgewählt, fällt sie aus der
  Zielgruppe heraus; bleibt keine Gruppe übrig, Fehler „Programmpunkt n:
  Zielgruppe auswählen.“ Sind alle gewählten Personengruppen Zielgruppe, wird
  keine Zielgruppe gespeichert (gleiche Wirkung, kein Hinweis).
  Zählweise siehe [§5.4](registration-form.md).
  **Sobald mindestens eine Anmeldung existiert, ist der Ablauf gesperrt**
  (nur Anzeige mit Hinweis). Änderungen am Programm nach Eröffnung der
  Anmeldung sind in Version 1 nicht vorgesehen.
- Bevorzugte Orte werden nicht je Veranstaltung, sondern global in den
  Einstellungen gepflegt (siehe §7.5); das Formular verweist dorthin.
- Es kann nur **eine** Veranstaltung aktiv sein. Aktivieren einer
  Veranstaltung deaktiviert alle anderen (zusätzlich per DB-Index abgesichert).
- **Löschen:** Eine Veranstaltung kann gelöscht werden, inkl. aller
  zugehörigen Daten (Programmpunkte, Anmeldungen,
  Programmpunkt-Aufteilung, Mail-Protokoll; per `ON DELETE CASCADE`).
  Vorher Bestätigungsdialog: „Veranstaltung X mit N Anmeldungen endgültig
  löschen?“
- Liste (`admin/events.php`): Titel, Datum, Anmeldefrist, Anzahl
  Anmeldungen, Badge „aktiv“; Aktionen Bearbeiten, Aktivieren/Deaktivieren,
  Löschen.
- Nach dem Speichern Redirect auf die Liste mit Erfolgsmeldung
  (Post/Redirect/Get); bei Fehlern Formular mit Eingaben und markierten
  Feldern. Admin-Seiten senden `Cache-Control: no-store`.

### 7.2 Anfragen (Tabelle)

Spalten: Datum/Uhrzeit, Name, Personen gesamt, Ort (bevorzugte Orte
hervorgehoben), Status, Mail-Status, Aktionen.

- Status (UI): offen · bestätigt · storniert · abgelehnt
  (DB: `pending` · `confirmed` · `cancelled` · `rejected`)
- Aktionen: Bestätigen, Ablehnen, Bearbeiten, Löschen.
- Filter nach Status, Suche nach Name / Ort / E-Mail.
- **Dublettenmarkierung:** Hinweis-Symbol bei gleicher E-Mail oder gleichem
  Vor- + Nachnamen innerhalb derselben Veranstaltung (Vergleich
  case-insensitive, getrimmt; alle Status zählen mit).
- Kein CSV-Export in Version 1.

### 7.3 Statuswechsel

- **Bestätigen** ist aus jedem Status möglich (offen, storniert, abgelehnt).
  Es gibt keine eigene Aktion „Nachrücken“ – Bestätigen einer offenen
  Anmeldung ist das Nachrücken.
- **Ablehnen** ist aus jedem Status außer abgelehnt möglich.
- Bei Bestätigen / Ablehnen fragt ein Dialog:
  „E-Mail an Teilnehmer senden? ja / nein“ (nur wenn E-Mail vorhanden).
- Überschreitet eine Bestätigung das Kontingent: **nur Warnung**, Bestätigen
  bleibt möglich.

### 7.4 Bearbeiten

- Alle Felder der Anmeldung editierbar (Kontakt, Ort, Personenzahlen,
  Aufteilung auf Programmpunkte, Status).
- Statusänderung über das Bearbeiten-Formular verschickt keine Mail.
- Überschreitet eine Änderung an einer bestätigten Anmeldung das Kontingent:
  nur Warnung.

### 7.5 Einstellungen

Alles global (Tabelle `settings`), nicht pro Veranstaltung, damit es auch
ohne aktive bzw. nach Löschen aller Veranstaltungen verfügbar ist.

- **Bevorzugte Orte** (Schlüssel `preferred_places`): Textarea, ein Ort pro
  Zeile, gilt für alle Veranstaltungen (jederzeit änderbar; wirkt auf den
  Status nur bei neuen Anmeldungen, die Hervorhebung in Tabelle und
  Auswertung folgt der aktuellen Liste). Normalisiert (trim,
  Mehrfach-Leerzeichen), Dubletten (case-insensitive) werden
  zusammengefasst, leere Zeilen ignoriert.
- **Einstellbare Texte** (Klartext, Zeilenumbrüche bleiben erhalten). Die
  Standardtexte stehen im Code; ein leeres Feld bedeutet „Standardtext“ und
  zeigt ihn grau als Platzhalter im Feld an.
  - Allgemein: Text bei „keine Veranstaltung aktiv“ (`inactive_text`, auch
    auf der Anmeldeseite)
  - Anmeldeformular: Bezeichnung des Feldes „Heimatversammlung“, Hinweis
    unter „Voraussichtliche Anwesenheit“, Hinweis bei ausgebuchter
    Veranstaltung (Infoseite)
  - Nach dem Absenden: Text bei „bestätigt“, Text bei „Warteliste“, Hinweis
    zur Bestätigungs-Mail
  - Absage: Text nach erfolgreicher Absage
  - E-Mails: Einleitung je Mail-Typ (inkl. Anrede), Hinweis vor dem
    Absage-Link (bestätigt / Warteliste), Grußformel, Ersatz-Unterschrift
    ohne Veranstalter-Namen. Platzhalter (englisch): `{first_name}`,
    `{last_name}`, `{title}`, `{date}`, `{location}`, `{organizer}`.
    Eckdaten, Personenzahlen und Absage-Link bleiben fest.
- Fest bleiben die in dieser Spezifikation wörtlich vorgegebenen Texte
  (Button „Teilnahme anfragen“, „Der Anmeldezeitraum ist abgelaufen.“,
  Datenschutz-Hinweis), Fehlermeldungen und Admin-Texte.

- Aufbau der Seite in Tabs: Allgemein (bevorzugte Orte, Text bei inaktiver
  Veranstaltung), Anmeldeformular, Nach dem Absenden, Absage, E-Mails,
  Mailversand, Team-Zugang (§7.8). „Speichern“ speichert alle Text-Tabs; danach öffnet
  sich wieder der Tab, aus dem gespeichert wurde (auch per Adresse, z. B.
  `#e-mails`).
- **Mailversand** (Tab, eigenes Formular): zeigt die Mail-Einstellungen aus
  der Konfiguration (Versandart, Server, Port, Verschlüsselung, Benutzer,
  Absender; beim Passwort nur „gesetzt“/„fehlt“) – ändern lassen sie sich nur
  in der Konfigurationsdatei. Button **„Testmail senden“** an eine frei
  wählbare Adresse (vorbelegt mit dem Absender). Danach öffnet sich der Tab
  mit Ergebnis und ausführlichem **Protokoll**: PHP-/OpenSSL-Version,
  aufgelöste Server-IP und der komplette SMTP-Dialog (Verbindung, STARTTLS,
  Anmeldung, Annahme durch den Server). Zugangsdaten sind im Protokoll
  ausgeblendet. Das Protokoll erscheint nur auf dieser Seite (kein Redirect)
  und wird nirgends gespeichert; die Testmail steht nicht im Mail-Protokoll
  der Anmeldungen. Zweck: Fehler beim Versand vom Server aus finden
  (Port gesperrt, Zertifikat, Anmeldung).
  Außerdem im selben Tab: **Benachrichtigung bei neuen Anmeldungen**
  (Schalter, Standard aus, und Empfängeradresse; eingeschaltet ist eine
  gültige Adresse Pflicht, beim Ausschalten bleibt die Adresse erhalten).
  Inhalt siehe [§9](mails.md).

### 7.6 Löschen einer Anmeldung

- Endgültiges Löschen inkl. Programmpunkt-Aufteilung und Mail-Protokoll.
- Vorher Bestätigungsdialog mit Name und Personenzahl. Keine Mail.

### 7.7 Auswertung (aktive bzw. gewählte Veranstaltung)

- Anfragen nach Status, gezählt in **Einzelpersonen** (alle Gruppen)
  sowie Anzahl Anmeldungen.
- **Gesamtbelegung:** bestätigte Personen aller Gruppen / max. Teilnehmer.
- **Je Personengruppe** der Veranstaltung ein Balken mit bestätigten und
  offenen Personen, im selben Maßstab wie die Gesamtbelegung (Kapazität),
  mit Namen und Zahlen. Eine Tabelle „nach Altersgruppe“ gibt es nicht mehr.
- Pro Programmpunkt: Summe und Aufschlüsselung nach den Personengruppen der
  Veranstaltung (nur Status `confirmed`); Summe = Personen beim Programmpunkt.
- Eigene Tabelle „Kinderbetreuung je Programmpunkt“ (nur wenn vorhanden):
  Programmpunkte mit Betreuung, betreute Kindergruppen, Kinder in der
  Betreuung je Kindergruppe (– = keine Betreuung für diese Gruppe), Summe;
  nur bestätigte Anmeldungen.
- Bei Programmpunkten mit Zielgruppe stehen in der Tabelle „Anwesenheit je
  Programmpunkt“ (Admin und Team-Seite) die Gruppen außerhalb der
  Zielgruppe als „–“, dazu der Hinweis „Zielgruppe: <Namen>“.
- Auswahl der Veranstaltung per Dropdown (`?event=<id>`); ohne Auswahl die
  aktive, sonst die neueste. Programmpunkte mobil als Block je Punkt.
- Belegung als gestapelter Balken: bestätigte Personen (blau, rot bei
  Überschreitung) + offene Anfragen (gelb gestreift, wie der Status „offen“),
  alle Personengruppen. Legende mit Zahlen, Text „x % belegt, y frei“ bzw.
  „⚠ Kontingent um n überschritten“ und „Wenn alle offenen bestätigt würden:
  z von max“. Übersteigen bestätigt + offen das Kontingent, wächst die Skala
  mit und ein Strich markiert die Grenze.
- Kachel „Anmeldungen“: zusätzlich Personen je Heimatversammlung (alle
  Gruppen), getrennt nach bestätigt und offen (storniert/abgelehnt zählen
  nicht), meiste zuerst; Schreibweisen ohne Rücksicht auf Groß-/Klein-
  schreibung zusammengefasst, bevorzugte Orte fett.

### 7.8 Team-Zugang zur Auswertung

Für Mitarbeiter, die die Zahlen zur Vorbereitung brauchen – ohne Login per
geheimem Link `/konferenz/team/?k=<Schlüssel>`.

- Schlüssel: 160 Bit zufällig (40 Hex-Zeichen), gespeichert in `settings`
  (`team_key`, leer = deaktiviert). Anfangs deaktiviert.
- Admin → Einstellungen, Abschnitt „Team-Zugang (Auswertung)“: Link erzeugen;
  wenn aktiv: Link mit „Kopieren“, „Neuen Link erzeugen“ (alter wird sofort
  ungültig, mit Rückfrage), „Deaktivieren“ (mit Rückfrage).
- Seite: nur die **aktive** Veranstaltung, **Titel oben**, darunter Datum
  und „Stand“ (Uhrzeit des Abrufs); Inhalt wie die Admin-Auswertung (§7.7,
  mit absoluten Zahlen), ohne Veranstaltungsauswahl und Admin-Navigation;
  ohne aktive Veranstaltung ein Hinweis.
- **Drei klar abgegrenzte Bereiche** (je ein Feld mit Rahmen, Titel,
  Kurzbeschreibung und farbiger Kopfkante), in dieser Reihenfolge:
  1. **Zugesagte Anmeldungen** (grün): Anwesenheit je Programmpunkt und
     Kinderbetreuung je Programmpunkt (nur bestätigte Anmeldungen),
  2. **Gesamtbelegung und Anmeldungen** (blau): die Kacheln „Gesamtbelegung“
     (inkl. Balken je Personengruppe) und „Anmeldungen“ (inkl. Personen je Ort),
  3. **Anfragen nach Status** (grau): Tabelle nach Status.
  Die Admin-Auswertung (§7.7) behält ihre bisherige Anordnung ohne Bereiche.
- Fehlender, falscher oder deaktivierter Schlüssel → **404**. Header
  `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`; der
  Schlüssel geht wegen `Referrer-Policy: same-origin` nicht an fremde Seiten.
- Restrisiko: Wer den Link hat, sieht die Zahlen (keine personenbezogenen
  Daten). Bei Weitergabe neuen Link erzeugen.

### 7.9 Menü und Hilfe

- Reihenfolge im Admin-Menü: Veranstaltungen, Anfragen, Auswertungen,
  Einstellungen, Hilfe.
- `admin/help.php`: erklärt Admins die Logik im Hintergrund und die
  Auswirkungen von Einstellungen, gruppiert nach Aspekt (Ablauf,
  Veranstaltungen, Kontingent/automatische Bestätigung, Personengruppen, bevorzugte Orte,
  Programmpunkte/Zielgruppe/Kinderbetreuung, Anfragen, E-Mails, Absage, Infoseite/
  Formular, Auswertung/Team-Zugang, Texte, Spamschutz, Datum/Datenschutz)
  mit Inhaltsverzeichnis. Feste deutsche Texte (nicht konfigurierbar);
  Grenzwerte (99 Personen, Spamschutz-Zeiten, Rate-Limit) werden aus Code
  bzw. Config gelesen. Bei Änderungen an der Logik die Hilfe mitpflegen.
