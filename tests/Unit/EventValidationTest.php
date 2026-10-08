<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventValidationTest extends TestCase
{
    /** @return array<string, mixed> gültige Eingabe, einzelne Felder überschreibbar */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Konferenz 2027',
            'date' => '01.05.2027',
            'location' => 'Hamm',
            'description' => '',
            'registration_deadline_date' => '15.04.2027',
            'registration_deadline_time' => '23:59',
            'timezone' => 'Europe/Berlin',
            'max_participants' => '150',
            'organizer_name' => '',
            'organizer_email' => '',
            'active' => false,
            'slots' => [['time' => '10:00', 'label' => 'Begrüßung']],
            'person_groups' => array_keys(PERSON_GROUPS),
            'group_names' => person_groups_default(),
        ];
    }

    public function testAcceptsValidInput(): void
    {
        [$data, $errors] = event_validate($this->input());

        $this->assertSame([], $errors);
        $this->assertSame('2027-05-01', $data['date'], 'ISO gespeichert');
        $this->assertSame('2027-04-15T23:59', $data['registration_deadline']);
        $this->assertSame(150, $data['max_participants']);
        $this->assertFalse($data['active']);
    }

    public function testNormalizesText(): void
    {
        [$data] = event_validate($this->input([
            'title' => "  Konferenz   2027 ",
            'description' => "Zeile 1\r\nZeile 2\n",
        ]));

        $this->assertSame('Konferenz 2027', $data['title']);
        $this->assertSame("Zeile 1\nZeile 2", $data['description']);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidFields(): array
    {
        return [
            'Titel leer' => ['title', '  '],
            'Ort leer' => ['location', ''],
            'Datum leer' => ['date', ''],
            'Datum ungültig' => ['date', '30.02.2027'],
            'Datum ISO statt deutsch' => ['date', '2027-05-01'],
            'Datum US-Format' => ['date', '05/01/2027'],
            'Zeitzone unbekannt' => ['timezone', 'Mars/Olympus'],
            'Zeitzone leer' => ['timezone', ''],
            'Teilnehmer 0' => ['max_participants', '0'],
            'Teilnehmer negativ' => ['max_participants', '-5'],
            'Teilnehmer Text' => ['max_participants', 'viele'],
            'Teilnehmer Dezimal' => ['max_participants', '1.5'],
            'E-Mail ungültig' => ['organizer_email', 'kein-at-zeichen'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function testRejectsInvalidField(string $field, string $value): void
    {
        [, $errors] = event_validate($this->input([$field => $value]));

        $this->assertArrayHasKey($field, $errors);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidDeadlines(): array
    {
        return [
            'Datum fehlt' => ['', '23:59'],
            'Uhrzeit fehlt' => ['15.04.2027', ''],
            'Uhrzeit 25 Uhr' => ['15.04.2027', '25:00'],
            'Uhrzeit AM/PM' => ['15.04.2027', '11:59 PM'],
            'Datum ungültig' => ['31.04.2027', '12:00'],
        ];
    }

    #[DataProvider('invalidDeadlines')]
    public function testRejectsInvalidDeadline(string $date, string $time): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline_date' => $date,
            'registration_deadline_time' => $time,
        ]));

        $this->assertArrayHasKey('registration_deadline', $errors);
    }

    public function testAcceptsDatesAndTimesWithoutLeadingZeros(): void
    {
        [$data, $errors] = event_validate($this->input([
            'date' => '1.5.2027',
            'registration_deadline_date' => '5.4.2027',
            'registration_deadline_time' => '9:05',
            'slots' => [['time' => '9:30', 'label' => 'Start']],
        ]));

        $this->assertSame([], $errors);
        $this->assertSame('2027-05-01', $data['date']);
        $this->assertSame('2027-04-05T09:05', $data['registration_deadline']);
        $this->assertSame('09:30', $data['slots'][0]['time']);
    }

    public function testOrganizerEmailIsOptional(): void
    {
        [, $errors] = event_validate($this->input(['organizer_email' => '']));

        $this->assertArrayNotHasKey('organizer_email', $errors);
    }

    public function testSortsSlotsByTimeAndIgnoresEmptyRows(): void
    {
        [$data, $errors] = event_validate($this->input([
            'slots' => [
                ['time' => '14:00', 'label' => 'Nachmittag'],
                ['time' => '', 'label' => ''],
                ['time' => '09:30', 'label' => '  Begrüßung  '],
            ],
        ]));

        $this->assertSame([], $errors);
        $this->assertSame([
            ['time' => '09:30', 'label' => 'Begrüßung', 'childcare' => []],
            ['time' => '14:00', 'label' => 'Nachmittag', 'childcare' => []],
        ], $data['slots']);
    }

    public function testRejectsIncompleteSlotRows(): void
    {
        [, $errors] = event_validate($this->input([
            'slots' => [
                ['time' => '10:00', 'label' => 'Ok'],
                ['time' => '11:00', 'label' => ''],
                ['time' => '9 Uhr', 'label' => 'Falsches Format'],
                ['time' => '2:00 PM', 'label' => 'AM/PM'],
            ],
        ]));

        $this->assertSame('Programmpunkt 2, 3, 4: Uhrzeit (HH:MM) und Bezeichnung angeben.', $errors['slots']);
    }

    public function testParsesChildcareGroups(): void
    {
        [$data, $errors] = event_validate($this->input([
            'slots' => [
                ['time' => '10:00', 'label' => 'Mit', 'childcare' => '1', 'childcare_groups' => ['group_4', 'group_2', 'group_5', 'x']],
                ['time' => '14:00', 'label' => 'Aus', 'childcare' => '', 'childcare_groups' => ['group_4']],
            ],
        ]));

        $this->assertSame([], $errors);
        $this->assertSame(['group_4', 'group_5'], $data['slots'][0]['childcare'], 'feste Reihenfolge, Jugend/Unbekanntes verworfen');
        $this->assertSame([], $data['slots'][1]['childcare'], 'ohne Schalter keine Betreuung');
    }

    public function testChildcareRequiresAtLeastOneGroup(): void
    {
        [, $errors] = event_validate($this->input([
            'slots' => [
                ['time' => '10:00', 'label' => 'A'],
                ['time' => '14:00', 'label' => 'B', 'childcare' => '1', 'childcare_groups' => ['group_2']],
            ],
        ]));

        $this->assertSame('Programmpunkt 2: Kindergruppen für die Kinderbetreuung auswählen.', $errors['slots']);
    }

    public function testParsesSelectedGroupsWithNames(): void
    {
        [$data, $errors] = event_validate($this->input([
            'person_groups' => ['group_3', 'group_1', 'x'],
            'group_names' => ['group_1' => '  Erwachsene  ', 'group_3' => 'Kinder 0–11', 'group_2' => 'nicht gewählt'],
        ]));

        $this->assertSame([], $errors);
        $this->assertSame(['group_1' => 'Erwachsene', 'group_3' => 'Kinder 0–11'], $data['person_groups']);
    }

    public function testRequiresAtLeastOneGroupAndNames(): void
    {
        [, $errors] = event_validate($this->input(['person_groups' => []]));
        $this->assertSame('Bitte mindestens eine Personengruppe auswählen.', $errors['person_groups']);

        [, $errors] = event_validate($this->input(['person_groups' => ['group_1'], 'group_names' => ['group_1' => ' ']]));
        $this->assertSame('Bitte für jede gewählte Personengruppe einen Namen angeben.', $errors['person_groups']);
    }

    public function testChildcareOnlyForSelectedKidsGroups(): void
    {
        [$data, $errors] = event_validate($this->input([
            'person_groups' => ['group_1', 'group_4'],
            'slots' => [
                ['time' => '10:00', 'label' => 'A', 'childcare' => '1', 'childcare_groups' => ['group_4', 'group_5']],
                ['time' => '14:00', 'label' => 'B', 'childcare' => '1', 'childcare_groups' => ['group_5']],
            ],
        ]));

        $this->assertSame(['group_4'], $data['slots'][0]['childcare'], 'nicht gewählte Kindergruppe verworfen');
        $this->assertSame('Programmpunkt 2: Kindergruppen für die Kinderbetreuung auswählen.', $errors['slots']);
    }

    public function testLockedGroupsKeepSelectionButAllowRenaming(): void
    {
        [$data, $errors] = event_validate($this->input([
            'person_groups' => ['group_1', 'group_2'],
            'group_names' => ['group_1' => 'Eltern', 'group_2' => 'Teens'],
        ]), null, ['group_1' => 'Erwachsene']);

        $this->assertSame([], $errors);
        $this->assertSame(['group_1' => 'Eltern'], $data['person_groups']);
    }

    public function testIgnoresMalformedSlotInput(): void
    {
        [$data, $errors] = event_validate($this->input(['slots' => 'kein Array']));

        $this->assertSame([], $data['slots']);
        $this->assertArrayNotHasKey('slots', $errors);
    }

    // --- Frist: spätestens Beginn des ersten Programmpunkts ---------------

    public function testDeadlineMayEqualStartOfFirstSlot(): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline_date' => '01.05.2027', 'registration_deadline_time' => '09:30',
            'slots' => [['time' => '14:00', 'label' => 'B'], ['time' => '09:30', 'label' => 'A']],
        ]));

        $this->assertArrayNotHasKey('registration_deadline', $errors);
    }

    public function testDeadlineAfterStartOfFirstSlotIsRejected(): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline_date' => '01.05.2027', 'registration_deadline_time' => '09:31',
            'slots' => [['time' => '14:00', 'label' => 'B'], ['time' => '09:30', 'label' => 'A']],
        ]));

        $this->assertSame(
            'Die Anmeldefrist darf spätestens am 01.05.2027, 09:30 Uhr enden (Beginn des ersten Programmpunkts).',
            $errors['registration_deadline']
        );
    }

    public function testDeadlineWithoutSlotsMayBeOnEventDay(): void
    {
        [, $errors] = event_validate($this->input(['registration_deadline_date' => '01.05.2027', 'registration_deadline_time' => '23:59', 'slots' => []]));

        $this->assertArrayNotHasKey('registration_deadline', $errors);
    }

    public function testDeadlineWithoutSlotsAfterEventDayIsRejected(): void
    {
        [, $errors] = event_validate($this->input(['registration_deadline_date' => '02.05.2027', 'registration_deadline_time' => '00:00', 'slots' => []]));

        $this->assertStringContainsString('(Veranstaltungstag)', $errors['registration_deadline']);
    }

    public function testDeadlineRuleSkippedWhenSlotsInvalid(): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline_date' => '01.06.2027', 'registration_deadline_time' => '00:00',
            'slots' => [['time' => 'x', 'label' => '']],
        ]));

        $this->assertArrayHasKey('slots', $errors);
        $this->assertArrayNotHasKey('registration_deadline', $errors);
    }

    // --- Gesperrter Ablauf -------------------------------------------------

    public function testLockedSlotsReplaceInputAndApplyToDeadlineRule(): void
    {
        $locked = [['time' => '08:00', 'label' => 'Frühstück']];

        [$data, $errors] = event_validate($this->input([
            'registration_deadline_date' => '01.05.2027', 'registration_deadline_time' => '09:00',
            'slots' => [['time' => '10:00', 'label' => 'Manipuliert']],
        ]), $locked);

        $this->assertSame($locked, $data['slots']);
        $this->assertArrayHasKey('registration_deadline', $errors);
    }
}
