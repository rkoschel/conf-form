<?php
declare(strict_types=1);

final class InfoPageTest extends HttpTestCase
{
    /** Legt über das Admin-Formular eine aktive Veranstaltung an */
    private function activeEvent(array $overrides = []): void
    {
        $response = $this->post('/admin/event.php', $overrides + [
            'csrf' => $this->csrfToken('/admin/event.php'),
            'title' => 'Konferenz <2099>',
            'date' => '02.05.2099',
            'location' => 'Hamm, Gemeindehaus',
            'description' => "Herzliche Einladung!\nMit Mittagessen.",
            'registration_deadline_date' => '15.04.2099',
            'registration_deadline_time' => '23:59',
            'timezone' => 'Europe/Berlin',
            'max_participants' => '173',
            'active' => '1',
            'slots' => [
                ['time' => '14:00', 'label' => 'Jugendstunde'],
                ['time' => '10:00', 'label' => 'Begrüßung'],
            ],
            'places' => 'Hamm',
        ]);
        $this->assertSame(303, $response['status'], strip_tags($response['body']));
    }

    public function testShowsEventDetailsAndRegisterButtonBeforeDeadline(): void
    {
        $this->activeEvent();

        $body = $this->get('/')['body'];

        $this->assertStringContainsString('<title>Konferenz &lt;2099&gt;</title>', $body);
        $this->assertStringContainsString('Samstag, 02.05.2099', $body);
        $this->assertStringContainsString('Hamm, Gemeindehaus', $body);
        $this->assertStringContainsString('15.04.2099, 23:59 Uhr', $body);
        $this->assertStringContainsString("Herzliche Einladung!\nMit Mittagessen.", $body);
        $this->assertLessThan(strpos($body, 'Jugendstunde'), strpos($body, 'Begrüßung'), 'Ablauf nach Uhrzeit');
        $this->assertStringContainsString('href="/register/"', $body);
        $this->assertStringContainsString('href="https://example.org/info/"', $body);
        $this->assertStringNotContainsString('abgelaufen', $body);
    }

    public function testDoesNotShowQuota(): void
    {
        $this->activeEvent();

        $body = $this->get('/')['body'];

        $this->assertStringNotContainsString('173', $body);
        $this->assertStringNotContainsStringIgnoringCase('Plätze', $body);
    }

    public function testShowsClosedNoticeWithoutButtonAfterDeadline(): void
    {
        $this->activeEvent([
            'title' => 'Vergangene Konferenz',
            'date' => '02.05.2020',
            'registration_deadline_date' => '15.04.2020',
            'registration_deadline_time' => '23:59',
        ]);

        $body = $this->get('/')['body'];

        $this->assertStringContainsString('Vergangene Konferenz', $body);
        $this->assertStringContainsString('Der Anmeldezeitraum ist abgelaufen.', $body);
        $this->assertStringNotContainsString('href="/register/"', $body);
        $this->assertStringContainsString('href="https://example.org/info/"', $body);
    }

    public function testShowsTimezoneOutsideBerlin(): void
    {
        $this->activeEvent(['timezone' => 'Europe/Lisbon']);

        $this->assertStringContainsString('(Europe/Lisbon)', $this->get('/')['body']);
    }
}
