<?php
declare(strict_types=1);

/** Absage per Link (SPEC §6) */
final class CancelPageTest extends HttpTestCase
{
    private function registration(string $status = 'confirmed'): array
    {
        db($this->serverDb());
        $eventId = (int) db()->query("SELECT id FROM events WHERE title = 'Absagetest'")->fetchColumn();
        if ($eventId === 0) {
            db()->exec("INSERT INTO events (title, date, location, registration_deadline, max_participants)
                        VALUES ('Absagetest', '2099-05-02', 'Hamm', '2099-04-15T23:59', 10)");
            $eventId = (int) db()->lastInsertId();
        }
        $registration = registration_create(event_find($eventId), [
            'first_name' => 'Anna', 'last_name' => '<Muster>', 'congregation' => 'Hamm',
            'email' => 'anna@example.org', 'phone' => null, 'no_email' => false,
            'group_1' => 2, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 0,
            'custom_split' => false, 'slots' => [],
        ]);
        registration_set_status($registration['id'], $status);
        return registration_find($registration['id']);
    }

    public function testGetShowsRegistrationWithoutCancelling(): void
    {
        $registration = $this->registration();

        $response = $this->get('/cancel/?t=' . $registration['cancel_token']);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Anna &lt;Muster&gt;', $response['body']);
        $this->assertStringContainsString('Absagetest', $response['body']);
        $this->assertStringContainsString('Teilnahme absagen</button>', $response['body']);
        $this->assertSame('confirmed', registration_find($registration['id'])['status']);
    }

    public function testPostCancelsAndRedirects(): void
    {
        $registration = $this->registration();
        $path = '/cancel/?t=' . $registration['cancel_token'];

        $response = $this->post('/cancel/', ['csrf' => $this->csrfToken($path), 't' => $registration['cancel_token']]);

        $this->assertSame(303, $response['status']);
        $this->assertSame('/cancel/?t=' . $registration['cancel_token'] . '&done=1', $response['headers']['location']);
        $this->assertSame('cancelled', registration_find($registration['id'])['status']);
        $this->assertStringContainsString('Deine Teilnahme ist abgesagt', $this->get($response['headers']['location'])['body']);
    }

    public function testPostWithoutCsrfDoesNotCancel(): void
    {
        $registration = $this->registration();

        $response = $this->post('/cancel/', ['t' => $registration['cancel_token']]);

        $this->assertSame(400, $response['status']);
        $this->assertSame('confirmed', registration_find($registration['id'])['status']);
    }

    public function testCancelledOrRejectedShowsNoticeWithoutButton(): void
    {
        foreach (['cancelled' => 'bereits storniert', 'rejected' => 'bereits abgelehnt'] as $status => $text) {
            $registration = $this->registration($status);

            $body = $this->get('/cancel/?t=' . $registration['cancel_token'])['body'];

            $this->assertStringContainsString($text, $body);
            $this->assertStringNotContainsString('Teilnahme absagen</button>', $body);
        }
    }

    public function testInvalidTokenShowsNeutralMessage(): void
    {
        foreach (['/cancel/', '/cancel/?t=abc', '/cancel/?t=' . str_repeat('0', 64)] as $path) {
            $body = $this->get($path)['body'];

            $this->assertStringContainsString('Dieser Link ist ungültig', $body, $path);
            $this->assertStringNotContainsString('Teilnahme absagen</button>', $body, $path);
        }
    }
}
