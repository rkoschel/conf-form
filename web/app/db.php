<?php
declare(strict_types=1);

/**
 * Migrationen bestehender Datenbanken: Version → SQL-Anweisungen.
 * schema.sql enthält immer den neuesten Stand; eine neue DB bekommt direkt
 * die höchste Version. Neue Migrationen nur anhängen, nie ändern.
 */
const DB_MIGRATIONS = [
    // Kinderbetreuung je Programmpunkt (SPEC §5.4, §7.1)
    1 => [
        "ALTER TABLE event_slots ADD COLUMN childcare TEXT NOT NULL DEFAULT ''",
        'ALTER TABLE registration_slots ADD COLUMN childcare_kids_7_12 INTEGER NOT NULL DEFAULT 0',
        'ALTER TABLE registration_slots ADD COLUMN childcare_kids_3_6 INTEGER NOT NULL DEFAULT 0',
        'ALTER TABLE registration_slots ADD COLUMN childcare_kids_0_2 INTEGER NOT NULL DEFAULT 0',
    ],
    // Bevorzugte Orte global statt je Veranstaltung (SPEC §7.5): Orte aller
    // Veranstaltungen zusammenführen (Dubletten ohne Groß-/Kleinschreibung)
    2 => [
        "INSERT OR REPLACE INTO settings (key, value)
         SELECT 'preferred_places', COALESCE(group_concat(name, char(10)), '')
         FROM (SELECT MIN(name) AS name FROM preferred_places GROUP BY name COLLATE NOCASE ORDER BY name COLLATE NOCASE)",
        'DROP TABLE preferred_places',
    ],
    // Personengruppen statt fester Altersgruppen (SPEC §5.1, §7.1): Spalten
    // umbenennen (Reihenfolge bleibt: Erwachsene, Jugendliche, Kinder alt→jung),
    // Auswahl und Namen je Veranstaltung ('' = alle Gruppen, Standardnamen)
    3 => [
        'ALTER TABLE registrations RENAME COLUMN adults TO group_1',
        'ALTER TABLE registrations RENAME COLUMN youth TO group_2',
        'ALTER TABLE registrations RENAME COLUMN kids_7_12 TO group_3',
        'ALTER TABLE registrations RENAME COLUMN kids_3_6 TO group_4',
        'ALTER TABLE registrations RENAME COLUMN kids_0_2 TO group_5',
        'ALTER TABLE registration_slots RENAME COLUMN adults TO group_1',
        'ALTER TABLE registration_slots RENAME COLUMN youth TO group_2',
        'ALTER TABLE registration_slots RENAME COLUMN kids_7_12 TO group_3',
        'ALTER TABLE registration_slots RENAME COLUMN kids_3_6 TO group_4',
        'ALTER TABLE registration_slots RENAME COLUMN kids_0_2 TO group_5',
        'ALTER TABLE registration_slots RENAME COLUMN childcare_kids_7_12 TO childcare_group_3',
        'ALTER TABLE registration_slots RENAME COLUMN childcare_kids_3_6 TO childcare_group_4',
        'ALTER TABLE registration_slots RENAME COLUMN childcare_kids_0_2 TO childcare_group_5',
        "UPDATE event_slots SET childcare = replace(replace(replace(childcare,
            'kids_7_12', 'group_3'), 'kids_3_6', 'group_4'), 'kids_0_2', 'group_5')",
        "ALTER TABLE events ADD COLUMN person_groups TEXT NOT NULL DEFAULT ''",
    ],
    // Individuelle Aufteilung je Veranstaltung abschaltbar; Nachricht an die
    // Veranstalter in der Anmeldung (SPEC §5.1, §7.1)
    4 => [
        'ALTER TABLE events ADD COLUMN allow_split INTEGER NOT NULL DEFAULT 1',
        "ALTER TABLE registrations ADD COLUMN message TEXT NOT NULL DEFAULT ''",
    ],
    // Zielgruppe je Programmpunkt (SPEC §5.1, §7.1): Personengruppen, für die
    // der Programmpunkt gilt; '' = alle
    5 => [
        "ALTER TABLE event_slots ADD COLUMN target_groups TEXT NOT NULL DEFAULT ''",
    ],
    // Verlauf je Anmeldung (SPEC §7.2): Statuswechsel mit Mail-Ergebnis.
    // Bestehende Anmeldungen: Verlauf aus dem Mail-Protokoll, soweit vorhanden
    6 => [
        "CREATE TABLE status_log (
            id              INTEGER PRIMARY KEY,
            registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
            changed_at      TEXT NOT NULL,
            status          TEXT NOT NULL CHECK (status IN ('pending','confirmed','cancelled','rejected')),
            mail            TEXT NOT NULL DEFAULT 'none' CHECK (mail IN ('sent','failed','none'))
        )",
        'CREATE INDEX status_log_registration ON status_log(registration_id)',
        "INSERT INTO status_log (registration_id, changed_at, status, mail)
         SELECT registration_id, sent_at,
                CASE type WHEN 'received_confirmed' THEN 'confirmed' WHEN 'received_waitlist' THEN 'pending' ELSE type END,
                CASE success WHEN 1 THEN 'sent' ELSE 'failed' END
         FROM mail_log ORDER BY id",
    ],
    // Verlauf mit Aktion (SPEC §7.2): Anmeldung, Statuswechsel/Bearbeitung durch
    // den Admin, Absage per Link. Vorhandene Einträge: erster Eintrag kurz nach
    // dem Eingang = Anmeldung, „storniert“ = Absage per Link, sonst Statuswechsel
    7 => [
        "ALTER TABLE status_log ADD COLUMN action TEXT NOT NULL DEFAULT 'admin_status'
            CHECK (action IN ('registration','admin_status','admin_edit','participant_cancel'))",
        "UPDATE status_log SET action = 'registration'
         WHERE id IN (SELECT MIN(l.id) FROM status_log l GROUP BY l.registration_id)
           AND (julianday(changed_at) - julianday((SELECT created_at FROM registrations r WHERE r.id = status_log.registration_id))) * 86400 < 60",
        "UPDATE status_log SET action = 'participant_cancel' WHERE status = 'cancelled'",
    ],
];

