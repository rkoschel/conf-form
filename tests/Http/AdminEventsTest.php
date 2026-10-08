<?php
declare(strict_types=1);

final class AdminEventsTest extends HttpTestCase
{
    /** @return array<string, mixed> gültige Formulardaten */
    private function formData(string $title, array $overrides = []): array
    {
        return $overrides + [
            'csrf' => $this->csrfToken('/admin/event.php'),
            'person_groups' => array_keys(PERSON_GROUPS),
            'group_names' => person_groups_default(),
            'title' => $title,
            'date' => '01.05.2027',
            'location' => 'Hamm',
            'description' => 'Beschreibung',
            'registration_deadline_date' => '15.04.2027',
            'registration_deadline_time' => '23:59',
            'timezone' => 'Europe/Berlin',
            'max_participants' => '120',
            'organizer_name' => 'Orga',
            'organizer_email' => 'orga@example.org',
            'slots' => [
                ['time' => '14:00', 'label' => 'Nachmittag'],
                ['time' => '10:00', 'label' => 'Vormittag'],
            ],
        ];
    }

    /** Legt eine Veranstaltung über das Formular an und liefert ihre ID */
    private function createEvent(string $title, array $overrides = []): int
    {
        $response = $this->post('/admin/event.php', $this->formData($title, $overrides));
        $this->assertSame(303, $response['status'], 'Anlegen fehlgeschlagen: ' . strip_tags($response['body']));

        $list = $this->get('/admin/events.php')['body'];
        $pattern = '#' . preg_quote(e($title), '#') . '.*?event\.php\?id=(\d+)#s';
        $this->assertMatchesRegularExpression($pattern, $list);
        preg_match($pattern, $list, $m);
        return (int) $m[1];
    }

    public function testCreateEventRedirectsToListWithMessage(): void
    {
        $response = $this->post('/admin/event.php', $this->formData('Frühjahrskonferenz'));

        $this->assertSame(303, $response['status']);
        $this->assertSame('/admin/events.php', $response['headers']['location'] ?? null);

        $list = $this->get('/admin/events.php')['body'];
        $this->assertStringContainsString('Veranstaltung angelegt.', $list);
        $this->assertStringContainsString('Frühjahrskonferenz', $list);
        $this->assertStringContainsString('01.05.2027', $list);
    }

    public function testEditFormShowsSavedValuesWithSortedSlots(): void
    {
        $id = $this->createEvent('Bearbeiten-Test');

        $body = $this->get("/admin/event.php?id=$id")['body'];

        $this->assertStringContainsString('value="Bearbeiten-Test"', $body);
        $this->assertStringContainsString('name="date" value="01.05.2027"', $body);
        $this->assertMatchesRegularExpression('#name="registration_deadline_date"\s+value="15\.04\.2027"#', $body);
        $this->assertStringContainsString('value="23:59"', $body);
        $this->assertStringContainsString('value="10:00"', $body);
        $this->assertStringNotContainsString('name="places"', $body, 'Orte werden in den Einstellungen gepflegt');
        $this->assertLessThan(strpos($body, 'Nachmittag'), strpos($body, 'Vormittag'), 'Ablauf nach Uhrzeit sortiert');
        $this->assertStringContainsString('data-add-slot', $body);
    }

    public function testInvalidInputShowsErrorsAndKeepsValues(): void
    {
        $response = $this->post('/admin/event.php', $this->formData('Fehler-Test', [
            'location' => '',
            'registration_deadline_date' => '01.05.2027',
            'registration_deadline_time' => '12:00',
        ]));

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Bitte die markierten Felder prüfen.', $response['body']);
        $this->assertStringContainsString('Ort ist erforderlich.', $response['body']);
        $this->assertStringContainsString('spätestens am 01.05.2027, 10:00 Uhr', $response['body']);
        $this->assertStringContainsString('value="Fehler-Test"', $response['body']);
        $this->assertStringContainsString('value="Nachmittag"', $response['body']);
    }

    public function testChildcareIsSavedAndShownInEditForm(): void
    {
        $id = $this->createEvent('Betreuung-Test', [
            'slots' => [
                ['time' => '10:00', 'label' => 'Vortrag', 'childcare' => '1', 'childcare_groups' => ['group_5', 'group_4']],
                ['time' => '14:00', 'label' => 'Mittag'],
            ],
        ]);

        $body = $this->get("/admin/event.php?id=$id")['body'];

        $this->assertMatchesRegularExpression('#name="slots\[0\]\[childcare\]"\s+checked#', $body);
        $this->assertMatchesRegularExpression('#value="group_4"[^>]*name="slots\[0\]\[childcare_groups\]\[\]"\s+checked#', $body);
        $this->assertMatchesRegularExpression('#value="group_3"[^>]*name="slots\[0\]\[childcare_groups\]\[\]"\s+>#', $body);
        $this->assertMatchesRegularExpression('#name="slots\[1\]\[childcare\]"\s+>#', $body);
    }

