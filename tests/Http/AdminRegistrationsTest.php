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
        return $this->post('/admin/registrations.php?event=' . $this->event(), [
            'csrf' => $this->csrfToken('/admin/registrations.php'),
            'id' => (string) $id,
            'action' => $action,
            'send_mail' => $sendMail,
        ]);
    }

    public function testListShowsRegistrationDetails(): void
    {
        $this->registration(['first_name' => 'Lena', 'last_name' => 'Liste', 'congregation' => 'Hamm',
            'group_1' => 2, 'group_5' => 1, 'status' => 'confirmed']);

        $body = $this->get('/admin/registrations.php')['body'];

        $this->assertStringContainsString('Lena Liste', $body);
        $this->assertStringContainsString('01.03.2027, 10:30', $body, 'Eingang in Zeitzone der Veranstaltung');
        $this->assertStringContainsString('<strong>Hamm</strong>', $body, 'bevorzugter Ort hervorgehoben');
        $this->assertMatchesRegularExpression('#Lena Liste.*?tabular-nums">3<#s', $body, 'Personen inkl. 0–2');
        $this->assertStringContainsString('>bestätigt</span>', $body);
    }

    public function testDetailsShowPeoplePerGroupAndSlot(): void
    {
        $id = $this->registration(['first_name' => 'Dora', 'last_name' => 'Details', 'group_1' => 2, 'group_5' => 1,
            'custom_split' => 1, 'message' => "Zeile 1\nZeile 2"]);
        $this->serverDb()->prepare('INSERT INTO registration_slots (registration_id, slot_id, group_1, group_5) VALUES (?, ?, 2, 1)')
            ->execute([$id, self::$slots['Vormittag']]);
        $this->serverDb()->prepare('INSERT INTO registration_slots (registration_id, slot_id, group_1) VALUES (?, ?, 1)')
            ->execute([$id, self::$slots['Nachmittag']]);

        $body = $this->get('/admin/registrations.php?q=Details')['body'];

        $this->assertStringContainsString('data-details-toggle', $body);
        $this->assertStringContainsString('aria-controls="details-' . $id . '">Details</button>', $body);
        $start = strpos($body, '<tr class="registration-details" id="details-' . $id . '" hidden>');
        $this->assertNotFalse($start, 'Detailzeile, anfangs zugeklappt');
        // Suche liefert nur diese Anmeldung: Rest der Tabelle = Details
        $raw = substr($body, (int) $start, (int) strpos($body, '</table>', (int) strpos($body, 'Nachricht</div>', (int) $start)) - (int) $start);
        $details = preg_replace('/\s+/', ' ', $raw);
        $this->assertStringContainsString('individuell aufgeteilt', $details);
        $this->assertMatchesRegularExpression('#<th class="text-end text-nowrap">Erwachsene</th> <th class="text-end text-nowrap">Kindergruppe 3</th> <th class="text-end">Summe</th>#', $details, 'nur Gruppen mit Personen');
        $this->assertStringNotContainsString('>Jugendliche</th>', $details);
        $this->assertMatchesRegularExpression('#10:00 Vormittag</td> <td class="text-end">2</td> <td class="text-end">1</td> <td class="text-end fw-semibold">3</td>#', $details);
        $this->assertMatchesRegularExpression('#14:00 Nachmittag</td> <td class="text-end">1</td> <td class="text-end">0</td> <td class="text-end fw-semibold">1</td>#', $details);
        $this->assertStringNotContainsString('>Personen</div>', $details, 'nur die Programmpunkt-Tabelle');
        $this->assertStringContainsString('Verlauf</div> <p class="small text-body-secondary mb-0">Noch keine Einträge.</p>', $details);
        $this->assertStringContainsString("Zeile 1\nZeile 2", $raw, 'ganze Nachricht');
    }

    public function testActionButtonsOrderStateAndDetailsActions(): void
    {
        $pending = $this->registration(['first_name' => 'Paula', 'last_name' => 'Knopf']);
        $confirmed = $this->registration(['first_name' => 'Bruno', 'last_name' => 'Knopf', 'status' => 'confirmed']);
        $rejected = $this->registration(['first_name' => 'Rita', 'last_name' => 'Knopf', 'status' => 'rejected']);

        $body = $this->get('/admin/registrations.php?q=Knopf')['body'];
        $actions = function (int $id) use ($body): string {
            // Aktionsspalte der Zeile: vom Details-Knopf rückwärts bis zum Zellenbeginn und vorwärts bis Zeilenende
            $toggle = strpos($body, 'aria-controls="details-' . $id . '"');
            $start = strrpos(substr($body, 0, (int) $toggle), '<td class="text-end text-nowrap">');
            return substr($body, (int) $start, (int) strpos($body, '</td>', (int) $toggle) - (int) $start);
        };

        // Reihenfolge Bestätigen, Details, Ablehnen; Bearbeiten/Löschen nicht in der Zeile
        $this->assertMatchesRegularExpression('#btn-outline-success"[^>]*data-action="confirm"[^>]*>Bestätigen</button>.*btn-outline-primary" data-details-toggle.*btn-outline-warning"[^>]*data-action="reject"[^>]*>Ablehnen</button>#s', $actions($pending));
        $this->assertStringNotContainsString('Bearbeiten', $actions($pending));
        $this->assertStringNotContainsString('Löschen', $actions($pending));

        // Bereits erreichter Status: grau, nicht klickbar; die Gegenaktion bleibt farbig
        $this->assertStringContainsString('<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Bereits bestätigt">Bestätigen</button>', $actions($confirmed));
        $this->assertMatchesRegularExpression('#btn-outline-warning"[^>]*data-action="reject"#', $actions($confirmed));
        $this->assertStringContainsString('disabled title="Bereits abgelehnt">Ablehnen</button>', $actions($rejected));
        $this->assertMatchesRegularExpression('#btn-outline-success"[^>]*data-action="confirm"#', $actions($rejected));

        // Bearbeiten (orange) und Löschen in den Details
        $details = substr($body, (int) strpos($body, 'id="details-' . $pending . '"'), 6000);
        $this->assertMatchesRegularExpression('#href="/admin/registration.php\?id=' . $pending . '" class="btn btn-sm btn-outline-orange">Bearbeiten</a>\s*<button type="button" class="btn btn-sm btn-outline-danger"\s+data-bs-toggle="modal" data-bs-target="\#delete-registration-modal"\s+data-id="' . $pending . '"#', $details);
    }

    public function testHistoryLogsStatusChangesWithMailOutcome(): void
    {
        $id = $this->registration(['first_name' => 'Vera', 'last_name' => 'Verlauf']);

        $this->action($id, 'confirm', '1');
        $this->action($id, 'reject', '0');
        $this->post("/admin/registration.php?id=$id", [
            'csrf' => $this->csrfToken("/admin/registration.php?id=$id"),
            'status' => 'pending', 'first_name' => 'Vera', 'last_name' => 'Verlauf', 'congregation' => 'Unna',
            'email' => 'vera@example.org', 'group_1' => '1', 'attend' => [self::$slots['Vormittag'] => '1'],
        ]);

        $body = $this->get('/admin/registrations.php?q=Verlauf')['body'];
        $history = substr($body, (int) strpos($body, 'Verlauf</div>', (int) strpos($body, 'id="details-' . $id . '"')), 3000);
        preg_match_all('#<td>[^<]+</td>\s*<td>([^<]+)</td>\s*<td>([^<]+)</td>\s*<td>(.*?)</td>#s', $history, $m, PREG_SET_ORDER);
        $this->assertSame([
            ['Statuswechsel', 'bestätigt', 'ja'],
            ['Statuswechsel', 'abgelehnt', 'nein'],
            ['Bearbeitet', 'offen', 'nein'],
        ], array_map(fn ($r) => [$r[1], $r[2], $r[3]], $m));

        // Bearbeiten ohne Statusänderung gibt ebenfalls einen Eintrag
        $this->post("/admin/registration.php?id=$id", [
            'csrf' => $this->csrfToken("/admin/registration.php?id=$id"),
            'status' => 'pending', 'first_name' => 'Vera', 'last_name' => 'Verlauf', 'congregation' => 'Soest',
            'email' => 'vera@example.org', 'group_1' => '1', 'attend' => [self::$slots['Vormittag'] => '1'],
        ]);
        $log = $this->serverDb()->prepare('SELECT action, status FROM status_log WHERE registration_id = ? ORDER BY id DESC LIMIT 1');
        $log->execute([$id]);
        $this->assertSame(['action' => 'admin_edit', 'status' => 'pending'], $log->fetch(PDO::FETCH_ASSOC));
    }

    public function testListShowsMessage(): void
    {
        $this->registration(['first_name' => 'Nina', 'last_name' => 'Nachricht', 'message' => 'Bitte <Rampe> einplanen']);

        $body = $this->get('/admin/registrations.php?q=Nachricht')['body'];

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

    public function testMessageIsEscapedInEditForm(): void
    {
        $id = $this->registration(['message' => '<script>alert(1)</script>']);

        $body = $this->get("/admin/registration.php?id=$id")['body'];

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;</textarea>', $body);
        $this->assertStringNotContainsString('<script>alert(1)', $body);
    }

    public function testMarksDuplicates(): void
    {
        $this->registration(['first_name' => 'Doris', 'last_name' => 'Doppelt']);
        $this->registration(['first_name' => ' doris ', 'last_name' => 'DOPPELT', 'status' => 'cancelled']);

        $body = $this->get('/admin/registrations.php?q=doppelt')['body'];

        $this->assertSame(2, substr_count($body, 'aria-label="Mögliche Dublette"'));
        $this->assertMatchesRegularExpression('#aria-label="Mögliche Dublette">2️⃣</span>\s*Doris Doppelt#', $body, 'Symbol vor dem Namen');
    }

    public function testFiltersByStatusAndSearch(): void
    {
        $this->registration(['first_name' => 'Filter', 'last_name' => 'Offen']);
        $this->registration(['first_name' => 'Filter', 'last_name' => 'Abgelehnt', 'status' => 'rejected']);

        $body = $this->get('/admin/registrations.php?status=rejected&q=filter')['body'];

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
        $body = $this->get('/admin/registrations.php')['body'];
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

        $list = $this->get('/admin/registrations.php?q=Quote')['body'];
        $this->assertMatchesRegularExpression('#data-action="confirm" data-id="' . $id . '".*?data-exceeds="1"#s', $list);

        $this->action($id, 'confirm');

        $this->assertSame('confirmed', $this->stored($id)['status']);
        $this->assertStringContainsString('Kontingent überschritten', $this->get('/admin/registrations.php')['body']);
    }

    public function testDeleteRemovesRegistration(): void
    {
        $id = $this->registration(['first_name' => 'Lars', 'last_name' => 'Löschen']);

        $this->action($id, 'delete');

        $this->assertSame([], $this->stored($id));
        $this->assertStringContainsString('Anmeldung von Lars Löschen wurde gelöscht.', $this->get('/admin/registrations.php')['body']);
    }

    public function testActionsRequireCsrfToken(): void
    {
        $id = $this->registration();
        $this->clearCookies();

        $response = $this->post('/admin/registrations.php?event=' . $this->event(), ['id' => (string) $id, 'action' => 'delete']);

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
