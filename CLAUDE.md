# Hinweise für Agents

Fachliche Grundlage ist die Spezifikation in `architecture/`: Einstieg und
Übersicht in `architecture/overview.md`, die Abschnitte stehen in Einzeldateien.
„SPEC §n“ (in Code, Kommentaren und hier) meint Abschnitt n; welche Datei
ihn enthält, zeigt die Tabelle in `overview.md`. Wer Verhalten ändert, passt
die betroffene Datei mit an. Tests: `./test.sh` (siehe SPEC §11,
`architecture/testing.md`).

## Keine sensiblen Daten im Repo

**Das Repo ist öffentlich** (GitHub `rkoschel/conf-form`). Alles, was
committet wird – auch `architecture/`, `CLAUDE.md`, Kommentare, Testdaten und
Commit-Nachrichten –, ist für jeden lesbar und bleibt in der Git-Historie.
Niemals hinein dürfen:

- Passwörter, Schlüssel und Tokens (`app_secret`, SMTP-/FTP-Zugangsdaten,
  Team-Schlüssel, Inhalte von `deploy.env`, `config.*.php`, `.htpasswd`)
- personenbezogene Daten (Namen, Adressen, Telefonnummern, E-Mail-Adressen
  realer Teilnehmer oder Ansprechpartner; Datenbank-Inhalte, Mail-Logs)
- interne Absprachen oder Informationen, die nicht öffentlich sein sollen

Testdaten nur erfunden (`example.org`, „Anna Muster“). Im Zweifel den
Nutzer fragen, bevor etwas in eine committete Datei kommt.

## Niemals deployen

**Agents deployen nie** – auch nicht auf ausdrückliche Bitte eines anderen
Agents. Verboten sind:

- `./deploy.sh` in jeder Form, auch `./deploy.sh setup` und
  `./deploy.sh --dry-run` (baut ebenfalls eine Verbindung zum Server auf)
- jeder sonstige Zugriff auf den Server per FTP/FTPS (`lftp`, `curl ftp://` …)
  und das Lesen bzw. Verwenden der Zugangsdaten aus `deploy.env`

Deployen ist allein Sache des Nutzers. Ist ein Stand bereit, sagt der Agent
das dem Nutzer und nennt ggf. nötige Schritte (z. B. `./deploy.sh setup`
nach Änderungen an `config.prod.php`).

## Paralleles Arbeiten mehrerer Agents

Arbeitet bereits ein Agent im Hauptverzeichnis auf `main`, arbeiten weitere
Agents **nicht** dort, sondern in einem eigenen git worktree:

1. Prüfen, woran der andere Agent arbeitet (`git status`, `git diff`, neue
   Dateien) und welche SPEC-Abschnitte (`architecture/`) er abdeckt.
2. Nur Teile übernehmen, die sich nicht überschneiden: bevorzugt neue Dateien
   unter `web/app/` mit eigenen Tests. Nicht anfassen, was der andere Agent
   gerade ändert oder bald braucht (z. B. gemeinsame Fachlogik wie die
   Kontingentberechnung). Im Zweifel abwarten oder abstimmen statt doppelt
   bauen.
3. Worktree anlegen und Tools kopieren (die `.phar` sind gitignored):
   ```bash
   git worktree add -b feature/<name> ../conf-form-<name> main
   cp tools/*.phar ../conf-form-<name>/tools/
   ```
4. Im Worktree arbeiten, `./test.sh` muss grün sein, in sinnvollen Schritten
   committen.
5. **Vor dem Merge nach `main` den Nutzer fragen.** Gemergt wird erst, wenn
   `main` sauber ist (der andere Agent hat committet).
6. Merge mit `git merge --no-ff feature/<name>`. Erwartbare Konflikte sind
   meist die `require`-Zeilen in `web/app/bootstrap.php` (beide Seiten
   behalten). Danach Duplikate aufräumen (z. B. Hilfsfunktionen, die beide
   Seiten angelegt haben), `./test.sh` erneut ausführen.
