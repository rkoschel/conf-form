# Konferenz-Anmeldung – Spezifikation

Stand: 01.10.2026

## 1. Ziel

Anmeldung zu einer (kostenlosen) Konferenz inkl. Infoseite, Warteliste und
schlanker Admin-Oberfläche. Technisch möglichst einfach und wartungsarm.

## 2. Technik & Betrieb

| Thema | Festlegung |
|---|---|
| Hosting | PHP-Webhost (PHP 8.2), kein vhost nötig |
| Sprache | Plain PHP (kein Framework), PDO |
| Datenbank | SQLite (`pdo_sqlite` vorhanden) |
| Frontend | Serverseitig gerendertes HTML, wenig Vanilla-JS, kein Build-Schritt |
| Datum & Uhrzeit | Anzeige und Eingabe immer `TT.MM.JJJJ` und `HH:MM` (24 h). Eingabe als Textfelder, nicht als native Date/Time-Picker (deren Format hängt von der Browser-Sprache ab, z. B. US-Format/AM-PM). Gespeichert wird ISO (`Y-m-d`, `H:i`). |
| UI-Framework | Bootstrap 5.3.8, **selbst gehostet** unter `assets/vendor/bootstrap/` (kein CDN, Datenschutz) |
| Spamschutz | Honeypot-Feld + signierter Zeitstempel + Rate-Limit pro IP (kein Captcha), siehe 5.2 |
| Mail | PHPMailer über SMTP (Postfach beim Hoster), Bibliothek im Repo eingecheckt (kein Composer) |
| Admin-Auth | HTTP Basic Auth per `.htaccess`/`.htpasswd` (ein Admin) |
| Repo | GitHub (nur Versionsverwaltung, keine Actions) |
| Deploy | Lokales Skript `deploy.sh` mit `lftp` über FTPS – lädt nur geänderte Dateien aus `web/` nach `/home/www/konferenz/`, siehe 2.1 |
| Entwicklung | Ubuntu-Laptop, `php -S localhost:8000 -t web dev/router.php` |
| Tests | PHPUnit 11 + PHPStan als `.phar` (kein Composer), `./test.sh`, Smoke-Test nach jedem Deploy, siehe §11 |

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

## 3. URLs

| URL | Zweck |
|---|---|
| `/konferenz/` | Infoseite der aktiven Konferenz |
| `/konferenz/register/` | Anmeldeformular |
| `/konferenz/cancel/?t=<token>` | Absage per Link aus der Mail |
| `/konferenz/admin/` | Verwaltung (Basic Auth) |

Jede URL ist ein Verzeichnis mit eigener `index.php` (kein mod_rewrite nötig).
Alle Links werden aus `base_url` gebildet.

## 4. Infoseite `/konferenz/`

- **Keine Veranstaltung aktiv:** nur der in den Admin-Einstellungen
  konfigurierbare Text (Klartext, kein Rich Text), siehe 7.5.
- **Aktiv, vor Anmeldefrist:** Titel, Datum, Ort, Anmeldefrist, Beschreibung,
  Ablauf (Programmpunkte; bei Kinderbetreuung Hinweis „Parallel
  Kinderbetreuung für <Namen der betreuten Kindergruppen>“), Link zu
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

## 5. Anmeldeformular `/konferenz/register/`

Nur erreichbar, wenn eine Veranstaltung aktiv ist und die Frist nicht
abgelaufen ist (serverseitig geprüft).

### 5.1 Felder

**Kontakt**
- Vorname (Pflicht)
- Nachname (Pflicht)
- Heimatversammlung (Pflicht): Textfeld mit `<datalist>`-Vorschlägen.
  Vorschläge = **nur** die bevorzugten Orte (global, siehe 7.5).
  Freitext erlaubt; wird beim Speichern normalisiert (trim, Mehrfach-
  Leerzeichen entfernen).
- E-Mail (Pflicht)
- Checkbox „Ich habe keine E-Mail-Adresse“ → E-Mail entfällt,
  Telefon wird Pflicht.
- Telefon (nur sichtbar/Pflicht bei „keine E-Mail“)

