<?php
declare(strict_types=1);

final class SchemaTest extends DbTestCase
{
    public function testCreatesAllTables(): void
    {
        $tables = db()->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([
            'event_slots', 'events', 'mail_log', 'rate_limit',
            'registration_slots', 'registrations', 'settings',
        ], $tables);
    }

    public function testEnablesForeignKeys(): void
    {
        $this->assertSame(1, (int) db()->query('PRAGMA foreign_keys')->fetchColumn());
    }

    public function testInactiveTextHasDefault(): void
    {
        $this->assertSame('Derzeit ist keine Anmeldung möglich.', setting_text('inactive_text'));
    }

    public function testOnlyOneEventCanBeActive(): void
    {
        $this->createEvent(['active' => 1]);
        $this->createEvent(['active' => 0]);

        $this->expectException(PDOException::class);
        $this->createEvent(['active' => 1]);
    }

    public function testRejectsUnknownStatus(): void
    {
        $eventId = $this->createEvent();

        $this->expectException(PDOException::class);
        $this->insertRegistration($eventId, 'bestaetigt');
    }

    public function testDeletingEventDeletesAllRelatedData(): void
    {
        $eventId = $this->createEvent();
        $otherEventId = $this->createEvent();

        db()->exec("INSERT INTO event_slots (event_id, time, label) VALUES ($eventId, '10:00', 'Vortrag')");
        $slotId = (int) db()->lastInsertId();
        $registrationId = $this->insertRegistration($eventId, 'confirmed');
        db()->exec("INSERT INTO registration_slots (registration_id, slot_id, adults) VALUES ($registrationId, $slotId, 2)");
        db()->exec("INSERT INTO mail_log (registration_id, type, sent_at, success)
                    VALUES ($registrationId, 'received_confirmed', '2027-01-01T00:00:00Z', 1)");
        $this->insertRegistration($otherEventId, 'pending');

        db()->exec("DELETE FROM events WHERE id = $eventId");

        $this->assertSame(1, $this->rowCount('events'));
        $this->assertSame(0, $this->rowCount('event_slots'));
        $this->assertSame(1, $this->rowCount('registrations'), 'Anmeldung der anderen Veranstaltung bleibt');
        $this->assertSame(0, $this->rowCount('registration_slots'));
        $this->assertSame(0, $this->rowCount('mail_log'));
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            db_transaction(function (PDO $pdo): void {
                $this->createEvent();
                throw new RuntimeException('Abbruch');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $this->rowCount('events'));
    }

    public function testTransactionReturnsResult(): void
    {
        $id = db_transaction(fn () => $this->createEvent());

        $this->assertSame(1, $id);
        $this->assertSame(1, $this->rowCount('events'));
    }

    private function insertRegistration(int $eventId, string $status): int
    {
        db()->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, email, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, now_utc(), 'Max', 'Muster', 'Hamm', 'max@example.org', $status, bin2hex(random_bytes(32))]);
        return (int) db()->lastInsertId();
    }
}
