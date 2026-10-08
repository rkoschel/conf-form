<?php
declare(strict_types=1);

final class InfoPageTest extends HttpTestCase
{
    /** Legt über das Admin-Formular eine aktive Veranstaltung an */
    private function activeEvent(array $overrides = []): void
    {
        $response = $this->post('/admin/event.php', $overrides + [
            'csrf' => $this->csrfToken('/admin/event.php'),
            'person_groups' => array_keys(PERSON_GROUPS),
            'group_names' => person_groups_default(),
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

    public function testShowsOccupancyOnlyAsPercentagesAboveButton(): void
    {
        $this->activeEvent(['max_participants' => '173']);
        $this->registrations(['confirmed' => 52, 'pending' => 26]);

        $body = $this->get('/')['body'];

        // 52/173 = 30 %, 26/173 = 15 %, frei 55 %
        $this->assertStringContainsString('Bestätigt 30 %', $body);
        $this->assertStringContainsString('Warteliste 15 %', $body);
        $this->assertStringContainsString('Frei 55 %', $body);
        $this->assertLessThan(strpos($body, 'href="/register/"'), strpos($body, 'Belegung'), 'oberhalb des Buttons');
        $text = strip_tags($body);
        foreach (['173', '52', '26', '78', '95'] as $absolute) {
            $this->assertStringNotContainsString($absolute, $text, "keine absolute Zahl im Text ($absolute)");
        }
        // In der Kachel (auch in Attributen wie width/aria) nur die Prozentwerte
        $start = strpos($body, '<div class="card mb-4">');
        $card = substr($body, $start, strpos($body, 'Frei 55 %', $start) - $start);
        preg_match_all('/\d+/', (string) preg_replace(['/class="[^"]*"/', '#</?[a-z][a-z0-9]*#i'], '', $card), $numbers);
        $this->assertSame([], array_values(array_diff(array_unique($numbers[0]), ['0', '15', '30', '100'])));
        $this->assertStringNotContainsStringIgnoringCase('Plätze', $body);
    }

    public function testShowsFullyBookedNoticeWhenNothingIsFree(): void
    {
        $this->activeEvent(['title' => 'Voll', 'max_participants' => '100']);
        $this->registrations(['confirmed' => 90, 'pending' => 15]);

        $body = $this->get('/')['body'];

        $this->assertStringContainsString('Frei 0 %', $body);
        $this->assertStringContainsString('Aktuell scheint die Veranstaltung ausgebucht zu sein.', $body);
        $this->assertStringContainsString('um auf die Warteliste zu kommen', $body);
        $this->assertStringContainsString('melden wir uns bei dir.', $body);
        $this->assertStringContainsString('href="/register/"', $body, 'Anmelden bleibt möglich');
    }

    public function testNoFullyBookedNoticeWhileSomethingIsFree(): void
    {
        $this->activeEvent(['title' => 'Noch Platz', 'max_participants' => '100']);
        $this->registrations(['confirmed' => 90, 'pending' => 5]);

        $body = $this->get('/')['body'];

        $this->assertStringContainsString('Frei 5 %', $body);
        $this->assertStringNotContainsString('ausgebucht', $body);
    }

    public function testHidesOccupancyAfterDeadline(): void
    {
        $this->activeEvent([
            'title' => 'Vorbei',
            'date' => '02.05.2020',
            'registration_deadline_date' => '15.04.2020',
            'registration_deadline_time' => '23:59',
        ]);

        $this->assertStringNotContainsString('Belegung', $this->get('/')['body']);
    }

    /** Legt für die aktive Veranstaltung je Status eine Anmeldung mit n Erwachsenen an */
    private function registrations(array $adultsByStatus): void
    {
        $db = $this->serverDb();
        $eventId = (int) $db->query('SELECT id FROM events WHERE active = 1')->fetchColumn();
        foreach ($adultsByStatus as $status => $group_1) {
            $db->prepare(
                'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, group_1, group_5, status, cancel_token)
                 VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)'
            )->execute([$eventId, now_utc(), 'Max', 'Muster', 'Hamm', $group_1, $status, bin2hex(random_bytes(32))]);
        }
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

    public function testShowsChildcareNoticeForSlots(): void
    {
        $this->activeEvent([
            'title' => 'Mit Betreuung',
            'slots' => [
                ['time' => '10:00', 'label' => 'Begrüßung', 'childcare' => '1', 'childcare_groups' => ['group_5', 'group_4']],
                ['time' => '14:00', 'label' => 'Jugendstunde'],
            ],
        ]);

        $body = $this->get('/')['body'];

        $this->assertSame(1, substr_count($body, 'Parallel Kinderbetreuung für Kindergruppe 2 und Kindergruppe 1'));
        $this->assertLessThan(strpos($body, 'Jugendstunde'), strpos($body, 'Kinderbetreuung'), 'beim richtigen Programmpunkt');
    }

    public function testShowsTimezoneOutsideBerlin(): void
    {
        $this->activeEvent(['timezone' => 'Europe/Lisbon']);

        $this->assertStringContainsString('(Europe/Lisbon)', $this->get('/')['body']);
    }
}