**Anzahl Teilnehmer** (ganze Zahlen ≥ 0, mindestens eine Person gesamt;
keine feste Obergrenze je Gruppe. Obergrenze: Personen gesamt ≤ max.
Teilnehmer der Veranstaltung; die Fehlermeldung nennt die Kapazität nicht.
Im Admin-Bearbeiten gilt diese Obergrenze nicht.)
- Je **Personengruppe der Veranstaltung** ein Zahlenfeld mit deren Namen
  (siehe 7.1, bis zu fünf Gruppen; Standardnamen Erwachsene, Jugendliche,
  Kindergruppe 1, Kindergruppe 2, Kindergruppe 3). Vorbelegt ist eine Person
  in der ersten Gruppe. Nicht gewählte Gruppen erscheinen nicht und werden
  als 0 gespeichert.

**Voraussichtliche Anwesenheit**
- Standard: je Programmpunkt eine Checkbox; gilt für die ganze Gruppe.
  Anfangs ist **kein** Programmpunkt ausgewählt (bewusste Entscheidung).
- **Mindestens ein Programmpunkt** muss besucht werden (angekreuzt bzw. in
  der Aufteilung > 0), sonst „Bitte mindestens einen Programmpunkt
  auswählen.“ – im Browser vor dem Absenden und auf dem Server geprüft
  (gilt auch im Admin-Bearbeiten).
- Nur wenn die Veranstaltung es erlaubt (7.1 „Individuelle Aufteilung
  erlauben“): Checkbox „Anzahl individuell aufteilen“ schaltet pro Programmpunkt
  Zahlenfelder je Personengruppe frei (vorbelegt mit den Gruppenzahlen, bei
  betreuten Kindergruppen mit 0; jeweils max. = Gruppenzahl). Mobil als Block pro Programmpunkt.
- Gespeichert wird immer die Anzahl je Programmpunkt und Personengruppe.
- **Kinderbetreuung** (siehe 7.1): Programmpunkte mit Betreuung zeigen den
  Hinweis „Parallel Kinderbetreuung für <Gruppennamen>“. Sind Kinder
  einer betreuten Kindergruppe eingetragen, erscheint unter den Anzahlen ein
  Hinweis, dass sie bei diesen Programmpunkten automatisch für die
  Kinderbetreuung berücksichtigt werden. In der individuellen Aufteilung sind
  betreute Kindergruppen mit 0 vorbelegt; trägt man dort Kinder > 0 ein,
  erscheint im Block des Programmpunkts der Info-Hinweis (keine Warnung)
  „Eingetragene Kinder (<Gruppennamen>) nehmen teil, die übrigen sind in der
  Kinderbetreuung eingeplant.“
- Sofortprüfung beim Tippen (JS, zusätzlich zur Prüfung auf dem Server):
  Anzahlen müssen ganze Zahlen ab 0 sein; in der Aufteilung höchstens die
  oben eingestellte Anzahl der Gruppe („Höchstens n (Anzahl oben)“).
  Sinkt die Anzahl oben, werden zu hohe Aufteilungswerte angepasst; steigt
  sie, wachsen die Aufteilungsfelder dieser Gruppe mit, die auf dem bisherigen
  Maximum standen (bewusst kleinere Werte und betreute Kindergruppen auf 0
  bleiben). Ein gerade geleertes Feld oben ändert die Aufteilung nicht. Solange
  ein Feld fehlerhaft ist, wird nicht abgeschickt (Fokus aufs erste Feld).
- Ausschalten der Aufteilung: Entsprechen alle Werte der Vorbelegung
  (= Anzahl oben), wird direkt ausgeschaltet. Sonst Dialog „Aufteilung
  zurücksetzen?“ (Aufteilung behalten / Zurücksetzen); beim Zurücksetzen
  gehen die Werte auf die Vorbelegung zurück.
- Hinweis unter „Voraussichtliche Anwesenheit“: „Deine Angaben helfen uns,
  die Räumlichkeiten besser zu nutzen und möglichst vielen die Teilnahme zu
  ermöglichen.“

**Nachricht an uns** (optional): Textfeld, höchstens 2000 Zeichen,
Klartext (Steuerzeichen außer Zeilenumbruch/Tab werden entfernt; gespeichert
per Prepared Statement, Ausgabe immer HTML-escaped). Grauer Platzhalter einstellbar (Einstellungen → Anmeldeformular),
Standard „Falls wir noch etwas berücksichtigen sollten, lass es uns gerne
wissen.“ Sichtbar in der Anfragen-Liste (gekürzt, ganzer Text als Tooltip)
und im Bearbeiten-Formular; nicht in Auswertung oder Mails.

