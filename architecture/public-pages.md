# Öffentliche Seiten

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 3. URLs

| URL | Zweck |
|---|---|
| `/konferenz/` | Infoseite der aktiven Konferenz |
| `/konferenz/register/` | Anmeldeformular |
| `/konferenz/cancel/?t=<token>` | Absage per Link aus der Mail |
| `/konferenz/admin/` | Verwaltung (Basic Auth) |

Jede URL ist ein Verzeichnis mit eigener `index.php` (kein mod_rewrite nötig).
Alle Links werden aus `base_url` gebildet.

**Leiste auf allen öffentlichen Seiten** (Infoseite, Anmeldung, Absage,
Ergebnis- und Fehlerseiten, Team-Seite; nicht im Admin): oben eine feste Leiste
mit **nur einem Button** rechts, „Zurück zur Hauptseite“, Ziel `home_url` aus der
Konfiguration (§2). Die Leiste bleibt beim Scrollen sichtbar (`position: sticky`),
ist **flach** (kein Schatten, keine Rundung) und läuft über die **gesamte
Breite**; eine feine Linie unten (Rahmenfarbe des Themes, hell und dunkel)
grenzt sie vom Inhalt ab. Der Link trägt `referrerpolicy="no-referrer"`, damit die aktuelle URL
(z. B. der Team-Schlüssel) nicht an die Hauptseite weitergegeben wird. Die
Auswahl des Farbschemas bleibt im Footer.

## 4. Infoseite `/konferenz/`

- **Keine Veranstaltung aktiv:** nur der in den Admin-Einstellungen
  konfigurierbare Text (Klartext, kein Rich Text), siehe [§7.5](admin.md).
- **Aktiv, vor Anmeldefrist:** Titel, Datum, Ort, Anmeldefrist, Beschreibung,
  Ablauf (Programmpunkte; bei Kinderbetreuung Hinweis „Parallel
  Kinderbetreuung für <Namen der betreuten Kindergruppen>“, bei Zielgruppe
  Hinweis „Zielgruppe: <Namen>“, z. B. „Zielgruppe: Jugendliche“ bzw.
  „Zielgruppe: Erwachsene und Jugendliche“), Link zu
  `https://christen-in-hamm.de/info/`, Button „Anmelden“.
- **Aktiv, nach Anmeldefrist:** Eckdaten wie oben, fester Text
  „Der Anmeldezeitraum ist abgelaufen.“, Link zur Info-Seite, kein Button.
- **Belegung** (nur solange die Anmeldung offen ist, oberhalb des Buttons
  „Anmelden“): gestapelter Balken mit Legende **ausschließlich in ganzen
  Prozent der Kapazität**, getrennt nach Bestätigt / Warteliste (offen) /
  Frei, Summe 100 %. Gezählt werden alle Personengruppen; bestätigte über dem Kontingent
  werden auf 100 % gekappt, die Warteliste auf den Rest. **Niemals absolute
  Zahlen** (weder Personen noch Kapazität), auch nicht in Attributen.
- Steht „Frei“ bei 0 %, erscheint unter der Legende: „Aktuell scheint die
  Veranstaltung ausgebucht zu sein. Eine Anmeldung lohnt sich trotzdem, um
  auf die Warteliste zu kommen. Sobald Plätze wieder frei sind (durch Absagen
  oder Änderungen), melden wir uns bei dir.“ Der Button bleibt.
- Die Frist wird in der Zeitzone der Veranstaltung ausgewertet und angezeigt.

## 6. Absage `/konferenz/cancel/`

- Link mit zufälligem Token (32 Byte, hex).
- Seite zeigt Name + Veranstaltung und einen Button „Teilnahme absagen“
  (POST, damit Link-Vorschauen in Mailprogrammen nicht versehentlich
  absagen).
- Status → `cancelled`, Platz wird frei. Kein automatisches Nachrücken.
- Ist die Anmeldung bereits storniert oder abgelehnt, erscheint nur ein
  Hinweis, kein Button. Ungültiger Token → neutrale Fehlermeldung.
