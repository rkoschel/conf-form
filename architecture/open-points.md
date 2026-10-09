# Weitere und offene Punkte

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

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
- **Geplant (in Umsetzung, Stand 09.10.2026): Zielgruppe je
  Programmpunkt.** Programmpunkte können exklusiv für eine oder mehrere
  Personengruppen sein. Bezeichnung: **„Zielgruppe“** (statt „eingeschränkt“,
  wirkt einladend statt ausschließend). Nach der Umsetzung wandern die
  Festlegungen in die zugehörigen Abschnitte (§4, §5.1, §5.4, §7.1, §7.4,
  §7.7, §8). Abgestimmte Festlegungen:
  - Admin: je Programmpunkt Schalter **„Zielgruppe festlegen“**; wenn an,
    Auswahl aus den gewählten Personengruppen der Veranstaltung (mindestens
    eine), für wen der Programmpunkt gilt. Gesperrt wie Ablauf/Kinderbetreuung,
    sobald es Anmeldungen gibt.
  - **Zielgruppe und Kinderbetreuung schließen sich aus:** Ein Programmpunkt
    mit Zielgruppe hat keine Kinderbetreuung. Im Formular wird der jeweils
    andere Schalter gesperrt; serverseitig Fehler „Programmpunkt n:
    Zielgruppe und Kinderbetreuung sind nicht kombinierbar.“
  - Wird auf Veranstaltungsebene eine Personengruppe abgewählt, fällt sie aus
    der Zielgruppe heraus (wie bei der Kinderbetreuung); bleibt keine Gruppe
    übrig, Fehler „Programmpunkt n: Zielgruppe auswählen.“
  - Sind alle gewählten Personengruppen der Veranstaltung Zielgruppe, wird
    keine Zielgruppe gespeichert (gleiche Wirkung, kein Hinweis „Zielgruppe:
    alle“).
  - Datenmodell: neue Spalte `event_slots.target_groups` (Format wie
    `childcare`, z. B. `group_1,group_2`; leer = alle) als Migration 5.
  - Zählweise: Beim Programmpunkt zählen nur Gruppen der Zielgruppe; die
    übrigen Personen der Anmeldung zählen dort nirgends (auch nicht in einer
    Betreuung). In der individuellen Aufteilung erscheinen beim Programmpunkt
    nur Felder der Zielgruppe; andere Gruppen werden serverseitig als 0
    gespeichert, auch wenn sie mitgeschickt werden.
  - Hinweis am Programmpunkt (Infoseite, Formular, Admin-Liste/Auswertung
    nach Bedarf): „Zielgruppe: Jugendliche“ bzw. „Zielgruppe: Erwachsene und
    Jugendliche“ (Namen wie bei der Kinderbetreuung verbunden).
  - Hinweis unter den Anzahlen **nur, wenn eingetragene Personen betroffen
    sind** (eine eingetragene Gruppe gehört bei einem Programmpunkt nicht zur
    Zielgruppe), je Programmpunkt eine Zeile: „Bei „Jugendstunde“ nehmen nur
    Personen der Zielgruppe teil (Jugendliche).“
  - Gehört niemand aus der Anmeldung zur Zielgruppe eines Programmpunkts, ist
    dessen Häkchen **ausgegraut** (und abgewählt) mit Hinweis „Niemand aus
    deiner Anmeldung gehört zur Zielgruppe.“ Serverseitig: angekreuzt, aber
    niemand aus der Zielgruppe → 0 beim Programmpunkt, kein Fehler; zählt
    nicht für „mindestens ein Programmpunkt“. Ohne JS prüft nur der Server.
  - Texte duzen (Einzahl, wie das übrige Formular) und sind fest, nicht
    einstellbar.
  - Admin-Bearbeiten: dieselben Regeln (Alt-Daten gibt es nicht, weil die
    Zielgruppe ab der ersten Anmeldung gesperrt ist).
  - Auswertung: Gruppen außerhalb der Zielgruppe beim Programmpunkt als „–“.
  - Keine Auswirkung auf Kontingent, öffentliche Belegung und Mails.
  - Hilfeseite, CLAUDE.md (Schnittstellen) und Tests mitpflegen.