**Datenschutz:** Kein Checkbox-Zwang. Unter dem Button steht ein Hinweis:
„Mit dem Absenden werden deine Angaben zur Organisation der Veranstaltung
gespeichert. Details in der [Datenschutzerklärung].“ (Link = `privacy_url`).

**Button:** „Teilnahme anfragen“

### 5.2 Spamschutz

Kein Captcha. Drei Maßnahmen, alle serverseitig geprüft:

1. **Honeypot:** zusätzliches Textfeld, per CSS versteckt
   (`autocomplete="off"`, `tabindex="-1"`). Ist es ausgefüllt, wird die
   Anfrage verworfen (Antwort wie bei Erfolg, damit Bots nichts lernen;
   nichts wird gespeichert, keine Mail).
2. **Signierter Zeitstempel:** Hidden-Feld mit Zeitpunkt der
   Formularauslieferung + HMAC (`app_secret`). Abgelehnt wird, wenn die
   Signatur ungültig ist, das Formular nach weniger als **3 Sekunden**
   abgeschickt wurde oder älter als **2 Stunden** ist (Meldung: „Bitte
   Formular neu laden und erneut absenden.“).
3. **Rate-Limit pro IP:** jeder Absendeversuch (POST) erzeugt eine Zeile
   in `rate_limit`. Vor der Prüfung werden alle Zeilen gelöscht, die älter
   als das Zeitfenster sind (kein Cron nötig, Daten werden nur kurz
   gespeichert). Liegt die Zahl der Versuche im Zeitfenster über dem Limit
   (Standard 10 / 60 Min.), wird abgelehnt. Die IP wird nur als
   HMAC-SHA256 mit `app_secret` gespeichert (ein einfacher Hash wäre bei
   IPv4 leicht zurückzurechnen). Das Limit ist bewusst großzügig, weil
   mehrere Familien dieselbe IP haben können (Gemeinde-WLAN, Mobilfunk-NAT).

### 5.3 Logik beim Absenden

1. Validierung, CSRF, Spamschutz (5.2), Frist.
2. In einer Transaktion (`BEGIN IMMEDIATE`):
   - Gruppengröße = Summe aller Personen über alle Personengruppen
     (**alle Gruppen zählen** zum Kontingent).
   - Belegt = Summe der Gruppengrößen aller Anmeldungen mit Status
     `confirmed`.
   - Ort ist bevorzugt **und** Belegt + Gruppengröße ≤ max. Teilnehmer
     **und** Personen gesamt ≤ 99 → Status `confirmed`.
   - Sonst → Status `pending` (Warteliste). Auch bevorzugte Orte kommen auf
     die Warteliste, wenn das Kontingent nicht reicht oder die Anmeldung
     mehr als 99 Personen umfasst (Prüfung durch den Admin).
3. Bei vorhandener E-Mail: Mail an Teilnehmer (bestätigt bzw. Warteliste)
   mit Absage-Link. Versand wird im Mail-Protokoll gespeichert.
4. Ergebnisseite: „Anmeldung bestätigt“ bzw. „Du stehst auf der Warteliste“.

Ohne E-Mail (nur Telefon): keine Mail; Kontakt erfolgt telefonisch durch den
Admin.

### 5.4 Zählweise Programmpunkte und Kinderbetreuung

Je Anmeldung und Programmpunkt werden zwei Zahlenreihen gespeichert: wer
**beim Programmpunkt** ist (je Personengruppe) und wie viele Kinder **in der
Kinderbetreuung** sind (je betreuter Kindergruppe, Gruppen 3–5).

| Fall | Beim Programmpunkt | In der Kinderbetreuung |
|---|---|---|
| Standard, Programmpunkt angekreuzt | alle Gruppen ohne Betreuungsangebot | Kinder der betreuten Kindergruppen |
| Individuelle Aufteilung | die eingegebenen Zahlen | je betreuter Gruppe: Anzahl oben − eingegebene Zahl, sofern beim Programmpunkt mindestens eine Person der Anmeldung ist |
| Programmpunkt nicht besucht | 0 | 0 |

## 6. Absage `/konferenz/cancel/`

