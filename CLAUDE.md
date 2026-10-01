# Hinweise für Agents

Fachliche Grundlage ist `SPEC.md` (nicht im Repo, per `.gitignore`
ausgeschlossen). Tests: `./test.sh` (siehe SPEC §11).

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
   Dateien) und welche SPEC-Abschnitte er abdeckt.
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
| §7.1 | `event_find($id)` (inkl. `slots`, `places`), `event_slots()`, `event_places()`, `event_list()` (mit `registration_count`), `event_has_registrations()`, `event_set_active()`, `event_delete()`, `event_validate()`, `event_save()` in `web/app/events.php` | Veranstaltungen lesen, prüfen, speichern, aktivieren, löschen |
| §4, §5 | `event_active()`, `event_registration_open($event, ?DateTimeImmutable $now)` in `web/app/events.php` | aktive Veranstaltung (mit `slots`, `places`); Frist in der Zeitzone der Veranstaltung |
| §5.1, §7.1 | `normalize_line()`, `normalize_text()` in `web/app/helpers.php` | Normalisierung einzeiliger (Ortsname) bzw. mehrzeiliger Texte |
| – | `format_date()`, `format_local_datetime()` in `web/app/helpers.php` | `Y-m-d` → `d.m.Y`; `Y-m-d\TH:i` → „d.m.Y, H:i Uhr“ |
| §7.5 | `setting_get()`, `setting_set()` in `web/app/settings.php` | globale Einstellungen (`inactive_text`) |
| – | `post_string()`, `post_rows()`, `invalid_class()`, `field_error()`, `input_field()`, `flash()`/`flash_take()` in `web/app/forms.php` | Formulare, Validierungsfehler, Meldungen nach Redirect |
| §7 | `admin_init()`, `admin_render()` in `web/app/admin.php` | Admin-Seiten (`no-store`, Session, Layout) |
| §11 | `DbTestCase::createEvent()`, `rowCount()`; `HttpTestCase` mit Cookies/Session, `csrfToken($path)`, `clearCookies()`, `serverDb()` (mit `db($this->serverDb())` als App-DB setzen) | Test-Hilfen |
| §5.2 | `spam_fields()`, `spam_check()`, `spam_message()` in `web/app/spam.php` | Honeypot, signierter Zeitstempel, Rate-Limit; bei `SPAM_HONEYPOT` Antwort wie bei Erfolg |
| §5.3, §7.3, §9 | `mail_registration($type, $registration, $event)` in `web/app/mailer.php` | Mail senden und in `mail_log` protokollieren; ohne E-Mail kein Versand |
| §8 | `AGE_GROUPS`, `STATUS_LABELS`, `MAIL_TYPES` in `web/app/labels.php` | zentrale deutsche Bezeichnungen |
| §5.3 | `QUOTA_AGE_GROUPS` in `web/app/stats.php` | Altersgruppen, die zum Kontingent zählen (ohne 0–2) |
| §5.1, §5.3 | `registration_validate()`, `registration_create()`, `registration_group_size()`, `registration_person_count()`, `registration_occupied()`, `registration_decide_status()` in `web/app/registrations.php` | Formular prüfen, Anmeldung mit Status-Entscheidung in `BEGIN IMMEDIATE` speichern |
| §6 | `registration_find_by_token()`, `registration_cancel()` in `web/app/registrations.php` | Absage per Link |
| §7.2–7.4, §7.6 | `registration_list()` (Filter, Suche, `is_duplicate`, `is_preferred_place`, `person_count`), `registration_set_status()`, `registration_exceeds_quota()`, `registration_update()`, `registration_slot_counts()`, `registration_delete()`, `registration_find()` in `web/app/registrations.php` | Admin-Funktionen für Anmeldungen |
| §7.7 | `stats_for_event($event)` in `web/app/stats.php` | Auswertung |
| §9 | `app_url()` in `web/app/mailer.php`, Config `app_url` | absolute Links in Mails (Absage-Link) |
