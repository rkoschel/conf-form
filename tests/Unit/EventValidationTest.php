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
            'date' => '2027-05-01',
            'location' => 'Hamm',
            'description' => '',
            'registration_deadline' => '2027-04-15T23:59',
            'timezone' => 'Europe/Berlin',
            'max_participants' => '150',
            'organizer_name' => '',
            'organizer_email' => '',
            'active' => false,
            'slots' => [['time' => '10:00', 'label' => 'Begrüßung']],
            'places' => '',
        ];
    }

    public function testAcceptsValidInput(): void
    {
        [$data, $errors] = event_validate($this->input());

        $this->assertSame([], $errors);
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
            'Datum ungültig' => ['date', '2027-02-30'],
            'Datum falsches Format' => ['date', '01.05.2027'],
            'Frist ohne Uhrzeit' => ['registration_deadline', '2027-04-15'],
            'Frist ungültig' => ['registration_deadline', '2027-04-15T25:00'],
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
            ['time' => '09:30', 'label' => 'Begrüßung'],
            ['time' => '14:00', 'label' => 'Nachmittag'],
        ], $data['slots']);
    }

    public function testRejectsIncompleteSlotRows(): void
    {
        [, $errors] = event_validate($this->input([
            'slots' => [
                ['time' => '10:00', 'label' => 'Ok'],
                ['time' => '11:00', 'label' => ''],
                ['time' => '9 Uhr', 'label' => 'Falsches Format'],
            ],
        ]));

        $this->assertSame('Programmpunkt 2, 3: Uhrzeit (HH:MM) und Bezeichnung angeben.', $errors['slots']);
    }

    public function testIgnoresMalformedSlotInput(): void
    {
        [$data, $errors] = event_validate($this->input(['slots' => 'kein Array']));

        $this->assertSame([], $data['slots']);
        $this->assertArrayNotHasKey('slots', $errors);
    }

    public function testNormalizesAndDeduplicatesPlaces(): void
    {
        [$data] = event_validate($this->input([
            'places' => "Hamm\r\n  bad   Hamm \n\nHAMM\nBad Hamm\nUnna",
        ]));

        $this->assertSame(['Hamm', 'bad Hamm', 'Unna'], $data['places']);
    }

    // --- Frist: spätestens Beginn des ersten Programmpunkts ---------------

    public function testDeadlineMayEqualStartOfFirstSlot(): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline' => '2027-05-01T09:30',
            'slots' => [['time' => '14:00', 'label' => 'B'], ['time' => '09:30', 'label' => 'A']],
        ]));

        $this->assertArrayNotHasKey('registration_deadline', $errors);
    }

    public function testDeadlineAfterStartOfFirstSlotIsRejected(): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline' => '2027-05-01T09:31',
            'slots' => [['time' => '14:00', 'label' => 'B'], ['time' => '09:30', 'label' => 'A']],
        ]));

        $this->assertSame(
            'Die Anmeldefrist darf spätestens am 01.05.2027, 09:30 Uhr enden (Beginn des ersten Programmpunkts).',
            $errors['registration_deadline']
        );
    }

    public function testDeadlineWithoutSlotsMayBeOnEventDay(): void
    {
        [, $errors] = event_validate($this->input(['registration_deadline' => '2027-05-01T23:59', 'slots' => []]));

        $this->assertArrayNotHasKey('registration_deadline', $errors);
    }

    public function testDeadlineWithoutSlotsAfterEventDayIsRejected(): void
    {
        [, $errors] = event_validate($this->input(['registration_deadline' => '2027-05-02T00:00', 'slots' => []]));

        $this->assertStringContainsString('(Veranstaltungstag)', $errors['registration_deadline']);
    }

    public function testDeadlineRuleSkippedWhenSlotsInvalid(): void
    {
        [, $errors] = event_validate($this->input([
            'registration_deadline' => '2027-06-01T00:00',
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
            'registration_deadline' => '2027-05-01T09:00',
            'slots' => [['time' => '10:00', 'label' => 'Manipuliert']],
        ]), $locked);

        $this->assertSame($locked, $data['slots']);
        $this->assertArrayHasKey('registration_deadline', $errors);
    }
}