- Link mit zufälligem Token (32 Byte, hex).
- Seite zeigt Name + Veranstaltung und einen Button „Teilnahme absagen“
  (POST, damit Link-Vorschauen in Mailprogrammen nicht versehentlich
  absagen).
- Status → `cancelled`, Platz wird frei. Kein automatisches Nachrücken.
- Ist die Anmeldung bereits storniert oder abgelehnt, erscheint nur ein
  Hinweis, kein Button. Ungültiger Token → neutrale Fehlermeldung.

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
  **Sobald mindestens eine Anmeldung existiert, ist der Ablauf gesperrt**
  (nur Anzeige mit Hinweis). Änderungen am Programm nach Eröffnung der
  Anmeldung sind in Version 1 nicht vorgesehen.
- Bevorzugte Orte werden nicht je Veranstaltung, sondern global in den
  Einstellungen gepflegt (siehe 7.5); das Formular verweist dorthin.
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
  Team-Zugang (§7.8). „Speichern“ speichert alle Text-Tabs; danach öffnet
  sich wieder der Tab, aus dem gespeichert wurde (auch per Adresse, z. B.
  `#e-mails`).

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
  Programmpunkte/Kinderbetreuung, Anfragen, E-Mails, Absage, Infoseite/
  Formular, Auswertung/Team-Zugang, Texte, Spamschutz, Datum/Datenschutz)
  mit Inhaltsverzeichnis. Feste deutsche Texte (nicht konfigurierbar);
  Grenzwerte (99 Personen, Spamschutz-Zeiten, Rate-Limit) werden aus Code
  bzw. Config gelesen. Bei Änderungen an der Logik die Hilfe mitpflegen.

## 8. Datenmodell (SQLite)

Alle Bezeichner und gespeicherten Werte (Status, Mail-Typen) auf Englisch;
die Übersetzung in deutsche UI-Texte erfolgt zentral an einer Stelle im Code.

```sql
CREATE TABLE settings (
  key   TEXT PRIMARY KEY,                       -- z. B. inactive_text, preferred_places
  value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE events (
  id                    INTEGER PRIMARY KEY,
  title                 TEXT NOT NULL,
  date                  TEXT NOT NULL,          -- YYYY-MM-DD
  location              TEXT NOT NULL,
  description           TEXT NOT NULL DEFAULT '',
  registration_deadline TEXT NOT NULL,          -- YYYY-MM-DDTHH:MM, lokale Zeit in `timezone`
  timezone              TEXT NOT NULL DEFAULT 'Europe/Berlin',
  max_participants      INTEGER NOT NULL,
  organizer_name        TEXT NOT NULL DEFAULT '',
  organizer_email       TEXT NOT NULL DEFAULT '',
  allow_split           INTEGER NOT NULL DEFAULT 1, -- individuelle Aufteilung erlaubt
  person_groups         TEXT NOT NULL DEFAULT '', -- JSON {"group_1":"Erwachsene",…}: gewählte Gruppen + Namen; '' = alle, Standardnamen
  active                INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX one_active_event ON events(active) WHERE active = 1;

CREATE TABLE event_slots (
  id       INTEGER PRIMARY KEY,
  event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  time     TEXT NOT NULL,                       -- HH:MM
  label    TEXT NOT NULL,
  sort     INTEGER NOT NULL DEFAULT 0,
  childcare TEXT NOT NULL DEFAULT ''            -- betreute Kindergruppen, z. B. 'group_4,group_5'; '' = keine
);

CREATE TABLE registrations (
  id           INTEGER PRIMARY KEY,
  event_id     INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  created_at   TEXT NOT NULL,                   -- UTC, ISO 8601
  first_name   TEXT NOT NULL,
  last_name    TEXT NOT NULL,
  congregation TEXT NOT NULL,
  email        TEXT,
  phone        TEXT,
  no_email     INTEGER NOT NULL DEFAULT 0,
  group_1      INTEGER NOT NULL DEFAULT 0,
  group_2      INTEGER NOT NULL DEFAULT 0,
  group_3      INTEGER NOT NULL DEFAULT 0,
  group_4      INTEGER NOT NULL DEFAULT 0,
  group_5      INTEGER NOT NULL DEFAULT 0,
  custom_split INTEGER NOT NULL DEFAULT 0,
  message      TEXT NOT NULL DEFAULT '',          -- „Nachricht an uns“ (optional)
  status       TEXT NOT NULL CHECK (status IN
                 ('pending','confirmed','cancelled','rejected')),
  cancel_token TEXT NOT NULL UNIQUE
);
CREATE INDEX registrations_event ON registrations(event_id);

CREATE TABLE registration_slots (
  registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
  slot_id         INTEGER NOT NULL REFERENCES event_slots(id) ON DELETE CASCADE,
  group_1         INTEGER NOT NULL DEFAULT 0,
  group_2         INTEGER NOT NULL DEFAULT 0,
  group_3         INTEGER NOT NULL DEFAULT 0,
  group_4         INTEGER NOT NULL DEFAULT 0,
  group_5         INTEGER NOT NULL DEFAULT 0,
  childcare_group_3 INTEGER NOT NULL DEFAULT 0, -- Kinder in der Betreuung (siehe 5.4)
  childcare_group_4  INTEGER NOT NULL DEFAULT 0,
  childcare_group_5  INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (registration_id, slot_id)
);

CREATE TABLE mail_log (
  id              INTEGER PRIMARY KEY,
  registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
  type            TEXT NOT NULL CHECK (type IN
                    ('received_confirmed','received_waitlist',
                     'confirmed','rejected')),
  sent_at         TEXT NOT NULL,                -- UTC, ISO 8601
  success         INTEGER NOT NULL,
  error           TEXT
);

CREATE TABLE rate_limit (
  ip_hash    TEXT NOT NULL,                     -- HMAC-SHA256(IP, app_secret)
  created_at TEXT NOT NULL                      -- UTC, ISO 8601
);
CREATE INDEX rate_limit_lookup ON rate_limit(ip_hash, created_at);
```

