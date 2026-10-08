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
        $this->assertContains('childcare_group_5', $this->columns($pdo, 'registration_slots'));
    }

    public function testExistingDatabaseIsMigratedAndKeepsData(): void
    {
        $old = new PDO('sqlite:' . $this->path);
        $old->exec(file_get_contents(ROOT_DIR . '/tests/fixtures/schema_v0.sql'));
        $old->exec("INSERT INTO events (id, title, date, location, registration_deadline, max_participants)
                    VALUES (1, 'Alt', '2027-05-01', 'Hamm', '2027-04-15T23:59', 10)");
        $old->exec("INSERT INTO event_slots (id, event_id, time, label) VALUES (1, 1, '10:00', 'Vortrag')");
        // altes Schema: feste Altersgruppen-Spalten
        $old->exec("INSERT INTO registrations (id, event_id, created_at, first_name, last_name, congregation,
                        adults, youth, kids_7_12, kids_3_6, kids_0_2, status, cancel_token)
                    VALUES (1, 1, '2027-01-01T00:00:00Z', 'Max', 'Muster', 'Hamm', 2, 1, 3, 4, 5, 'pending', 'abc')");
        $old->exec('INSERT INTO registration_slots (registration_id, slot_id, adults, kids_0_2) VALUES (1, 1, 2, 5)');
        $this->assertSame(0, db_version($old));
        $old = null;

        $pdo = db_connect($this->path);

        $this->assertSame(max(array_keys(DB_MIGRATIONS)), db_version($pdo));
        $slot = $pdo->query('SELECT * FROM event_slots')->fetch();
        $this->assertSame(['Vortrag', ''], [$slot['label'], $slot['childcare']]);
        $attendance = $pdo->query('SELECT * FROM registration_slots')->fetch();
        $this->assertSame([2, 5, 0], [(int) $attendance['group_1'], (int) $attendance['group_5'], (int) $attendance['childcare_group_4']]);
        $registration = $pdo->query('SELECT * FROM registrations')->fetch();
        $this->assertSame(
            [2, 1, 3, 4, 5],
            array_map(fn ($n) => (int) $registration["group_$n"], [1, 2, 3, 4, 5]),
            'Erwachsene→1, Jugend→2, 7–12→3, 3–6→4, 0–2→5'
        );
        $this->assertSame('', $pdo->query('SELECT person_groups FROM events')->fetchColumn(), 'bestehende Veranstaltung: alle Gruppen');
    }

    public function testChildcareIsMigratedToGroupKeys(): void
    {
        // Stand nach Migration 2 (mit Kinderbetreuung, alte Spaltennamen)
        $old = new PDO('sqlite:' . $this->path);
        $old->exec(file_get_contents(ROOT_DIR . '/tests/fixtures/schema_v0.sql'));
        foreach ([1, 2] as $version) {
            foreach (DB_MIGRATIONS[$version] as $sql) {
                $old->exec($sql);
            }
        }
        $old->exec('PRAGMA user_version = 2');
        $old->exec("INSERT INTO events (id, title, date, location, registration_deadline, max_participants)
                    VALUES (1, 'Alt', '2027-05-01', 'Hamm', '2027-04-15T23:59', 10)");
        $old->exec("INSERT INTO event_slots (id, event_id, time, label, childcare) VALUES (1, 1, '10:00', 'Vortrag', 'kids_3_6,kids_0_2')");
        $old->exec("INSERT INTO registrations (id, event_id, created_at, first_name, last_name, congregation, kids_3_6, status, cancel_token)
                    VALUES (1, 1, '2027-01-01T00:00:00Z', 'Max', 'Muster', 'Hamm', 2, 'pending', 'abc')");
        $old->exec('INSERT INTO registration_slots (registration_id, slot_id, childcare_kids_3_6) VALUES (1, 1, 2)');
        $old = null;

        $pdo = db_connect($this->path);

        $this->assertSame('group_4,group_5', $pdo->query('SELECT childcare FROM event_slots')->fetchColumn());
        $this->assertSame(2, (int) $pdo->query('SELECT childcare_group_4 FROM registration_slots')->fetchColumn());
    }

    public function testPreferredPlacesOfAllEventsBecomeGlobal(): void
    {
        $old = new PDO('sqlite:' . $this->path);
        $old->exec(file_get_contents(ROOT_DIR . '/tests/fixtures/schema_v0.sql'));
        foreach ([1 => ['Unna', 'Hamm'], 2 => ['hamm', 'Soest']] as $eventId => $places) {
            $old->exec("INSERT INTO events (id, title, date, location, registration_deadline, max_participants)
                        VALUES ($eventId, 'Alt', '2027-05-01', 'Hamm', '2027-04-15T23:59', 10)");
            foreach ($places as $place) {
                $old->prepare('INSERT INTO preferred_places (event_id, name) VALUES (?, ?)')->execute([$eventId, $place]);
            }
        }
        $old = null;

        db(db_connect($this->path));

        $this->assertSame(['Hamm', 'Soest', 'Unna'], preferred_places(), 'vereinigt, ohne Dubletten (Groß-/Kleinschreibung)');
        $tables = db()->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotContains('preferred_places', $tables);
    }

    public function testMigrationWithoutPlacesStoresEmptyList(): void
    {
        $old = new PDO('sqlite:' . $this->path);
        $old->exec(file_get_contents(ROOT_DIR . '/tests/fixtures/schema_v0.sql'));
        $old = null;

        db(db_connect($this->path));

        $this->assertSame([], preferred_places());
    }

    public function testMigrationRunsOnlyOnce(): void
    {
        db_connect($this->path);
        $pdo = db_connect($this->path);

        $this->assertSame(max(array_keys(DB_MIGRATIONS)), db_version($pdo));
        $this->assertSame(1, count(array_keys($this->columns($pdo, 'event_slots'), 'childcare')));
    }
}
