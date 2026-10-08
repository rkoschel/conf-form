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
        preferred_places_set(['Hamm']);
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
            'group_1' => '2',
            'group_5' => '1',
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

    public function testExplainsAttendanceAndOffersSplitResetDialog(): void
    {
        $body = $this->get('/register/')['body'];

        $this->assertStringContainsString('die Räumlichkeiten besser zu nutzen und möglichst vielen die Teilnahme zu ermöglichen', $body);
        $this->assertStringContainsString('id="split-reset-modal"', $body);
        $this->assertStringContainsString('data-split-reset-confirm', $body);
    }

    /** Programmpunkt „Vortrag“ bekommt Kinderbetreuung für die Gruppen 4 und 5 */
    private function withChildcare(): void
    {
        db()->prepare("UPDATE event_slots SET childcare = 'group_5,group_4' WHERE id = ?")
            ->execute([$this->event['slots'][0]['id']]);
        $this->event = event_find((int) $this->event['id']);
    }

    public function testShowsChildcareNoticesAndZeroDefaultsForCoveredGroups(): void
    {
        $this->withChildcare();

        $body = $this->get('/register/')['body'];
        $vortrag = $this->event['slots'][0]['id'];
        $jugend = $this->event['slots'][1]['id'];

        $this->assertSame(2, substr_count($body, 'Parallel Kinderbetreuung für Kindergruppe 2 und Kindergruppe 3'), 'Ankreuzen und Aufteilung');
        $this->assertMatchesRegularExpression('#<div class="col-12" data-childcare-hint hidden>#', $body, 'ohne Kinder kein Hinweis');
        $this->assertStringContainsString('10:00 Uhr Vortrag: Kindergruppe 2 und Kindergruppe 3', $body);
        $this->assertStringContainsString('data-childcare-split-hint hidden', $body);
        $this->assertMatchesRegularExpression('#name="split\[' . $vortrag . '\]\[group_4\]"\s+value="0"#', $body, 'betreute Gruppe mit 0 vorbelegt');
        $this->assertMatchesRegularExpression('#data-childcare-slot=""#', $body, 'Programmpunkt ohne Betreuung');
        $this->assertStringNotContainsString("$jugend: Kinder", $body);
    }

    public function testChildcareHintVisibleAfterValidationErrorWithChildren(): void
    {
        $this->withChildcare();

        $response = $this->submit(['first_name' => '', 'group_4' => '2']);

        $this->assertSame(200, $response['status']);
        $this->assertMatchesRegularExpression('#<div class="col-12" data-childcare-hint>#', $response['body']);
    }

    public function testRegistrationStoresChildrenInChildcare(): void
    {
        $this->withChildcare();
        $vortrag = $this->event['slots'][0]['id'];

        $this->submit(['group_4' => '2', 'attend' => [$vortrag => '1']]);

        $registration = $this->registrations()[0];
        $counts = registration_slot_counts((int) $registration['id'])[$vortrag];
        $this->assertSame([2, 0, 2, 1], [
            $counts['group_1'], $counts['group_4'], $counts['childcare_group_4'], $counts['childcare_group_5'],
        ]);
    }

    public function testShowsOnlyEventGroupsWithTheirNames(): void
    {
        db()->prepare('UPDATE events SET person_groups = ? WHERE id = ?')
            ->execute(['{"group_1":"Eltern","group_4":"Kinder 3–6"}', $this->event['id']]);

        $body = $this->get('/register/')['body'];

        $this->assertStringContainsString('>Eltern</label>', $body);
        $this->assertStringContainsString('>Kinder 3–6</label>', $body);
        $this->assertStringNotContainsString('data-count="group_2"', $body);
        $this->assertStringNotContainsString('data-count="group_5"', $body);
        $this->assertMatchesRegularExpression('#name="group_1"\s+value="1"|name="group_1" value="1"#', $body, 'eine Person in der ersten Gruppe vorbelegt');
    }

    public function testGroupsNotOfferedAreIgnoredOnSubmit(): void
    {
        db()->prepare('UPDATE events SET person_groups = ? WHERE id = ?')->execute(['{"group_1":"Eltern"}', $this->event['id']]);

        $this->submit(['group_1' => '2', 'group_5' => '7']);

        $registration = $this->registrations()[0];
        $this->assertSame([2, 0], [(int) $registration['group_1'], (int) $registration['group_5']]);
    }

    public function testHidesSplitOptionWhenNotAllowed(): void
    {
        $this->assertStringContainsString('id="f-custom_split"', $this->get('/register/')['body'], 'Standard: erlaubt');

        db()->prepare('UPDATE events SET allow_split = 0 WHERE id = ?')->execute([$this->event['id']]);
        $body = $this->get('/register/')['body'];

        $this->assertStringNotContainsString('id="f-custom_split"', $body);
        $this->assertStringNotContainsString('data-split', $body);
        $this->assertStringNotContainsString('split-reset-modal', $body);
        $this->assertStringContainsString('name="attend[', $body, 'Ankreuzen je Programmpunkt bleibt');
    }

    public function testMessageFieldWithPlaceholderIsStored(): void
    {
        $body = $this->get('/register/')['body'];
        $this->assertMatchesRegularExpression(
            '#<textarea id="f-message" name="message"[^>]*placeholder="Falls wir noch etwas berücksichtigen sollten, lass es uns gerne wissen\.">\s*</textarea>#',
            $body
        );

        $this->submit(['message' => 'Wir bringen einen Rollstuhl mit.']);

        $this->assertSame('Wir bringen einen Rollstuhl mit.', $this->registrations()[0]['message']);
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
        $this->assertSame(2, $slots[$this->event['slots'][0]['id']]['group_1']);
        $this->assertSame(0, $slots[$this->event['slots'][1]['id']]['group_1']);

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

    public function testMoreQuotaPersonsThanCapacityIsRejected(): void
    {
        // Kapazität der Test-Veranstaltung: 10
        $response = $this->submit(['group_1' => '11', 'group_5' => '0']);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('is-invalid', $response['body']);
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
        $this->post('/register/', ['first_name' => 'Anna', 'last_name' => 'Muster', 'group_1' => '1']);
        $this->assertSame([], $this->registrations());
    }

    public function testClosedWithoutActiveEvent(): void
    {
        db()->exec('UPDATE events SET active = 0');

        $this->assertStringContainsString('Derzeit ist keine Anmeldung möglich.', $this->get('/register/')['body']);
    }
}