- `PRAGMA foreign_keys = ON` bei jeder Verbindung.
- **Migrationen:** Die Schema-Version steht in `PRAGMA user_version`. Eine
  neue DB wird aus `schema.sql` (immer aktueller Stand) angelegt und auf die
  neueste Version gesetzt; eine bestehende DB wird beim ersten Zugriff per
  `ALTER TABLE` in einer Transaktion auf den neuesten Stand gebracht
  (Version 1: Kinderbetreuung; Version 2: bevorzugte Orte global;
  Version 3: Personengruppen – Spalten adults/youth/kids_7_12/kids_3_6/
  kids_0_2 → group_1…group_5, childcare_kids_* → childcare_group_3…5,
  events.person_groups; benötigt SQLite ≥ 3.25 für RENAME COLUMN;
  Version 4: events.allow_split, registrations.message).
- Existiert die DB-Datei nicht, wird sie beim ersten Aufruf aus
  `schema.sql` angelegt.
- Technische Zeitstempel (`created_at`, `sent_at`) in UTC; Anzeige in der
  Zeitzone der Veranstaltung.
- IP-Adressen werden nur als HMAC gespeichert und nach Ablauf des
  Rate-Limit-Zeitfensters gelöscht.

## 9. Mails

| Anlass | `mail_log.type` | Inhalt |
|---|---|---|
| Eingang, bestätigt | `received_confirmed` | Bestätigung, Eckdaten, Personenzahlen, Absage-Link |
| Eingang, Warteliste | `received_waitlist` | Hinweis Warteliste, Personenzahlen, Absage-Link |
| Admin: bestätigt (optional) | `confirmed` | Bestätigung, Absage-Link |
| Admin: abgelehnt (optional) | `rejected` | Absage |

