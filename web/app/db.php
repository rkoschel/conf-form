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
