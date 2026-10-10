# Datenmodell

Teil der Spezifikation, Übersicht: [overview.md](overview.md).

## 8. Datenmodell (SQLite)

Alle Bezeichner und gespeicherten Werte (Status, Mail-Typen) auf Englisch;
die Übersetzung in deutsche UI-Texte erfolgt zentral an einer Stelle im Code.

```sql
CREATE TABLE settings (
  key   TEXT PRIMARY KEY,                       -- z. B. inactive_text, preferred_places, admin_notify
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
  childcare TEXT NOT NULL DEFAULT '',           -- betreute Kindergruppen, z. B. 'group_4,group_5'; '' = keine
  target_groups TEXT NOT NULL DEFAULT ''        -- Zielgruppe, z. B. 'group_1,group_2'; '' = alle
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
  childcare_group_3 INTEGER NOT NULL DEFAULT 0, -- Kinder in der Betreuung (siehe §5.4)
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
  Version 4: events.allow_split, registrations.message;
  Version 5: event_slots.target_groups).
- Existiert die DB-Datei nicht, wird sie beim ersten Aufruf aus
  `schema.sql` angelegt.
- Technische Zeitstempel (`created_at`, `sent_at`) in UTC; Anzeige in der
  Zeitzone der Veranstaltung.
- IP-Adressen werden nur als HMAC gespeichert und nach Ablauf des
  Rate-Limit-Zeitfensters gelöscht.
