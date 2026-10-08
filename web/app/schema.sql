-- Schema der Konferenz-Anmeldung (siehe SPEC §8).
-- Immer der neueste Stand; db.php legt damit neue Datenbanken an und setzt
-- PRAGMA user_version auf die höchste Migration (siehe DB_MIGRATIONS).

CREATE TABLE IF NOT EXISTS settings (
  key   TEXT PRIMARY KEY,                       -- z. B. inactive_text
  value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS events (
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
  person_groups         TEXT NOT NULL DEFAULT '', -- JSON {"group_1":"Erwachsene",…} gewählte Gruppen + Namen; '' = alle, Standardnamen
  allow_split           INTEGER NOT NULL DEFAULT 1, -- individuelle Aufteilung auf Programmpunkte erlaubt
  active                INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS one_active_event ON events(active) WHERE active = 1;

CREATE TABLE IF NOT EXISTS event_slots (
  id       INTEGER PRIMARY KEY,
  event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  time     TEXT NOT NULL,                       -- HH:MM
  label    TEXT NOT NULL,
  sort     INTEGER NOT NULL DEFAULT 0,
  childcare TEXT NOT NULL DEFAULT ''            -- betreute Gruppen, z. B. 'group_4,group_5'; '' = keine
);

CREATE TABLE IF NOT EXISTS registrations (
  id           INTEGER PRIMARY KEY,
  event_id     INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  created_at   TEXT NOT NULL,                   -- UTC, ISO 8601
  first_name   TEXT NOT NULL,
  last_name    TEXT NOT NULL,
  congregation TEXT NOT NULL,
  email        TEXT,
  phone        TEXT,
  no_email     INTEGER NOT NULL DEFAULT 0,
  group_1         INTEGER NOT NULL DEFAULT 0,
  group_2         INTEGER NOT NULL DEFAULT 0,
  group_3         INTEGER NOT NULL DEFAULT 0,
  group_4         INTEGER NOT NULL DEFAULT 0,
  group_5         INTEGER NOT NULL DEFAULT 0,
  custom_split INTEGER NOT NULL DEFAULT 0,
  message      TEXT NOT NULL DEFAULT '',          -- „Nachricht an uns“ (optional)
  status       TEXT NOT NULL CHECK (status IN
                 ('pending','confirmed','cancelled','rejected')),
  cancel_token TEXT NOT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS registrations_event ON registrations(event_id);

CREATE TABLE IF NOT EXISTS registration_slots (
  registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
  slot_id         INTEGER NOT NULL REFERENCES event_slots(id) ON DELETE CASCADE,
  group_1         INTEGER NOT NULL DEFAULT 0,
  group_2         INTEGER NOT NULL DEFAULT 0,
  group_3         INTEGER NOT NULL DEFAULT 0,
  group_4         INTEGER NOT NULL DEFAULT 0,
  group_5         INTEGER NOT NULL DEFAULT 0,
  childcare_group_3 INTEGER NOT NULL DEFAULT 0, -- Kinder in der Betreuung (SPEC §5.4)
  childcare_group_4 INTEGER NOT NULL DEFAULT 0,
  childcare_group_5 INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (registration_id, slot_id)
);

CREATE TABLE IF NOT EXISTS mail_log (
  id              INTEGER PRIMARY KEY,
  registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
  type            TEXT NOT NULL CHECK (type IN
                    ('received_confirmed','received_waitlist',
                     'confirmed','rejected')),
  sent_at         TEXT NOT NULL,                -- UTC, ISO 8601
  success         INTEGER NOT NULL,
  error           TEXT
);

CREATE TABLE IF NOT EXISTS rate_limit (
  ip_hash    TEXT NOT NULL,                     -- HMAC-SHA256(IP, app_secret)
  created_at TEXT NOT NULL                      -- UTC, ISO 8601
);
CREATE INDEX IF NOT EXISTS rate_limit_lookup ON rate_limit(ip_hash, created_at);

