<?php
declare(strict_types=1);

final class AdminRegistrationsTest extends HttpTestCase
{
    private static int $eventId = 0;
    /** @var array<string, int> Programmpunkt-Bezeichnung → ID */
    private static array $slots = [];

    /** Eine Veranstaltung pro Testklasse; jeder Test legt eigene Anmeldungen an */
    private function event(): int
    {
        if (self::$eventId === 0) {
            $db = $this->serverDb();
            $db->exec("INSERT INTO events (title, date, location, registration_deadline, timezone, max_participants, active)
                       VALUES ('Konferenz', '2099-05-01', 'Hamm', '2099-04-15T23:59', 'Europe/Berlin', 10, 1)");
            self::$eventId = (int) $db->lastInsertId();
            foreach (['Vormittag' => '10:00', 'Nachmittag' => '14:00'] as $label => $time) {
                $db->prepare('INSERT INTO event_slots (event_id, time, label) VALUES (?, ?, ?)')
                    ->execute([self::$eventId, $time, $label]);
                self::$slots[$label] = (int) $db->lastInsertId();
            }
            $db->exec("INSERT INTO settings (key, value) VALUES ('preferred_places', 'Hamm')
                       ON CONFLICT (key) DO UPDATE SET value = excluded.value");
        }
        return self::$eventId;
    }

    /** @param array<string, mixed> $fields */
    private function registration(array $fields = []): int
    {
        $fields += [
            'event_id' => $this->event(),
            'created_at' => '2027-03-01T09:30:00Z',
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'congregation' => 'Unna',
            'email' => 'max' . bin2hex(random_bytes(3)) . '@example.org',
            'group_1' => 1,
            'status' => 'pending',
            'cancel_token' => bin2hex(random_bytes(32)),
        ];
        $db = $this->serverDb();
        $columns = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(fn ($k) => ':' . $k, array_keys($fields)));
        $db->prepare("INSERT INTO registrations ($columns) VALUES ($params)")->execute($fields);
        return (int) $db->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function stored(int $id): array
    {
        $stmt = $this->serverDb()->prepare('SELECT * FROM registrations WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function mailCount(int $id): int
    {
        $stmt = $this->serverDb()->prepare('SELECT COUNT(*) FROM mail_log WHERE registration_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function action(int $id, string $action, string $sendMail = '0'): array
    {
        return $this->post('/admin/?event=' . $this->event(), [
            'csrf' => $this->csrfToken('/admin/'),
            'id' => (string) $id,
            'action' => $action,
            'send_mail' => $sendMail,
        ]);
    }

    public function testListShowsRegistrationDetails(): void
    {
        $this->registration(['first_name' => 'Lena', 'last_name' => 'Liste', 'congregation' => 'Hamm',
            'group_1' => 2, 'group_5' => 1, 'status' => 'confirmed']);

        $body = $this->get('/admin/')['body'];

        $this->assertStringContainsString('Lena Liste', $body);
        $this->assertStringContainsString('01.03.2027, 10:30', $body, 'Eingang in Zeitzone der Veranstaltung');
        $this->assertStringContainsString('<strong>Hamm</strong>', $body, 'bevorzugter Ort hervorgehoben');
        $this->assertMatchesRegularExpression('#Lena Liste.*?tabular-nums">3<#s', $body, 'Personen inkl. 0–2');
        $this->assertStringContainsString('>bestätigt</span>', $body);
    }

    public function testListShowsMessage(): void
    {
        $this->registration(['first_name' => 'Nina', 'last_name' => 'Nachricht', 'message' => 'Bitte <Rampe> einplanen']);

        $body = $this->get('/admin/?q=Nachricht')['body'];

        $this->assertStringContainsString('title="Bitte &lt;Rampe&gt; einplanen"', $body);
        $this->assertStringContainsString('message-preview', $body);
    }

    public function testEditKeepsExistingSplitEvenIfEventNoLongerAllowsIt(): void
    {
        $this->serverDb()->prepare('UPDATE events SET allow_split = 0 WHERE id = ?')->execute([$this->event()]);
        $plain = $this->registration(['first_name' => 'Ohne']);
        $split = $this->registration(['first_name' => 'Mit', 'custom_split' => 1]);

        $this->assertStringNotContainsString('id="f-custom_split"', $this->get("/admin/registration.php?id=$plain")['body']);
        $this->assertStringContainsString('id="f-custom_split"', $this->get("/admin/registration.php?id=$split")['body']);
        $this->serverDb()->prepare('UPDATE events SET allow_split = 1 WHERE id = ?')->execute([$this->event()]);
    }

    public function testMarksDuplicates(): void
    {
        $this->registration(['first_name' => 'Doris', 'last_name' => 'Doppelt']);
        $this->registration(['first_name' => ' doris ', 'last_name' => 'DOPPELT', 'status' => 'cancelled']);

        $body = $this->get('/admin/?q=doppelt')['body'];

        $this->assertSame(2, substr_count($body, 'aria-label="Mögliche Dublette"'));
    }

    public function testFiltersByStatusAndSearch(): void
    {
        $this->registration(['first_name' => 'Filter', 'last_name' => 'Offen']);
        $this->registration(['first_name' => 'Filter', 'last_name' => 'Abgelehnt', 'status' => 'rejected']);

        $body = $this->get('/admin/?status=rejected&q=filter')['body'];

        $this->assertStringContainsString('Filter Abgelehnt', $body);
        $this->assertStringNotContainsString('Filter Offen', $body);
        $this->assertStringContainsString('1 Anfrage', $body);
    }

    public function testConfirmWithMailSendsAndLogsMail(): void
    {
        $id = $this->registration(['first_name' => 'Mia', 'last_name' => 'Mail']);

        $response = $this->action($id, 'confirm', '1');

        $this->assertSame(303, $response['status']);
        $this->assertSame('confirmed', $this->stored($id)['status']);
        $this->assertSame(1, $this->mailCount($id));
        $body = $this->get('/admin/')['body'];
        $this->assertStringContainsString('Anmeldung von Mia Mail: bestätigt.', $body);
        $this->assertStringContainsString('gesendet.', $body);
        $this->assertMatchesRegularExpression('#Mia Mail.*?✓\s*Bestätigung#s', $body, 'Mail-Status in der Liste');
    }

    public function testRejectWithoutMailSendsNoMail(): void
    {
        $id = $this->registration();

        $this->action($id, 'reject', '0');

        $this->assertSame('rejected', $this->stored($id)['status']);
        $this->assertSame(0, $this->mailCount($id));
    }

    public function testNoMailWithoutEmailAddressEvenIfRequested(): void
    {
        $id = $this->registration(['email' => null, 'no_email' => 1, 'phone' => '02381 123']);

        $this->action($id, 'confirm', '1');

        $this->assertSame('confirmed', $this->stored($id)['status']);
        $this->assertSame(0, $this->mailCount($id));
    }

    public function testConfirmingBeyondQuotaWarnsButConfirms(): void
    {
        $this->serverDb()->exec("UPDATE registrations SET status = 'cancelled' WHERE status = 'confirmed'");
        $this->registration(['group_1' => 9, 'status' => 'confirmed']);
        $id = $this->registration(['first_name' => 'Quentin', 'last_name' => 'Quote', 'group_1' => 2]);

        $list = $this->get('/admin/?q=Quote')['body'];
        $this->assertMatchesRegularExpression('#data-action="confirm" data-id="' . $id . '".*?data-exceeds="1"#s', $list);

        $this->action($id, 'confirm');

        $this->assertSame('confirmed', $this->stored($id)['status']);
        $this->assertStringContainsString('Kontingent überschritten', $this->get('/admin/')['body']);
    }

    public function testDeleteRemovesRegistration(): void
    {
        $id = $this->registration(['first_name' => 'Lars', 'last_name' => 'Löschen']);

        $this->action($id, 'delete');

        $this->assertSame([], $this->stored($id));
        $this->assertStringContainsString('Anmeldung von Lars Löschen wurde gelöscht.', $this->get('/admin/')['body']);
    }

    public function testActionsRequireCsrfToken(): void
    {
        $id = $this->registration();
        $this->clearCookies();

        $response = $this->post('/admin/?event=' . $this->event(), ['id' => (string) $id, 'action' => 'delete']);

        $this->assertSame(400, $response['status']);
        $this->assertNotSame([], $this->stored($id));
    }

    public function testEditFormIsPrefilled(): void
    {
        $id = $this->registration(['first_name' => 'Erik', 'last_name' => 'Edit', 'group_1' => 2, 'group_2' => 1]);
        $this->serverDb()->prepare('INSERT INTO registration_slots (registration_id, slot_id, group_1, group_2) VALUES (?, ?, 2, 1)')
            ->execute([$id, self::$slots['Vormittag']]);

        $body = $this->get("/admin/registration.php?id=$id")['body'];

        $this->assertStringContainsString('value="Erik"', $body);
        $this->assertMatchesRegularExpression('#name="group_1"\s+value="2"|name="group_1" value="2"#', $body);
        $this->assertMatchesRegularExpression('#name="attend\[' . self::$slots['Vormittag'] . '\]" value="1" checked#', $body);
        $this->assertMatchesRegularExpression('#name="attend\[' . self::$slots['Nachmittag'] . '\]" value="1" >#', $body);
        $this->assertStringContainsString('form.js', $body);
    }

    public function testEditUpdatesRegistrationWithoutMail(): void
    {
        $id = $this->registration(['first_name' => 'Uwe', 'last_name' => 'Update']);

        $response = $this->post("/admin/registration.php?id=$id", [
            'csrf' => $this->csrfToken("/admin/registration.php?id=$id"),
            'status' => 'confirmed',
            'first_name' => 'Uwe',
            'last_name' => 'Geändert',
            'congregation' => 'Soest',
            'email' => 'uwe@example.org',
            'group_1' => '2', 'group_2' => '0', 'group_3' => '0', 'group_4' => '0', 'group_5' => '1',
            'attend' => [self::$slots['Nachmittag'] => '1'],
        ]);

        $this->assertSame(303, $response['status']);
        $stored = $this->stored($id);
        $this->assertSame('Geändert', $stored['last_name']);
        $this->assertSame('confirmed', $stored['status']);
        $this->assertSame(1, (int) $stored['group_5']);
        $this->assertSame(0, $this->mailCount($id));
    }

    public function testEditWithInvalidInputShowsErrors(): void
    {
        $id = $this->registration();

        $response = $this->post("/admin/registration.php?id=$id", [
            'csrf' => $this->csrfToken("/admin/registration.php?id=$id"),
            'status' => 'unbekannt',
            'first_name' => '',
            'last_name' => 'X',
            'congregation' => 'Hamm',
            'email' => 'kaputt',
            'group_1' => '0',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Vorname ist erforderlich.', $response['body']);
        $this->assertStringContainsString('Bitte einen Status auswählen.', $response['body']);
        $this->assertStringContainsString('Bitte eine gültige E-Mail-Adresse angeben.', $response['body']);
    }

    public function testUnknownRegistrationReturns404(): void
    {
        $this->assertSame(404, $this->get('/admin/registration.php?id=99999')['status']);
    }
}
