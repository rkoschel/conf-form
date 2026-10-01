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
        $this->assertSame(2, $data['adults']);
        $this->assertSame(0, $data['youth']);
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
        [, $errors] = $this->validate(['adults' => '0']);

        $this->assertArrayHasKey('persons', $errors);
    }

    public function testBabyAloneCountsAsPerson(): void
    {
        [, $errors] = $this->validate(['adults' => '0', 'kids_0_2' => '1']);

        $this->assertArrayNotHasKey('persons', $errors);
    }

    public function testCountsMustBeNonNegativeIntegers(): void
    {
        foreach (['-1', '1.5', 'zwei', '1234567890'] as $value) {
            [, $errors] = $this->validate(['youth' => $value]);
            $this->assertArrayHasKey('youth', $errors, $value);
        }
        [$data, $errors] = $this->validate(['youth' => '']);
        $this->assertArrayNotHasKey('youth', $errors, 'leer = 0');
        $this->assertSame(0, $data['youth']);
    }

    public function testNoFixedLimitPerGroup(): void
    {
        [$data, $errors] = $this->validate(['adults' => '150']);

        $this->assertSame([], $errors);
        $this->assertSame(150, $data['adults']);
    }

    public function testQuotaPersonsMayNotExceedCapacity(): void
    {
        [, $errors] = registration_validate($this->input(['adults' => '8', 'youth' => '3']), self::SLOTS, 10);

        $this->assertArrayHasKey('persons', $errors);
        $this->assertStringNotContainsString('10', $errors['persons'], 'Kapazität wird nicht verraten');
    }

    public function testCapacityExactlyReachedAndBabiesNotLimited(): void
    {
        [, $errors] = registration_validate($this->input(['adults' => '10', 'kids_0_2' => '5']), self::SLOTS, 10);

        $this->assertSame([], $errors);
    }

    public function testWithoutCapacityNoUpperLimit(): void
    {
        [, $errors] = registration_validate($this->input(['adults' => '5000']), self::SLOTS);

        $this->assertSame([], $errors);
    }

    public function testGroupAttendanceCopiesGroupCounts(): void
    {
        [$data, $errors] = $this->validate([
            'adults' => '2', 'kids_0_2' => '1',
            'attend' => ['10' => '1'],
        ]);

        $this->assertSame([], $errors);
        $this->assertFalse($data['custom_split']);
        $this->assertSame(
            ['adults' => 2, 'youth' => 0, 'kids_7_12' => 0, 'kids_3_6' => 0, 'kids_0_2' => 1],
            $data['slots'][10]
        );
        $this->assertSame(array_fill_keys(array_keys(AGE_GROUPS), 0), $data['slots'][11]);
    }

    public function testCustomSplitPerSlotAndGroup(): void
    {
        [$data, $errors] = $this->validate([
            'adults' => '2', 'youth' => '1',
            'custom_split' => '1',
            'attend' => ['10' => '1', '11' => '1'], // wird bei Aufteilung ignoriert
            'split' => [
                '10' => ['adults' => '2', 'youth' => '0'],
                '11' => ['adults' => '1', 'youth' => '1'],
            ],
        ]);

        $this->assertSame([], $errors);
        $this->assertTrue($data['custom_split']);
        $this->assertSame(0, $data['slots'][10]['youth']);
        $this->assertSame(1, $data['slots'][11]['adults']);
        $this->assertSame(0, $data['slots'][11]['kids_3_6'], 'fehlende Felder = 0');
    }

    public function testSplitMayNotExceedGroupCount(): void
    {
        [, $errors] = $this->validate([
            'adults' => '2',
            'custom_split' => '1',
            'split' => ['10' => ['adults' => '3']],
        ]);

        $this->assertArrayHasKey('split', $errors);
    }

    public function testSplitRejectsInvalidNumbers(): void
    {
        [, $errors] = $this->validate([
            'custom_split' => '1',
            'split' => ['11' => ['adults' => '-1']],
        ]);

        $this->assertArrayHasKey('split', $errors);
    }

    public function testUnknownSlotsAreIgnored(): void
    {
        [$data, $errors] = $this->validate(['attend' => ['99' => '1'], 'split' => 'kaputt']);

        $this->assertSame([], $errors);
        $this->assertSame([10, 11], array_keys($data['slots']));
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
            'adults' => '2',
        ];
    }
}
