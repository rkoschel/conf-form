<?php
declare(strict_types=1);

final class AdminStatsTest extends HttpTestCase
{
    public function testShowsEmptyStateWithoutEvents(): void
    {
        $response = $this->get('/admin/stats.php');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Noch keine Veranstaltungen angelegt.', $response['body']);
    }

    public function testShowsActiveEventByDefaultAndSelectedEventOnRequest(): void
    {
        $db = $this->serverDb();
        $active = $this->event($db, 'Aktive Konferenz', 1, 10);
        $other = $this->event($db, 'Andere Konferenz', 0, 50);
        $this->registration($db, $active, 'confirmed', 8, 1);
        $this->registration($db, $active, 'pending', 3, 0);

        $body = $this->get('/admin/stats.php')['body'];
        $this->assertMatchesRegularExpression('#<option value="' . $active . '"\s+selected>#', $body);
        $this->assertMatchesRegularExpression('#8 <span[^>]*>/ 10</span>#', $body, 'Belegung ohne Kinder 0–2');
        $this->assertStringContainsString('80 % belegt, 2 frei', $body);

        $body = $this->get("/admin/stats.php?event=$other")['body'];
        $this->assertMatchesRegularExpression('#<option value="' . $other . '"\s+selected>#', $body);
        $this->assertMatchesRegularExpression('#0 <span[^>]*>/ 50</span>#', $body);
    }

    public function testWarnsWhenQuotaIsExceeded(): void
    {
        $db = $this->serverDb();
        $id = $this->event($db, 'Volle Konferenz', 0, 5);
        $this->registration($db, $id, 'confirmed', 7, 0);

        $body = $this->get("/admin/stats.php?event=$id")['body'];

        $this->assertStringContainsString('Kontingent um 2 überschritten', $body);
        $this->assertStringContainsString('bg-danger', $body);
    }

    public function testUnknownEventReturns404(): void
    {
        $this->assertSame(404, $this->get('/admin/stats.php?event=99999')['status']);
    }

    private function event(PDO $db, string $title, int $active, int $max): int
    {
        if ($active) {
            $db->exec('UPDATE events SET active = 0');
        }
        $db->prepare(
            "INSERT INTO events (title, date, location, registration_deadline, max_participants, active)
             VALUES (?, '2099-05-01', 'Hamm', '2099-04-15T23:59', ?, ?)"
        )->execute([$title, $max, $active]);
        return (int) $db->lastInsertId();
    }

    private function registration(PDO $db, int $eventId, string $status, int $adults, int $toddlers): void
    {
        $db->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, adults, kids_0_2, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, now_utc(), 'Max', 'Muster', 'Hamm', $adults, $toddlers, $status, bin2hex(random_bytes(32))]);
    }
}
