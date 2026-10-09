# Anmeldeformular

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 5. Anmeldeformular `/konferenz/register/`

Nur erreichbar, wenn eine Veranstaltung aktiv ist und die Frist nicht
abgelaufen ist (serverseitig geprüft).

### 5.1 Felder

**Kontakt**
- Vorname (Pflicht)
- Nachname (Pflicht)
- Heimatversammlung (Pflicht): Textfeld mit `<datalist>`-Vorschlägen.
  Vorschläge = **nur** die bevorzugten Orte (global, siehe [§7.5](admin.md)).
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
  (siehe [§7.1](admin.md), bis zu fünf Gruppen; Standardnamen Erwachsene, Jugendliche,
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
- Nur wenn die Veranstaltung es erlaubt ([§7.1](admin.md) „Individuelle Aufteilung
  erlauben“): Checkbox „Anzahl individuell aufteilen“ schaltet pro Programmpunkt
  Zahlenfelder je Personengruppe frei (vorbelegt mit den Gruppenzahlen, bei
  betreuten Kindergruppen mit 0; jeweils max. = Gruppenzahl). Mobil als Block pro Programmpunkt.
- Gespeichert wird immer die Anzahl je Programmpunkt und Personengruppe.
- **Kinderbetreuung** (siehe [§7.1](admin.md)): Programmpunkte mit Betreuung zeigen den
  Hinweis „Parallel Kinderbetreuung für <Gruppennamen>“. Sind Kinder
  einer betreuten Kindergruppe eingetragen, erscheint unter den Anzahlen ein
  Hinweis, dass sie bei diesen Programmpunkten automatisch für die
  Kinderbetreuung berücksichtigt werden. In der individuellen Aufteilung sind
  betreute Kindergruppen mit 0 vorbelegt; trägt man dort Kinder > 0 ein,
  erscheint im Block des Programmpunkts der Info-Hinweis (keine Warnung)
  „Eingetragene Kinder (<Gruppennamen>) nehmen teil, die übrigen sind in der
  Kinderbetreuung eingeplant.“
- **Zielgruppe** (siehe [§7.1](admin.md), Zählweise [§5.4](registration-form.md)):
  Programmpunkte mit Zielgruppe zeigen den Hinweis „Zielgruppe: <Namen>“.
  Unter den Anzahlen steht je Programmpunkt mit Zielgruppe **nur dann** eine
  Zeile, wenn mindestens eine Person der Zielgruppe eingetragen ist **und**
  außerdem Personen eingetragen sind, die dort nicht zur Zielgruppe gehören:
  „Jugendstunde wurde für Jugendliche aktiviert.“ (bzw. „… für Erwachsene und
  Jugendliche aktiviert.“)
  Gehört **niemand** aus der Anmeldung zur Zielgruppe eines Programmpunkts,
  ist dessen Häkchen ausgegraut und abgewählt, mit Hinweis „Niemand aus deiner
  Anmeldung gehört zur Zielgruppe.“. Kommt jemand aus der Zielgruppe dazu
  (Übergang „niemand“ → „jemand“ durch eine Eingabe in den Anzahlen), wird das
  Häkchen **automatisch angekreuzt**; ein bewusst abgewähltes Häkchen bleibt
  beim Weitertippen aus. Beim Laden der Seite (auch einer bestehenden Anmeldung
  im Admin-Bearbeiten) wird nichts automatisch angekreuzt. In der
  individuellen Aufteilung erscheinen bei dem Programmpunkt nur Felder der
  Zielgruppe. Ohne JS prüft nur der Server (siehe §5.4). Die Texte duzen wie
  das übrige Formular und sind fest, nicht einstellbar.
- **Gemeinsame Infobox:** Die Hinweise zur Kinderbetreuung und zur Zielgruppe
  stehen unter den Anzahlen in **einer** Infobox (zwei Abschnitte, beide mit
  Aufzählungszeichen: der Kinder-Hinweis als Punkt mit Unterliste der
  Programmpunkte, der Zielgruppen-Hinweis als eine Zeile je Programmpunkt).
  Die Box ist nur sichtbar, wenn mindestens einer der beiden Hinweise sichtbar ist.
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

1. Validierung, CSRF, Spamschutz (§5.2), Frist.
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

### 5.4 Zählweise Programmpunkte, Zielgruppe und Kinderbetreuung

Je Anmeldung und Programmpunkt werden zwei Zahlenreihen gespeichert: wer
**beim Programmpunkt** ist (je Personengruppe) und wie viele Kinder **in der
Kinderbetreuung** sind (je betreuter Kindergruppe, Gruppen 3–5).

| Fall | Beim Programmpunkt | In der Kinderbetreuung |
|---|---|---|
| Standard, Programmpunkt angekreuzt | alle Gruppen ohne Betreuungsangebot | Kinder der betreuten Kindergruppen |
| Individuelle Aufteilung | die eingegebenen Zahlen | je betreuter Gruppe: Anzahl oben − eingegebene Zahl, sofern beim Programmpunkt mindestens eine Person der Anmeldung ist |
| Programmpunkt mit Zielgruppe, angekreuzt | nur die Gruppen der Zielgruppe | – (keine Betreuung bei Zielgruppe) |
| Programmpunkt mit Zielgruppe, individuelle Aufteilung | nur Felder der Zielgruppe; andere Gruppen immer 0, mitgeschickte Werte werden ignoriert (kein Fehler) | – |
| Angekreuzt, aber niemand aus der Anmeldung gehört zur Zielgruppe | 0 (kein Fehler); zählt nicht für „mindestens ein Programmpunkt“ | – |
| Programmpunkt nicht besucht | 0 | 0 |

Bei einem Programmpunkt mit **Zielgruppe** (§7.1) zählen nur Personen der
Zielgruppen-Gruppen; die übrigen Personen der Anmeldung zählen dort nirgends
(auch nicht in einer Betreuung). Die Regeln gelten gleich im Admin-Bearbeiten
(§7.4). Auf Kontingent, öffentliche Belegung und Mails hat die Zielgruppe
keine Auswirkung.