/** Mindestversion für ALTER TABLE … RENAME COLUMN (Migration 3) */
const DB_MIN_SQLITE_VERSION = '3.25.0';

/**
 * Die DB-Verbindung der App (aus config('db_path')).
 * Tests können mit db(db_connect($pfad)) eine eigene Verbindung setzen.
 */
function db(?PDO $use = null): PDO
{
    static $pdo = null;
    if ($use !== null) {
        $pdo = $use;
    }
    return $pdo ??= db_connect((string) config('db_path'));
}

/** Öffnet eine SQLite-DB, legt bei Bedarf Verzeichnis und Schema an und migriert. */
function db_connect(string $path): PDO
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('DB-Verzeichnis kann nicht angelegt werden: ' . $dir);
    }

    // Neue DB-Datei nur für den PHP-User lesbar (SQLite übernimmt die Rechte
    // für Journal-Dateien von der DB-Datei)
    if (!is_file($path)) {
        touch($path);
        chmod($path, 0600);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    // Schneller Weg ohne Schreibsperre: Schema vorhanden und aktuell
    if (!db_has_schema($pdo) || db_version($pdo) < max(array_keys(DB_MIGRATIONS))) {
        db_upgrade($pdo);
    }

    return $pdo;
}

function db_has_schema(PDO $pdo): bool
{
    return (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'events'")->fetchColumn();
}

function db_version(PDO $pdo): int
{
    return (int) $pdo->query('PRAGMA user_version')->fetchColumn();
}

/**
 * Legt das Schema an bzw. führt ausstehende Migrationen aus – unter
 * Schreibsperre, damit gleichzeitige Erstaufrufe sich nicht in die Quere
 * kommen (der Stand wird innerhalb der Sperre erneut geprüft).
 */
function db_upgrade(PDO $pdo): void
{
    $latest = max(array_keys(DB_MIGRATIONS));
    $sqlite = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
    if (db_has_schema($pdo) && version_compare($sqlite, DB_MIN_SQLITE_VERSION, '<')) {
        throw new RuntimeException("SQLite $sqlite ist zu alt für die Datenbank-Migration (mindestens " . DB_MIN_SQLITE_VERSION . ').');
    }
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if (!db_has_schema($pdo)) {
            $pdo->exec(file_get_contents(APP_DIR . '/schema.sql'));
            $version = $latest;
        } else {
            $version = db_version($pdo);
            foreach (DB_MIGRATIONS as $target => $statements) {
                if ($target > $version) {
                    foreach ($statements as $sql) {
                        $pdo->exec($sql);
                    }
                    $version = $target;
                }
            }
        }
        $pdo->exec('PRAGMA user_version = ' . $version);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/**
 * Führt $fn in einer Transaktion mit BEGIN IMMEDIATE aus (Schreibsperre ab
 * Beginn, verhindert Überbuchung bei gleichzeitigen Anmeldungen).
 */
function db_transaction(callable $fn): mixed
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $result = $fn($pdo);
        $pdo->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}