    public function testChildcareWithoutGroupShowsError(): void
    {
        $response = $this->post('/admin/event.php', $this->formData('Betreuung-Fehler', [
            'slots' => [['time' => '10:00', 'label' => 'Vortrag', 'childcare' => '1']],
        ]));

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Kindergruppen für die Kinderbetreuung auswählen.', $response['body']);
    }

    public function testPersonGroupsAreSavedAndPrefilledForNewEvents(): void
    {
        $id = $this->createEvent('Gruppen-Test', [
            'person_groups' => ['group_1', 'group_4'],
            'group_names' => ['group_1' => 'Eltern', 'group_4' => 'Kinder 3–6', 'group_2' => 'egal'],
        ]);

        $edit = $this->get("/admin/event.php?id=$id")['body'];
        $this->assertMatchesRegularExpression('#value="group_1"\s+id="g-group_1" data-person-group="group_1"\s+checked#', $edit);
        $this->assertMatchesRegularExpression('#value="group_2"\s+id="g-group_2" data-person-group="group_2"\s+>#', $edit, 'nicht gewählt');
        $this->assertStringContainsString('name="group_names[group_1]"' . "\n" . '                   value="Eltern"', $edit);

        $new = $this->get('/admin/event.php')['body'];
        $this->assertMatchesRegularExpression('#data-person-group="group_4"\s+checked#', $new, 'neue Veranstaltung übernimmt die zuletzt angelegte');
        $this->assertStringContainsString('value="Kinder 3–6"', $new);
        $this->assertMatchesRegularExpression('#data-childcare-option="group_5" hidden#', $new, 'nicht gewählte Kindergruppe in der Betreuung ausgeblendet');
        $this->assertStringNotContainsString('data-childcare-option="group_2"', $new, 'Jugendliche nicht betreubar');
    }

    public function testGroupSelectionIsLockedOnceRegistrationsExistButNamesStayEditable(): void
    {
        $id = $this->createEvent('Gesperrt-Test', [
            'person_groups' => ['group_1', 'group_2'],
            'group_names' => ['group_1' => 'Erwachsene', 'group_2' => 'Jugendliche'],
        ]);
        $this->serverDb()->prepare(
            "INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, group_1, status, cancel_token)
             VALUES (?, ?, 'A', 'B', 'Hamm', 1, 'pending', ?)"
        )->execute([$id, now_utc(), bin2hex(random_bytes(32))]);

        $edit = $this->get("/admin/event.php?id=$id")['body'];
        $this->assertStringContainsString('die Auswahl der Personengruppen kann nicht mehr geändert werden', $edit);
        $this->assertMatchesRegularExpression('#data-person-group="group_3"\s+disabled#', $edit);

        $response = $this->post("/admin/event.php?id=$id", $this->formData('Gesperrt-Test', [
            'csrf' => $this->csrfToken("/admin/event.php?id=$id"),
            'person_groups' => ['group_3'],
            'group_names' => ['group_1' => 'Eltern', 'group_2' => 'Teens', 'group_3' => 'Kinder'],
        ]));

        $this->assertSame(303, $response['status']);
        $stmt = $this->serverDb()->prepare('SELECT person_groups FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame(['group_1' => 'Eltern', 'group_2' => 'Teens'], json_decode((string) $stmt->fetchColumn(), true));
    }

    public function testNoGroupSelectedShowsError(): void
    {
        $response = $this->post('/admin/event.php', $this->formData('Ohne Gruppe', ['person_groups' => []]));

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Bitte mindestens eine Personengruppe auswählen.', $response['body']);
    }

    public function testUnknownEventReturns404(): void
    {
        $this->assertSame(404, $this->get('/admin/event.php?id=99999')['status']);
    }

    public function testActivatedEventAppearsOnInfoPage(): void
    {
        $id = $this->createEvent('Aktiv-Test');

        $response = $this->post('/admin/events.php', [
            'csrf' => $this->csrfToken('/admin/events.php'),
            'id' => (string) $id,
            'action' => 'activate',
        ]);

        $this->assertSame(303, $response['status']);
        $this->assertStringContainsString('„Aktiv-Test“ ist jetzt aktiv.', $this->get('/admin/events.php')['body']);
        $this->assertStringContainsString('Aktiv-Test', $this->get('/')['body']);
    }

    public function testDeleteRemovesEvent(): void
    {
        $id = $this->createEvent('Lösch-Test');

        $this->post('/admin/events.php', [
            'csrf' => $this->csrfToken('/admin/events.php'),
            'id' => (string) $id,
            'action' => 'delete',
        ]);

        $list = $this->get('/admin/events.php')['body'];
        $this->assertStringContainsString('„Lösch-Test“ wurde gelöscht.', $list);
        $this->assertStringNotContainsString("event.php?id=$id\"", $list);
        $this->assertSame(404, $this->get("/admin/event.php?id=$id")['status']);
    }

    public function testActionsRequireCsrfToken(): void
    {
        $id = $this->createEvent('CSRF-Test');
        $this->clearCookies();

        $response = $this->post('/admin/events.php', ['id' => (string) $id, 'action' => 'delete']);

        $this->assertSame(400, $response['status']);
        $this->assertSame(200, $this->get("/admin/event.php?id=$id")['status']);
    }
}
