<?php
declare(strict_types=1);

final class MailerTest extends DbTestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = (string) config('mail_log_file');
        @unlink($this->logFile);
    }

    public function testSendsConfirmationAndLogsSuccess(): void
    {
        [$registration, $event] = $this->fixture();

        $this->assertTrue(mail_registration('received_confirmed', $registration, $event));

        $log = file_get_contents($this->logFile);
        $this->assertStringContainsString('To: anna@example.org', $log);
        $this->assertStringContainsString('Reply-To: orga@example.org', $log);
        $this->assertStringContainsString('Subject: Anmeldung bestätigt: Testkonferenz', $log);
        $this->assertStringContainsString('Hallo Anna,', $log);
        $this->assertStringContainsString('Datum: Samstag, 01.05.2027', $log);
        $this->assertStringContainsString('Erwachsene: 2', $log);
        $this->assertStringContainsString('Kindergruppe 1: 1', $log, 'Standardname der Gruppe 5');
        $this->assertStringNotContainsString('Jugendliche', $log, 'Altersgruppen mit 0 entfallen');
        $this->assertStringContainsString(
            'https://example.org/konferenz/cancel/?t=' . $registration['cancel_token'],
            $log
        );

        $row = db()->query('SELECT * FROM mail_log')->fetch();
        $this->assertSame($registration['id'], $row['registration_id']);
        $this->assertSame('received_confirmed', $row['type']);
        $this->assertSame(1, $row['success']);
        $this->assertNull($row['error']);
    }

    public function testAllTypesRenderWithSubject(): void
    {
        [$registration, $event] = $this->fixture();

        foreach (array_keys(MAIL_TYPES) as $type) {
            $mail = mail_render($type, mail_vars($registration, $event));
            $this->assertNotSame('', $mail['subject'], $type);
            $this->assertStringContainsString('Testkonferenz', $mail['body'], $type);
            $this->assertStringNotContainsString('<?', $mail['body'], $type);
        }
    }

    public function testUsesOwnTextsWithPlaceholders(): void
    {
        [$registration, $event] = $this->fixture([], ['organizer_name' => '']);
        setting_text_set('mail_intro_received_confirmed', "Moin {first_name} {last_name}!\nWir sehen uns am {date} in {location} zu „{title}“.");
        setting_text_set('mail_cancel_hint', 'Absagen hier:');
        setting_text_set('mail_organizer_fallback', 'Team Hamm');

        $body = mail_render('received_confirmed', mail_vars($registration, $event))['body'];

        $this->assertStringStartsWith("Moin Anna Muster!\nWir sehen uns am Samstag, 01.05.2027 in Hamm zu „Testkonferenz“.", $body);
        $this->assertStringContainsString("Absagen hier:\nhttps://example.org/konferenz/cancel/", $body);
        $this->assertStringEndsWith("Viele Grüße\nTeam Hamm\n", $body);
    }

    public function testRejectionHasNoCancelLink(): void
    {
        [$registration, $event] = $this->fixture();

        $mail = mail_render('rejected', mail_vars($registration, $event));

        $this->assertStringNotContainsString('cancel', $mail['body']);
    }

    public function testSkipsRegistrationWithoutEmail(): void
    {
        [$registration, $event] = $this->fixture(['email' => null, 'no_email' => 1, 'phone' => '0123']);

        $this->assertFalse(mail_registration('received_confirmed', $registration, $event));
        $this->assertSame(0, $this->rowCount('mail_log'));
        $this->assertFileDoesNotExist($this->logFile);
    }

    public function testLogsFailure(): void
    {
        [$registration, $event] = $this->fixture(['email' => 'kaputt']);

        $this->assertFalse(mail_registration('rejected', $registration, $event));

        $row = db()->query('SELECT * FROM mail_log')->fetch();
        $this->assertSame(0, $row['success']);
        $this->assertSame('Ungültige E-Mail-Adresse.', $row['error']);
    }

    public function testOmitsInvalidReplyTo(): void
    {
        [$registration, $event] = $this->fixture([], ['organizer_email' => '']);

        mail_registration('confirmed', $registration, $event);

        $this->assertStringNotContainsString('Reply-To:', file_get_contents($this->logFile));
    }

    public function testRejectsUnknownType(): void
    {
        [$registration, $event] = $this->fixture();

        $this->expectException(InvalidArgumentException::class);
        mail_registration('reminder', $registration, $event);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function fixture(array $registrationFields = [], array $eventFields = []): array
    {
        $eventId = $this->createEvent($eventFields + [
            'organizer_name' => 'Orga-Team',
            'organizer_email' => 'orga@example.org',
        ]);
        $fields = $registrationFields + [
            'event_id' => $eventId,
            'created_at' => now_utc(),
            'first_name' => 'Anna',
            'last_name' => 'Muster',
            'congregation' => 'Hamm',
            'email' => 'anna@example.org',
            'group_1' => 2,
            'group_5' => 1,
            'status' => 'confirmed',
            'cancel_token' => bin2hex(random_bytes(32)),
        ];
        $columns = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(fn ($k) => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO registrations ($columns) VALUES ($params)")->execute($fields);
        $id = (int) db()->lastInsertId();

        return [
            db()->query("SELECT * FROM registrations WHERE id = $id")->fetch(),
            db()->query("SELECT * FROM events WHERE id = $eventId")->fetch(),
        ];
    }
}
