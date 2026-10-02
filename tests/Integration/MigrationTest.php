<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = getenv('CONF_FORM_TEST_DIR') . '/migration-' . bin2hex(random_bytes(4)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '*') ?: [] as $file) {
            unlink($file);
        }
    }

    /** @return list<string> */
    private function columns(PDO $pdo, string $table): array
    {
        return array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    }

    public function testNewDatabaseGetsLatestSchemaAndVersion(): void
    {
        $pdo = db_connect($this->path);

        $this->assertSame(max(array_keys(DB_MIGRATIONS)), db_version($pdo));
        $this->assertContains('childcare', $this->columns($pdo, 'event_slots'));
        $this->assertContains('childcare_kids_0_2', $this->columns($pdo, 'registration_slots'));
    }

    public function testExistingDatabaseIsMigratedAndKeepsData(): void
    {
        $old = new PDO('sqlite:' . $this->path);
        $old->exec(file_get_contents(ROOT_DIR . '/tests/fixtures/schema_v0.sql'));
        $old->exec("INSERT INTO events (id, title, date, location, registration_deadline, max_participants)
                    VALUES (1, 'Alt', '2027-05-01', 'Hamm', '2027-04-15T23:59', 10)");
        $old->exec("INSERT INTO event_slots (id, event_id, time, label) VALUES (1, 1, '10:00', 'Vortrag')");
        $old->exec("INSERT INTO registrations (id, event_id, created_at, first_name, last_name, congregation, adults, status, cancel_token)
                    VALUES (1, 1, '2027-01-01T00:00:00Z', 'Max', 'Muster', 'Hamm', 2, 'pending', 'abc')");
        $old->exec('INSERT INTO registration_slots (registration_id, slot_id, adults) VALUES (1, 1, 2)');
        $this->assertSame(0, db_version($old));
        $old = null;

        $pdo = db_connect($this->path);

        $this->assertSame(1, db_version($pdo));
        $slot = $pdo->query('SELECT * FROM event_slots')->fetch();
        $this->assertSame(['Vortrag', ''], [$slot['label'], $slot['childcare']]);
        $attendance = $pdo->query('SELECT * FROM registration_slots')->fetch();
        $this->assertSame([2, 0], [(int) $attendance['adults'], (int) $attendance['childcare_kids_3_6']]);
    }

    public function testMigrationRunsOnlyOnce(): void
    {
        db_connect($this->path);
        $pdo = db_connect($this->path);

        $this->assertSame(1, db_version($pdo));
        $this->assertSame(1, count(array_keys($this->columns($pdo, 'event_slots'), 'childcare')));
    }
}
