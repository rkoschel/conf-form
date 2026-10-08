<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Fachregeln ohne DB: Kontingent, Status, Dubletten (SPEC §5.3, §7.2) */
final class RegistrationRulesTest extends TestCase
{
    public function testGroupSizeExcludesKidsUnderThree(): void
    {
        $counts = ['group_1' => 2, 'group_2' => 1, 'group_3' => 1, 'group_4' => 1, 'group_5' => 3];

        $this->assertSame(5, registration_group_size($counts));
        $this->assertSame(8, registration_person_count($counts));
    }

    public function testGroupSizeTreatsMissingGroupsAsZero(): void
    {
        $this->assertSame(2, registration_group_size(['group_1' => '2']));
    }

    public function testPreferredPlaceWithEnoughCapacityIsConfirmed(): void
    {
        $this->assertSame('confirmed', registration_decide_status(true, 95, 5, 100, 5));
    }

    public function testExactlyFillingTheQuotaIsConfirmed(): void
    {
        $this->assertSame('confirmed', registration_decide_status(true, 96, 4, 100, 4));
    }

    public function testPreferredPlaceOverQuotaGoesToWaitlist(): void
    {
        $this->assertSame('pending', registration_decide_status(true, 97, 4, 100, 4));
    }

    public function testOtherPlaceAlwaysGoesToWaitlist(): void
    {
        $this->assertSame('pending', registration_decide_status(false, 0, 1, 100, 1));
    }

    public function testOnlyBabiesDoNotUseQuota(): void
    {
        // Gruppengröße 0 (nur Kinder 0–2) passt auch bei vollem Kontingent
        $this->assertSame('confirmed', registration_decide_status(true, 100, 0, 100, 1));
    }

    public function testUpTo99PersonsAreConfirmed(): void
    {
        $this->assertSame('confirmed', registration_decide_status(true, 0, 99, 1000, 99));
    }

    public function testMoreThan99PersonsGoToWaitlistDespitePreferredPlaceAndCapacity(): void
    {
        $this->assertSame('pending', registration_decide_status(true, 0, 100, 1000, 100));
    }

    public function testKidsUnderThreeCountTowardsTheWaitlistThreshold(): void
    {
        // 95 im Kontingent + 5 Kinder 0–2 = 100 Personen
        $this->assertSame('pending', registration_decide_status(true, 0, 95, 1000, 100));
    }

    public function testPreferredPlaceMatchesNormalizedAndCaseInsensitive(): void
    {
        $places = ['Hamm', 'Bad Oeynhausen'];

        $this->assertTrue(registration_is_preferred_place('hamm', $places));
        $this->assertTrue(registration_is_preferred_place('  bad   oeynhausen ', $places));
        $this->assertTrue(registration_is_preferred_place('ÄÖÜ', ['äöü']));
        $this->assertFalse(registration_is_preferred_place('Hammm', $places));
        $this->assertFalse(registration_is_preferred_place('Hamm', []));
    }

    public function testDuplicatesByEmailOrNameCaseInsensitive(): void
    {
        $rows = [
            ['id' => 1, 'first_name' => 'Anna', 'last_name' => 'Muster', 'email' => 'anna@example.org'],
            ['id' => 2, 'first_name' => 'Ben', 'last_name' => 'Beispiel', 'email' => ' ANNA@example.org '],
            ['id' => 3, 'first_name' => ' anna', 'last_name' => 'MUSTER ', 'email' => null],
            ['id' => 4, 'first_name' => 'Carla', 'last_name' => 'Muster', 'email' => 'carla@example.org'],
            ['id' => 5, 'first_name' => 'Dora', 'last_name' => 'Dorf', 'email' => null],
            ['id' => 6, 'first_name' => 'Emil', 'last_name' => 'Ernst', 'email' => ''],
        ];

        $this->assertSame([1, 2, 3], registration_duplicate_ids($rows));
    }
}