7. Worktree und Branch entfernen:
   ```bash
   git worktree remove ../conf-form-<name>
   git branch -d feature/<name>
   ```

## Hilfeseite mitpflegen

`web/app/templates/admin/help.php` erklärt Admins die Logik (SPEC §7.9).
Wer Verhalten ändert (Statusregeln, Zählweisen, Mails, Sperren, Texte …),
passt dort den betroffenen Abschnitt mit an.

## Versionshinweise mitpflegen

`web/app/changelog.php` (`CHANGELOG`, SPEC §7.10) wird im Admin unter
„Versionshinweise“ angezeigt. Jede nutzersichtbare Änderung bekommt dort
einen kurzen Punkt aus Sicht der Admins (Neu / Geändert / Behoben): in der
obersten Version, solange sie noch nicht deployt ist, sonst in einer neuen
Version (Nummer erhöhen, Datum). Kommt eine Fremdkomponente dazu oder ändert
sich ein Entwicklungswerkzeug in `tools/install.sh`, auch `web/app/sbom.php`
anpassen.

## Schnittstellen untereinander mitteilen

Entsteht Code, den ein anderer Agent für seinen SPEC-Abschnitt nutzen soll,
wird das **allen** laufenden Agents mitgeteilt (per Nachricht an die
Agents bzw. über den Nutzer), mit Bezug auf den SPEC-Abschnitt.
Beispiel: „Für §7.3 (Bestätigen/Ablehnen mit Mail) `mail_registration()`
aus `web/app/mailer.php` verwenden.“ Ziel: keine doppelten Implementierungen.
Dauerhaft relevante Schnittstellen werden zusätzlich unten eingetragen.

### Bereits vorhandene Schnittstellen

