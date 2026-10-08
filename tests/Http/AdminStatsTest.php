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
        $this->assertMatchesRegularExpression('#9 <span[^>]*>/ 10</span>#', $body, 'Belegung mit allen Gruppen');
        $this->assertStringContainsString('90 % belegt, 1 frei', $body);
        $this->assertStringContainsString('Offen 3', $body);
        $this->assertStringContainsString('Wenn alle offenen bestätigt würden: 12 von 10', $body);
        $this->assertStringContainsString('Kontingent um 2 überschritten', $body);
        $this->assertMatchesRegularExpression('#Je Personengruppe.*?Erwachsene</span>\s*<span[^>]*>8 bestätigt · 3 offen#s', $body);
        $this->assertMatchesRegularExpression('#Kindergruppe 3</span>\s*<span[^>]*>1 bestätigt · 0 offen#s', $body);
        $this->assertStringNotContainsString('nach Altersgruppe', $body, 'alte Tabelle entfernt');
        $this->assertStringContainsString('quota-meter-limit', $body, 'Grenze markiert, wenn bestätigt + offen darüber liegt');
        $this->assertMatchesRegularExpression('#Personen je Ort.*?Hamm\s*</td>\s*<td class="text-end">9</td>\s*<td class="text-end">3</td>#s', $body);

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

    public function testPendingWithinQuotaShowsPercentageWithoutLimitMarker(): void
    {
        $db = $this->serverDb();
        $id = $this->event($db, 'Luft nach oben', 0, 20);
        $this->registration($db, $id, 'confirmed', 5, 0);
        $this->registration($db, $id, 'pending', 5, 2);

        $body = $this->get("/admin/stats.php?event=$id")['body'];

        $this->assertStringContainsString('Wenn alle offenen bestätigt würden: 12 von 20', $body);
        $this->assertStringContainsString('(60 %)', $body);
        $this->assertStringNotContainsString('quota-meter-limit', $body);
        $this->assertStringContainsString('style="width: 25.00%"', $body, 'Segmente relativ zum Kontingent');
    }

    public function testNoPendingHidesProjection(): void
    {
        $db = $this->serverDb();
        $id = $this->event($db, 'Ohne offene', 0, 20);
        $this->registration($db, $id, 'confirmed', 5, 0);

        $body = $this->get("/admin/stats.php?event=$id")['body'];

        $this->assertStringNotContainsString('Wenn alle offenen', $body);
        $this->assertStringNotContainsString('aria-label="Offen"', $body);
    }

    public function testShowsChildcareRowPerSlot(): void
    {
        $db = $this->serverDb();
        $id = $this->event($db, 'Mit Betreuung', 0, 20);
        $db->prepare("INSERT INTO event_slots (event_id, time, label, childcare) VALUES (?, '10:00', 'Vortrag', 'group_5,group_4')")
            ->execute([$id]);

        $body = $this->get("/admin/stats.php?event=$id")['body'];

        $this->assertStringContainsString('Kinderbetreuung je Programmpunkt', $body);
        $this->assertStringContainsString('<td>Kindergruppe 2 und Kindergruppe 3</td>', $body);
        $this->assertStringContainsString('Betreuung für Kindergruppe 2 und Kindergruppe 3', $body, 'mobile Ansicht');
        $this->assertStringNotContainsString('↳', $body, 'keine Zusatzzeilen mehr in der Programmpunkt-Tabelle');
    }

    public function testNoChildcareTableWithoutChildcare(): void
    {
        $db = $this->serverDb();
        $id = $this->event($db, 'Ohne Betreuung', 0, 20);
        $db->prepare("INSERT INTO event_slots (event_id, time, label) VALUES (?, '10:00', 'Vortrag')")->execute([$id]);

        $this->assertStringNotContainsString('Kinderbetreuung je Programmpunkt', $this->get("/admin/stats.php?event=$id")['body']);
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

    private function registration(PDO $db, int $eventId, string $status, int $group_1, int $toddlers): void
    {
        $db->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, group_1, group_5, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, now_utc(), 'Max', 'Muster', 'Hamm', $group_1, $toddlers, $status, bin2hex(random_bytes(32))]);
    }
}
