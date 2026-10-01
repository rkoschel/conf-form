<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Jeder Test bekommt eine frische SQLite-DB mit dem echten Schema.
 */
abstract class DbTestCase extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = getenv('CONF_FORM_TEST_DIR') . '/' . bin2hex(random_bytes(8)) . '.sqlite';
        db(db_connect($this->dbPath));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dbPath . '*') ?: [] as $file) {
            unlink($file);
        }
    }

    /** Legt eine Veranstaltung an und gibt ihre ID zurück. */
    protected function createEvent(array $fields = []): int
    {
        $fields += [
            'title' => 'Testkonferenz',
            'date' => '2027-05-01',
            'location' => 'Hamm',
            'registration_deadline' => '2027-04-15T23:59',
            'max_participants' => 100,
            'active' => 0,
        ];
        $columns = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(fn ($k) => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO events ($columns) VALUES ($params)")->execute($fields);
        return (int) db()->lastInsertId();
    }

    protected function rowCount(string $table): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }
}
