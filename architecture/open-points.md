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