Absender: konfiguriertes SMTP-Postfach; Reply-To: Veranstalter-E-Mail.
Texte als einfache PHP-Templates im Repo.

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
├── deploy.sh                      Deployment per lftp/FTPS (siehe 2.1)
├── deploy.env.example             Vorlage für FTP-Zugangsdaten und APP_URL
└── SPEC.md
```

## 13. Weitere Punkte
- URL der Datenschutzerklärung (`privacy_url`): https://christen-in-hamm.de/datenschutzerklaerung/

## 14. Offene Punkte

- **Mailversand einrichten (Stand 09.10.2026):** Absender-Postfach
  `info@christen-in-hamm.de` beim Hoster (webhostone). Outlook.com scheidet
  aus (nur noch OAuth2), GMX nur als Notlösung (Absender wäre die
  GMX-Adresse, Zugriff wird bei Nichtnutzung automatisch abgeschaltet).
  - [ ] **SPF, DKIM, DMARC setzen** – bisher alle drei leer (geprüft
    09.10.2026). Im webhostone-Adminpanel: E-Mail → E-Mail Konto → E-Mail
    Domain → `christen-in-hamm.de` → „DKIM“ (älteres KundenControlCenter:
    Domains → „DNS-Einträge anpassen“ → „DKIM setzen“; setzt alle drei).
    Zielwerte:
    - SPF (TXT `christen-in-hamm.de`):
      `v=spf1 mx a include:spf.serverdomain.org ~all`
      (deckt den Server 89.107.184.82 ab; weitere Absender wie Newsletter
      oder Kontaktformulare der Landingpage vorher klären und ergänzen)
    - DKIM (TXT `<selector>._domainkey.christen-in-hamm.de`): vom Hoster erzeugt
    - DMARC (TXT `_dmarc.christen-in-hamm.de`):
      `v=DMARC1; p=none; rua=mailto:info@christen-in-hamm.de`,
      nach einigen Wochen ohne Auffälligkeiten auf `p=quarantine`
  - [ ] **Einträge prüfen** (DNS-Abfrage, DKIM-Selector ermitteln) und
    **Testmail** an ein Gmail-Konto: SPF, DKIM und DMARC jeweils „PASS“.
  - [ ] **SMTP in `config.prod.php` eintragen:** Host `mail.christen-in-hamm.de`,
    Port 587, `encryption` `tls` (STARTTLS), Benutzer und Absender
    `info@christen-in-hamm.de`, Passwort; danach `./deploy.sh setup`
    (nur durch den Nutzer).
- Für später: HSTS-Header (`Strict-Transport-Security`). Nicht aus der App
  setzen, da er für die ganze Domain gilt (auch die Landingpage im selben
  Webroot); besser zentral beim Hoster bzw. für die gesamte Domain klären.
- **Geplant (noch nicht umgesetzt, Stand 09.10.2026): Zielgruppe je
  Programmpunkt.** Programmpunkte können exklusiv für eine oder mehrere
  Personengruppen sein. Bezeichnung: **„Zielgruppe“** (statt „eingeschränkt“,
  wirkt einladend statt ausschließend). Abgestimmte Festlegungen:
  - Admin: je Programmpunkt Schalter **„Zielgruppe festlegen“**; wenn an, Auswahl
    aus den gewählten Personengruppen der Veranstaltung (mindestens eine),
    für wen der Programmpunkt gilt. Gesperrt wie Ablauf/Kinderbetreuung,
    sobald es Anmeldungen gibt. Datenmodell: neue Spalte an `event_slots`
    (erlaubte Gruppen, leer = alle) als Migration 5.
  - **Kombinierbar mit Kinderbetreuung** (z. B. „Bibelstunde“ nur für
    Erwachsene, parallel Kinderbetreuung für Kindergruppen): nicht erlaubte
    Kindergruppen zählen dann in der Betreuung, nicht beim Programmpunkt.
  - Zählweise: beim Programmpunkt zählen nur erlaubte Gruppen; in der
    individuellen Aufteilung erscheinen dort nur deren Felder.
  - Hinweise: am Programmpunkt (Infoseite, Formular) z. B. „Zielgruppe:
    Jugendliche“ bzw. „Zielgruppe: Erwachsene und Jugendliche“; oben unter
    den Anzahlen **nur wenn eingetragene Personen betroffen sind** (eine
    eingetragene Gruppe gehört bei einem Programmpunkt nicht zur Zielgruppe),
    z. B. „Bei ‚Jugendstunde‘ nehmen von euch nur die Jugendlichen teil.“
  - Gehört niemand aus der Anmeldung zur Zielgruppe eines Programmpunkts
    teilnehmen, ist dessen Häkchen **ausgegraut** mit Hinweis (z. B. „Von
    euch nimmt hier niemand teil“); zählt nicht für „mindestens ein
    Programmpunkt“.
  - Auswertung: Gruppen außerhalb der Zielgruppe beim Programmpunkt als „–“.
  - Hilfeseite, CLAUDE.md und Tests mitpflegen.
