<?php
declare(strict_types=1);

final class TeamPageTest extends HttpTestCase
{
    protected function setUp(): void
    {
        db($this->serverDb());
        db()->exec('DELETE FROM events');
        team_key_disable();
    }

    private function activeEvent(string $title): void
    {
        db()->prepare(
            "INSERT INTO events (title, date, location, registration_deadline, max_participants, active)
             VALUES (?, '2099-05-02', 'Hamm', '2099-04-15T23:59', 50, 1)"
        )->execute([$title]);
        $id = (int) db()->lastInsertId();
        db()->prepare(
            "INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, group_1, status, cancel_token)
             VALUES (?, ?, 'Max', 'Muster', 'Hamm', 7, 'confirmed', ?)"
        )->execute([$id, now_utc(), bin2hex(random_bytes(32))]);
    }

    public function testNotFoundWithoutValidKey(): void
    {
        $key = team_key_generate();
        $this->activeEvent('Konferenz');

        $this->assertSame(404, $this->get('/team/')['status']);
        $this->assertSame(404, $this->get('/team/?k=falsch')['status']);
        $this->assertSame(404, $this->get('/team/?k=' . substr($key, 0, -1))['status']);
        $this->assertSame(404, $this->get('/team/?k[]=' . $key)['status']);
    }

    public function testNotFoundWhenDisabled(): void
    {
        $key = team_key_generate();
        team_key_disable();

        $this->assertSame(404, $this->get('/team/?k=' . $key)['status']);
        $this->assertSame(404, $this->get('/team/?k=')['status']);
    }

    public function testShowsStatsOfActiveEventWithTitleOnTop(): void
    {
        $key = team_key_generate();
        $this->activeEvent('Frühjahrskonferenz <2099>');

        $response = $this->get('/team/?k=' . $key);
        $body = $response['body'];

        $this->assertSame(200, $response['status']);
        $this->assertMatchesRegularExpression('#<h1[^>]*>Frühjahrskonferenz &lt;2099&gt;</h1>#', $body);
        $this->assertStringContainsString('Samstag, 02.05.2099', $body);
        $this->assertStringContainsString('Stand', $body);
        $this->assertStringContainsString('Gesamtbelegung', $body);
        $this->assertMatchesRegularExpression('#7 <span[^>]*>/ 50</span>#', $body, 'absolute Zahlen für das Team');
        $this->assertStringNotContainsString('name="event"', $body, 'keine Veranstaltungsauswahl');
        $this->assertStringNotContainsString('Konferenz-Admin', $body, 'keine Admin-Navigation');
        $this->assertSame('no-store', $response['headers']['cache-control'] ?? null);
        $this->assertSame('noindex, nofollow', $response['headers']['x-robots-tag'] ?? null);
    }

    public function testShowsThreeSeparatedAreasWithTheirContent(): void
    {
        $key = team_key_generate();
        $this->activeEvent('Bereiche');
        $id = (int) db()->query('SELECT id FROM events')->fetchColumn();
        db()->prepare("INSERT INTO event_slots (event_id, time, label, childcare) VALUES (?, '10:00', 'Vortrag', 'group_4')")->execute([$id]);
        db()->prepare(
            "INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, group_1, status, cancel_token)
             VALUES (?, ?, 'Eva', 'Offen', 'Hamm', 2, 'pending', ?)"
        )->execute([$id, now_utc(), bin2hex(random_bytes(32))]);

        $body = $this->get('/team/?k=' . $key)['body'];

        $this->assertSame(3, substr_count($body, '<section class="stats-area '), 'drei Bereiche');
        $areas = array_slice(explode('<section class="stats-area ', $body), 1);

        // 1. zugesagte Anmeldungen: Anwesenheit und Kinderbetreuung je Programmpunkt
        $this->assertStringContainsString('Zugesagte Anmeldungen', $areas[0]);
        $this->assertStringContainsString('Voraussichtliche Anwesenheit je Programmpunkt', $areas[0]);
        $this->assertStringContainsString('Kinderbetreuung je Programmpunkt', $areas[0]);
        $this->assertStringNotContainsString('Anfragen nach Status', $areas[0]);
        $this->assertDoesNotMatchRegularExpression('#/ 50#', $areas[0]);

        // 2. Gesamtbelegung und Anmeldungen: die zwei Kacheln
        $this->assertStringContainsString('Gesamtbelegung und Anmeldungen', $areas[1]);
        $this->assertMatchesRegularExpression('#7 <span[^>]*>/ 50</span>#', $areas[1]);
        $this->assertStringContainsString('Personen je Ort', $areas[1]);
        $this->assertStringNotContainsString('Voraussichtliche Anwesenheit je Programmpunkt', $areas[1]);
        $this->assertStringNotContainsString('<th>Status</th>', $areas[1]);

        // 3. Anfragen nach Status
        $this->assertStringContainsString('Anfragen nach Status', $areas[2]);
        $this->assertStringContainsString('<th>Status</th>', $areas[2]);
        $this->assertStringNotContainsString('Personen je Ort', $areas[2]);
        $this->assertSame(1, substr_count($body, '<th>Status</th>'), 'Tabelle nur einmal');
        $this->assertSame(1, substr_count($body, '>Anfragen nach Status<'), 'Überschrift nur einmal');
    }

    public function testAdminStatsKeepsItsOwnLayoutWithoutAreas(): void
    {
        $this->activeEvent('Admin bleibt');

        $body = $this->get('/admin/stats.php')['body'];

        $this->assertStringNotContainsString('stats-area', $body);
        $this->assertGreaterThan(strpos($body, 'Gesamtbelegung'), strpos($body, 'Anfragen nach Status'), 'bisherige Reihenfolge');
    }

    public function testWithoutActiveEventShowsNotice(): void
    {
        $key = team_key_generate();

        $body = $this->get('/team/?k=' . $key)['body'];

        $this->assertStringContainsString('Derzeit ist keine Veranstaltung aktiv.', $body);
        $this->assertStringNotContainsString('stats-area', $body, 'ohne Veranstaltung keine Bereiche');
    }
}
