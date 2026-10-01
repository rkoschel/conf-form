<?php
declare(strict_types=1);

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

/** Öffnet eine SQLite-DB und legt bei Bedarf Verzeichnis und Schema an. */
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

    $hasSchema = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'events'")
        ->fetchColumn();
    if (!$hasSchema) {
        // schema.sql ist idempotent (IF NOT EXISTS), parallele Erstaufrufe sind harmlos
        $pdo->exec(file_get_contents(APP_DIR . '/schema.sql'));
    }

    return $pdo;
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
