<?php
declare(strict_types=1);

final class RegistrationAdminTest extends DbTestCase
{
    public function testMailStatusReturnsLatestEntryPerRegistration(): void
    {
        $eventId = $this->createEvent();
        $first = $this->insertRegistration($eventId);
        $second = $this->insertRegistration($eventId);
        $other = $this->insertRegistration($this->createEvent());

        $this->log($first, 'received_waitlist', '2027-01-01T10:00:00Z', 1);
        $this->log($first, 'confirmed', '2027-01-02T10:00:00Z', 0, 'SMTP down');
        $this->log($other, 'received_confirmed', '2027-01-03T10:00:00Z', 1);

        $status = mail_status_for_event($eventId);

        $this->assertSame([$first], array_keys($status), 'nur Anmeldungen mit Mail, nur diese Veranstaltung');
        $this->assertSame('confirmed', $status[$first]['type']);
        $this->assertSame(0, (int) $status[$first]['success']);
        $this->assertSame('SMTP down', $status[$first]['error']);
        $this->assertArrayNotHasKey($second, $status);
    }

    public function testFormValuesMatchRegistrationFormFormat(): void
    {
        $registration = [
            'first_name' => 'Max', 'last_name' => 'Muster', 'congregation' => 'Hamm',
            'email' => null, 'phone' => '0123', 'no_email' => 1, 'custom_split' => 0, 'status' => 'pending',
            'adults' => 2, 'youth' => 1, 'kids_7_12' => 0, 'kids_3_6' => 0, 'kids_0_2' => 1,
        ];
        $slotCounts = [
            7 => ['adults' => 2, 'youth' => 1, 'kids_7_12' => 0, 'kids_3_6' => 0, 'kids_0_2' => 1],
            8 => ['adults' => 0, 'youth' => 0, 'kids_7_12' => 0, 'kids_3_6' => 0, 'kids_0_2' => 0],
        ];

        $form = registration_form_values($registration, $slotCounts);

        $this->assertSame('', $form['email']);
        $this->assertTrue($form['no_email']);
        $this->assertSame('2', $form['adults']);
        $this->assertSame([7 => '1'], $form['attend'], 'nur Programmpunkte mit Personen angekreuzt');
        $this->assertSame('1', $form['split'][7]['youth']);
        $this->assertSame('0', $form['split'][8]['adults']);
    }

    private function insertRegistration(int $eventId): int
    {
        db()->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, now_utc(), 'Max', 'Muster', 'Hamm', 'pending', bin2hex(random_bytes(32))]);
        return (int) db()->lastInsertId();
    }

    private function log(int $registrationId, string $type, string $sentAt, int $success, ?string $error = null): void
    {
        db()->prepare('INSERT INTO mail_log (registration_id, type, sent_at, success, error) VALUES (?, ?, ?, ?, ?)')
            ->execute([$registrationId, $type, $sentAt, $success, $error]);
    }
}
