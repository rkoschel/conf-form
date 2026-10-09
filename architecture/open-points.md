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