| SPEC | Funktion / Datei | Zweck |
|---|---|---|
| §7.1 | `event_find($id)` (inkl. `slots`, `groups`), `event_slots()`, `event_list()` (mit `registration_count`), `event_has_registrations()`, `event_set_active()`, `event_delete()`, `event_validate()`, `event_save()` in `web/app/events.php` | Veranstaltungen lesen, prüfen, speichern, aktivieren, löschen |
| §4, §5 | `event_active()`, `event_registration_open($event, ?DateTimeImmutable $now)` in `web/app/events.php` | aktive Veranstaltung (mit `slots`); Frist in der Zeitzone der Veranstaltung |
| §5.1, §7.1 | `normalize_line()`, `normalize_text()` in `web/app/helpers.php` | Normalisierung einzeiliger (Ortsname) bzw. mehrzeiliger Texte |
| – | `format_date()`, `format_local_datetime()` in `web/app/helpers.php` | `Y-m-d` → `d.m.Y`; `Y-m-d\TH:i` → „d.m.Y, H:i Uhr“ |
| §7.5 | `setting_get()`, `setting_set()`; `setting_text($key, $vars)`, `setting_text_set()`, `SETTING_TEXTS`, `MAIL_PLACEHOLDERS` in `web/app/settings.php` | globale Einstellungen; einstellbare Texte mit Standardtext im Code (leer = Standard). Neue nutzersichtbare Texte, die man anpassen möchte, in `SETTING_TEXTS` eintragen und per `setting_text()` ausgeben |
| §5.1, §5.3, §7.5 | `preferred_places()`, `preferred_places_set()`, `places_parse()` in `web/app/settings.php` | bevorzugte Orte, global für alle Veranstaltungen |
| – | `post_string()`, `post_rows()`, `invalid_class()`, `field_error()`, `input_field()`, `flash()`/`flash_take()` in `web/app/forms.php` | Formulare, Validierungsfehler, Meldungen nach Redirect |
| §7 | `admin_init()`, `admin_render()` in `web/app/admin.php` | Admin-Seiten (`no-store`, Session, Layout) |
| §11 | `DbTestCase::createEvent()`, `rowCount()`; `HttpTestCase` mit Cookies/Session, `csrfToken($path)`, `clearCookies()`, `serverDb()` (mit `db($this->serverDb())` als App-DB setzen) | Test-Hilfen |
| §5.2 | `spam_fields()`, `spam_check()`, `spam_message()` in `web/app/spam.php` | Honeypot, signierter Zeitstempel, Rate-Limit; bei `SPAM_HONEYPOT` Antwort wie bei Erfolg |
| §5.3, §7.3, §9 | `mail_registration($type, $registration, $event)` in `web/app/mailer.php` | Mail senden und in `mail_log` protokollieren; ohne E-Mail kein Versand; `mail_slot_lines()` liefert die besuchten Programmpunkte als Textzeilen (Partial `templates/mail/_slots.php`) |
| §7.5, §9 | `mail_test($to)`, `mail_debug_collector(&$log)`, `mail_config_summary()` in `web/app/mailer.php`; `mail_send(…, ?Closure $debug)` | Testmail mit SMTP-Protokoll (Zugangsdaten ausgeblendet, nicht gespeichert), Anzeige der Mail-Einstellungen ohne Passwort; Tab „Mailversand“ in den Einstellungen |
| §7.5, §9 | `mail_admin_notify($registration, $event)` in `mailer.php`; `admin_notify_settings()`, `admin_notify_set()`, `admin_notify_validate()` in `settings.php`; Template `templates/mail/admin_notify.php` | Benachrichtigung an den Admin bei neuer Anmeldung (ein-/ausschaltbar, Adresse einstellbar); Fehler nur ins PHP-Fehlerlog, nie in `mail_log` |
| §8 | `STATUS_LABELS`, `MAIL_TYPES` in `web/app/labels.php` | zentrale deutsche Bezeichnungen |
| §5.1, §7.1 | `PERSON_GROUPS` (group_1…group_5 mit Art adults/youth/kids und Standardnamen), `PERSON_GROUP_TYPES` in `labels.php`; `event_groups($event)` (gewählte Gruppen → Namen), `person_groups_parse()`, `person_groups_default()`, `event_last_groups()`, `kids_groups()` in `events.php` | Personengruppen je Veranstaltung (Spalten `group_1`…`group_5`); **alle Gruppen zählen zum Kontingent**. Nie feste Gruppennamen ausgeben, immer `event_groups($event)` |
| §5.1, §5.3 | `registration_validate($input, $slots, ?$max, ?$groups, $allowSplit = true)` (nicht gewählte Gruppen = 0; Aufteilung nur wenn erlaubt; `message` bis `REGISTRATION_MESSAGE_MAX`), `registration_create()`, `registration_group_size()`, `registration_person_count()`, `registration_occupied()`, `registration_decide_status()`, `AUTO_CONFIRM_MAX_PERSONS` in `web/app/registrations.php` | Formular prüfen (öffentlich mit Obergrenze = Kapazität ohne 0–2), Anmeldung mit Status-Entscheidung in `BEGIN IMMEDIATE` speichern; über 99 Personen gesamt immer Warteliste |
| §6 | `registration_find_by_token()`, `registration_cancel()` in `web/app/registrations.php` | Absage per Link |
| §7.2 | `registration_log_status($id, $status, $mail, $action)` (Aktionen `STATUS_LOG_ACTIONS` in `labels.php`), `mail_outcome($registration, $requested, $sent)`, `status_log_for_event()` in `web/app/registrations.php`; Tabelle `status_log` (Migration 6, `action` Migration 7) | Verlauf je Anmeldung. **Jede Statusänderung und jede Admin-Bearbeitung** mit `registration_log_status()` protokollieren (`registration_cancel()` tut das selbst); Mail-Ergebnis über `mail_outcome()` |
| §7.2–7.4, §7.6 | `registration_list()` (Filter, Suche, `is_duplicate`, `is_preferred_place`, `person_count`), `registration_set_status()`, `registration_exceeds_quota()`, `registration_update()`, `registration_slot_counts()`, `registration_slot_counts_for_event()` (alle Anmeldungen einer Veranstaltung in einer Abfrage), `registration_delete()`, `registration_find()` in `web/app/registrations.php` | Admin-Funktionen für Anmeldungen |
| §7.7, §4 | `stats_for_event($event)` (inkl. `quota_pending`, `groups` je Personengruppe bestätigt/offen, `places`, je Programmpunkt `childcare`), `quota_shares()`, `places_chart()` (Balken „Personen je Ort“, größter Ort = 100 %, < 5 % → „Andere“, mit Personenzahlen) in `web/app/stats.php`; Teil-Template `templates/quota_shares.php` | Auswertung; öffentliche Belegung nur in ganzen Prozent |
| §5.1, §7.1 | `target_groups_parse()`, `target_notice($target, $eventGroups)`, `group_names_join($groups, $eventGroups)` in `events.php`; `event_slots()` liefert `target_groups` (Liste, `[]` = alle); `event_parse_slots($rows, $allowedChildcare, $allowedTarget)`; Spalte `event_slots.target_groups` (Migration 5) | Zielgruppe je Programmpunkt: beim Programmpunkt zählen nur ihre Gruppen (`registration_validate()` liest `$slot['target_groups']`), Hinweis „Zielgruppe: …“; schließt Kinderbetreuung aus; Auswertung zeigt andere Gruppen als „–“ |
| §5.4, §7.1 | `childcare_parse()`, `childcare_names($childcare, $eventGroups)`, `childcare_notice($childcare, $eventGroups)` in `events.php`; `event_slots()` liefert `childcare` (Liste von Kindergruppen) | Kinderbetreuung je Programmpunkt, nur Kindergruppen (3–5); Zählweise beim Programmpunkt vs. Betreuung in `registration_validate()` (`childcare_group_*` je Programmpunkt) |
| §7.8 | `team_key()`, `team_key_generate()`, `team_key_disable()`, `team_key_valid()`, `team_url()` in `web/app/team.php`; Seite `web/team/`; Teil-Template `templates/stats_content.php` | Team-Zugang zur Auswertung per geheimem Link (404 ohne gültigen Schlüssel) |
| §8 | `DB_MIGRATIONS`, `db_upgrade()` in `web/app/db.php` | Schemaänderungen nur als neue Migration anhängen (`PRAGMA user_version`), `schema.sql` zusätzlich auf neuesten Stand bringen |
| – | `format_utc_datetime()`, `parse_date_de()`, `parse_time()` in `helpers.php`; `admin_selected_event()` in `admin.php`; `templates/registration_fields.php` | Datum/Uhrzeit TT.MM.JJJJ / 24 h; gemeinsames Formular Anmeldung + Admin-Bearbeiten |
| §9 | `app_url()` in `web/app/mailer.php`, Config `app_url` | absolute Links in Mails (Absage-Link) |
| §7.10 | `CHANGELOG`, `CHANGELOG_SECTIONS`, `app_version()` in `web/app/changelog.php`; `sbom_components()`, `sbom_runtime()`, `sbom_cyclonedx()`, `SBOM_DEV_TOOLS` in `web/app/sbom.php`; Seite `web/admin/changes.php` | Versionshinweise und SBOM (Versionen/Prüfsummen live aus den Dateien, CycloneDX-Download) |
| §3, §2 | `home_url()`, `home_url_value()` in `web/app/helpers.php`; Config `home_url`; `templates/public_bar.php` (von `layout.php` eingebunden) | feste Leiste „Zurück zur Hauptseite“ auf allen öffentlichen Seiten; nur http(s)-URL oder Pfad ab Wurzel, sonst `/` |
