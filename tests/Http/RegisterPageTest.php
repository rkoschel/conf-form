<?php
declare(strict_types=1);

/** Anmeldeformular (SPEC §5): Hauptabläufe über echte Requests */
final class RegisterPageTest extends HttpTestCase
{
    private array $event;

    protected function setUp(): void
    {
        db($this->serverDb());
        $this->clearCookies();
        db()->exec('DELETE FROM events');
        db()->exec('DELETE FROM rate_limit');
        $this->event = $this->activeEvent();
    }

    private function activeEvent(string $deadline = '2099-04-15T23:59'): array
    {
        db()->prepare(
            "INSERT INTO events (title, date, location, registration_deadline, max_participants, organizer_email, active)
             VALUES ('Konferenz 2099', '2099-05-02', 'Hamm', ?, 10, 'orga@example.org', 1)"
        )->execute([$deadline]);
        $id = (int) db()->lastInsertId();
        db()->exec("INSERT INTO event_slots (event_id, time, label, sort) VALUES ($id, '10:00', 'Vortrag', 0)");
        db()->exec("INSERT INTO event_slots (event_id, time, label, sort) VALUES ($id, '14:00', 'Jugendstunde', 1)");
        db()->exec("INSERT INTO preferred_places (event_id, name) VALUES ($id, 'Hamm')");
        return event_find($id);
    }

    /** Gültiger Absendeversuch; $fields überschreibt bzw. ergänzt */
    private function submit(array $fields = []): array
    {
        $slotIds = array_column($this->event['slots'], 'id');
        return $this->post('/register/', $fields + [
            'csrf' => $this->csrfToken('/register/'),
            SPAM_TIMESTAMP_FIELD => spam_timestamp(time() - 10),
            SPAM_HONEYPOT_FIELD => '',
            'first_name' => 'Anna',
            'last_name' => 'Muster',
            'congregation' => 'hamm',
            'email' => 'anna@example.org',
            'adults' => '2',
            'kids_0_2' => '1',
            'attend' => [$slotIds[0] => '1'],
        ]);
    }

    private function registrations(): array
    {
        return db()->query('SELECT * FROM registrations')->fetchAll();
    }

    public function testShowsForm(): void
    {
        $body = $this->get('/register/')['body'];

        $this->assertStringContainsString('Konferenz 2099', $body);
        $this->assertStringContainsString('<option value="Hamm">', $body);
        $this->assertStringContainsString('Jugendstunde', $body);
        $this->assertStringContainsString('name="' . SPAM_HONEYPOT_FIELD . '"', $body);
        $this->assertStringContainsString('name="' . SPAM_TIMESTAMP_FIELD . '"', $body);
        $this->assertStringContainsString('href="https://example.org/datenschutz/"', $body);
        $this->assertStringContainsString('Teilnahme anfragen', $body);
    }

    public function testPreferredPlaceIsConfirmedWithMail(): void
    {
        $response = $this->submit();

        $this->assertSame(303, $response['status'], strip_tags($response['body']));
        $this->assertStringContainsString('Anmeldung bestätigt', $this->get('/register/')['body']);
        $this->assertStringNotContainsString('Anmeldung bestätigt', $this->get('/register/')['body'], 'Ergebnis nur einmal');

        [$registration] = $this->registrations();
        $this->assertSame('confirmed', $registration['status']);
        $this->assertSame('hamm', $registration['congregation']);
        $slots = registration_slot_counts($registration['id']);
        $this->assertSame(2, $slots[$this->event['slots'][0]['id']]['adults']);
        $this->assertSame(0, $slots[$this->event['slots'][1]['id']]['adults']);

        $mail = db()->query('SELECT type, success FROM mail_log')->fetch();
        $this->assertSame(['type' => 'received_confirmed', 'success' => 1], $mail);
    }

    public function testOtherPlaceGoesToWaitlist(): void
    {
        $this->submit(['congregation' => 'Dortmund']);

        $this->assertStringContainsString('Du stehst auf der Warteliste', $this->get('/register/')['body']);
        $this->assertSame('pending', $this->registrations()[0]['status']);
        $this->assertSame('received_waitlist', db()->query('SELECT type FROM mail_log')->fetchColumn());
    }

    public function testWithoutEmailNoMail(): void
    {
        $response = $this->submit(['email' => '', 'no_email' => '1', 'phone' => '02381 123']);

        $this->assertSame(303, $response['status'], strip_tags($response['body']));
        $this->assertSame('02381 123', $this->registrations()[0]['phone']);
        $this->assertSame(0, (int) db()->query('SELECT COUNT(*) FROM mail_log')->fetchColumn());
    }

    public function testValidationErrorKeepsInput(): void
    {
        $response = $this->submit(['last_name' => '', 'first_name' => 'Anna <b>']);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Nachname ist erforderlich.', $response['body']);
        $this->assertStringContainsString('value="Anna &lt;b&gt;"', $response['body']);
        $this->assertSame([], $this->registrations());
    }

    public function testMissingCsrfIsRejected(): void
    {
        $response = $this->submit(['csrf' => '']);

        $this->assertSame(400, $response['status']);
        $this->assertSame([], $this->registrations());
    }

    public function testHoneypotLooksLikeSuccessButStoresNothing(): void
    {
        $response = $this->submit([SPAM_HONEYPOT_FIELD => 'http://spam.example']);

        $this->assertSame(303, $response['status']);
        $this->assertStringContainsString('Anmeldung bestätigt', $this->get('/register/')['body']);
        $this->assertSame([], $this->registrations());
        $this->assertSame(0, (int) db()->query('SELECT COUNT(*) FROM mail_log')->fetchColumn());
    }

    public function testTooFastSubmissionIsRejected(): void
    {
        $response = $this->submit([SPAM_TIMESTAMP_FIELD => spam_timestamp(time())]);

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Bitte Formular neu laden und erneut absenden.', $response['body']);
        $this->assertStringContainsString('value="Anna"', $response['body'], 'Eingaben bleiben erhalten');
        $this->assertSame([], $this->registrations());
    }

    public function testRateLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register/', []);
        }

        $response = $this->submit();

        $this->assertSame(429, $response['status']);
        $this->assertSame([], $this->registrations());
    }

    public function testClosedAfterDeadline(): void
    {
        db()->exec('DELETE FROM events');
        $this->event = $this->activeEvent('2020-04-15T23:59');

        $this->assertStringContainsString('Der Anmeldezeitraum ist abgelaufen.', $this->get('/register/')['body']);
        $this->post('/register/', ['first_name' => 'Anna', 'last_name' => 'Muster', 'adults' => '1']);
        $this->assertSame([], $this->registrations());
    }

    public function testClosedWithoutActiveEvent(): void
    {
        db()->exec('UPDATE events SET active = 0');

        $this->assertStringContainsString('Derzeit ist keine Anmeldung möglich.', $this->get('/register/')['body']);
    }
}
