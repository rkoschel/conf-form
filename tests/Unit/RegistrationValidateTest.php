<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Validierung des Anmeldeformulars (SPEC §5.1) */
final class RegistrationValidateTest extends TestCase
{
    private const SLOTS = [
        ['id' => 10, 'time' => '10:00', 'label' => 'Vortrag'],
        ['id' => 11, 'time' => '14:00', 'label' => 'Jugendstunde'],
    ];

    public function testValidInputIsNormalized(): void
    {
        [$data, $errors] = $this->validate([
            'first_name' => '  Anna ',
            'last_name' => 'Muster',
            'congregation' => '  Bad   Oeynhausen ',
            'email' => ' anna@example.org ',
            'phone' => '0123',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('Anna', $data['first_name']);
        $this->assertSame('Bad Oeynhausen', $data['congregation']);
        $this->assertSame('anna@example.org', $data['email']);
        $this->assertNull($data['phone'], 'Telefon nur bei „keine E-Mail“');
        $this->assertFalse($data['no_email']);
        $this->assertSame(2, $data['group_1']);
        $this->assertSame(0, $data['group_2']);
    }

    public function testRequiredFields(): void
    {
        [, $errors] = $this->validate(['first_name' => ' ', 'last_name' => '', 'congregation' => '', 'email' => '']);

        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertArrayHasKey('congregation', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testInvalidEmail(): void
    {
        [, $errors] = $this->validate(['email' => 'anna@']);

        $this->assertArrayHasKey('email', $errors);
    }

    public function testNoEmailRequiresPhone(): void
    {
        [, $errors] = $this->validate(['no_email' => '1', 'email' => '', 'phone' => ' ']);

        $this->assertArrayHasKey('phone', $errors);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testNoEmailDropsEmail(): void
    {
        [$data, $errors] = $this->validate(['no_email' => '1', 'email' => 'anna@example.org', 'phone' => '02381 12345']);

        $this->assertSame([], $errors);
        $this->assertTrue($data['no_email']);
        $this->assertNull($data['email']);
        $this->assertSame('02381 12345', $data['phone']);
    }

    public function testAtLeastOnePerson(): void
    {
        [, $errors] = $this->validate(['group_1' => '0']);

        $this->assertArrayHasKey('persons', $errors);
    }

    public function testBabyAloneCountsAsPerson(): void
    {
        [, $errors] = $this->validate(['group_1' => '0', 'group_5' => '1']);

        $this->assertArrayNotHasKey('persons', $errors);
    }

    public function testCountsMustBeNonNegativeIntegers(): void
    {
        foreach (['-1', '1.5', 'zwei', '1234567890'] as $value) {
            [, $errors] = $this->validate(['group_2' => $value]);
            $this->assertArrayHasKey('group_2', $errors, $value);
        }
        [$data, $errors] = $this->validate(['group_2' => '']);
        $this->assertArrayNotHasKey('group_2', $errors, 'leer = 0');
        $this->assertSame(0, $data['group_2']);
    }

    public function testNoFixedLimitPerGroup(): void
    {
        [$data, $errors] = $this->validate(['group_1' => '150']);

        $this->assertSame([], $errors);
        $this->assertSame(150, $data['group_1']);
    }

    public function testQuotaPersonsMayNotExceedCapacity(): void
    {
        [, $errors] = registration_validate($this->input(['group_1' => '8', 'group_2' => '3']), self::SLOTS, 10);

        $this->assertArrayHasKey('persons', $errors);
        $this->assertStringNotContainsString('10', $errors['persons'], 'Kapazität wird nicht verraten');
    }

    public function testCapacityCountsAllGroups(): void
    {
        [, $errors] = registration_validate($this->input(['group_1' => '5', 'group_5' => '5']), self::SLOTS, 10);
        $this->assertSame([], $errors, 'genau voll');

        [, $errors] = registration_validate($this->input(['group_1' => '10', 'group_5' => '1']), self::SLOTS, 10);
        $this->assertArrayHasKey('persons', $errors, 'auch die jüngste Gruppe zählt');
    }

    public function testMessageIsOptionalNormalizedAndLimited(): void
    {
        [$data, $errors] = $this->validate(['message' => "  Wir kommen etwas später.\r\nDanke!  "]);
        $this->assertSame([], $errors);
        $this->assertSame("Wir kommen etwas später.\nDanke!", $data['message']);

        [$data] = $this->validate([]);
        $this->assertSame('', $data['message']);

        [, $errors] = $this->validate(['message' => str_repeat('a', REGISTRATION_MESSAGE_MAX + 1)]);
        $this->assertSame('Bitte höchstens ' . REGISTRATION_MESSAGE_MAX . ' Zeichen.', $errors['message']);
    }

    public function testCustomSplitIgnoredWhenNotAllowed(): void
    {
        [$data, $errors] = registration_validate(
            $this->input(['custom_split' => '1', 'split' => ['10' => ['group_1' => '1']], 'attend' => ['10' => '1']]),
            self::SLOTS,
            null,
            null,
            false
        );

        $this->assertSame([], $errors);
        $this->assertFalse($data['custom_split']);
        $this->assertSame(2, $data['slots'][10]['group_1'], 'Ankreuzen gilt für die ganze Gruppe');
    }

    public function testGroupsNotSelectedForEventAreZero(): void
    {
        [$data, $errors] = registration_validate(
            $this->input(['group_1' => '2', 'group_2' => '3', 'group_5' => 'kaputt', 'attend' => ['10' => '1']]),
            self::SLOTS,
            null,
            ['group_1', 'group_4']
        );

        $this->assertSame([], $errors, 'Eingaben nicht gewählter Gruppen werden ignoriert');
        $this->assertSame([2, 0, 0], [$data['group_1'], $data['group_2'], $data['group_5']]);
        $this->assertSame(0, $data['slots'][10]['group_2']);
    }

    public function testWithoutCapacityNoUpperLimit(): void
    {
        [, $errors] = registration_validate($this->input(['group_1' => '5000']), self::SLOTS);

        $this->assertSame([], $errors);
    }

    public function testGroupAttendanceCopiesGroupCounts(): void
    {
        [$data, $errors] = $this->validate([
            'group_1' => '2', 'group_5' => '1',
            'attend' => ['10' => '1'],
        ]);

        $this->assertSame([], $errors);
        $this->assertFalse($data['custom_split']);
        $this->assertSame(
            ['group_1' => 2, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 1],
            array_intersect_key($data['slots'][10], PERSON_GROUPS)
        );
        $this->assertSame(0, $data['slots'][10]['childcare_group_5'], 'ohne Betreuungsangebot keine Betreuung');
        $this->assertSame(array_fill_keys(array_keys(PERSON_GROUPS), 0), array_intersect_key($data['slots'][11], PERSON_GROUPS));
    }

    public function testCustomSplitPerSlotAndGroup(): void
    {
        [$data, $errors] = $this->validate([
            'group_1' => '2', 'group_2' => '1',
            'custom_split' => '1',
            'attend' => ['10' => '1', '11' => '1'], // wird bei Aufteilung ignoriert
            'split' => [
                '10' => ['group_1' => '2', 'group_2' => '0'],
                '11' => ['group_1' => '1', 'group_2' => '1'],
            ],
        ]);

        $this->assertSame([], $errors);
        $this->assertTrue($data['custom_split']);
        $this->assertSame(0, $data['slots'][10]['group_2']);
        $this->assertSame(1, $data['slots'][11]['group_1']);
        $this->assertSame(0, $data['slots'][11]['group_4'], 'fehlende Felder = 0');
    }

    public function testSplitMayNotExceedGroupCount(): void
    {
        [, $errors] = $this->validate([
            'group_1' => '2',
            'custom_split' => '1',
            'split' => ['10' => ['group_1' => '3']],
        ]);

        $this->assertArrayHasKey('split', $errors);
    }

    public function testSplitRejectsInvalidNumbers(): void
    {
        [, $errors] = $this->validate([
            'custom_split' => '1',
            'split' => ['11' => ['group_1' => '-1']],
        ]);

        $this->assertArrayHasKey('split', $errors);
    }

    public function testUnknownSlotsAreIgnored(): void
    {
        [$data, $errors] = $this->validate(['attend' => ['99' => '1', '11' => '1'], 'split' => 'kaputt']);

        $this->assertSame([], $errors);
        $this->assertSame([10, 11], array_keys($data['slots']));
    }

    public function testAtLeastOneSlotIsRequired(): void
    {
        [, $errors] = $this->validate(['attend' => []]);
        $this->assertSame('Bitte mindestens einen Programmpunkt auswählen.', $errors['attend']);

        [, $errors] = $this->validate(['attend' => ['99' => '1']]);
        $this->assertArrayHasKey('attend', $errors, 'unbekannter Programmpunkt zählt nicht');

        [, $errors] = $this->validate([
            'attend' => [],
            'custom_split' => '1',
            'split' => ['10' => ['group_1' => '0'], '11' => ['group_1' => '0']],
        ]);
        $this->assertArrayHasKey('attend', $errors, 'Aufteilung überall 0');

        [, $errors] = $this->validate(['attend' => [], 'custom_split' => '1', 'split' => ['11' => ['group_1' => '1']]]);
        $this->assertArrayNotHasKey('attend', $errors);
    }

    public function testEventWithoutSlots(): void
    {
        [$data, $errors] = registration_validate($this->input([]), []);

        $this->assertSame([], $errors);
        $this->assertSame([], $data['slots']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $overrides): array
    {
        return registration_validate($this->input($overrides), self::SLOTS);
    }

    private function input(array $overrides): array
    {
        return $overrides + [
            'first_name' => 'Anna',
            'last_name' => 'Muster',
            'congregation' => 'Hamm',
            'email' => 'anna@example.org',
            'group_1' => '2',
            'attend' => ['10' => '1'],
        ];
    }
}
