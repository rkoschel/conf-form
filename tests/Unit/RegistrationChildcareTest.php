<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Zählweise Programmpunkte und Kinderbetreuung (SPEC §5.4) */
final class RegistrationChildcareTest extends TestCase
{
    private const SLOTS = [
        ['id' => 1, 'time' => '10:00', 'label' => 'Mit Betreuung 0–6', 'childcare' => ['kids_0_2', 'kids_3_6']],
        ['id' => 2, 'time' => '14:00', 'label' => 'Ohne Betreuung', 'childcare' => []],
    ];

    /** Familie: 2 Erwachsene, 1 Kind 7–12, 2 Kinder 3–6, 1 Kind 0–2 */
    private function validate(array $overrides): array
    {
        [$data, $errors] = registration_validate($overrides + [
            'first_name' => 'Anna', 'last_name' => 'Muster', 'congregation' => 'Hamm', 'email' => 'anna@example.org',
            'adults' => '2', 'kids_7_12' => '1', 'kids_3_6' => '2', 'kids_0_2' => '1',
        ], self::SLOTS);
        $this->assertSame([], $errors);
        return $data['slots'];
    }

    public function testStandardAttendancePutsCoveredChildrenIntoChildcare(): void
    {
        $slots = $this->validate(['attend' => ['1' => '1', '2' => '1']]);

        $this->assertSame(
            ['adults' => 2, 'youth' => 0, 'kids_7_12' => 1, 'kids_3_6' => 0, 'kids_0_2' => 0,
             'childcare_kids_0_2' => 1, 'childcare_kids_3_6' => 2, 'childcare_kids_7_12' => 0],
            $slots[1],
            'betreute Gruppen in der Betreuung, 7–12 (nicht betreut) beim Programmpunkt'
        );
        $this->assertSame(1, $slots[2]['kids_0_2'], 'ohne Betreuungsangebot alle beim Programmpunkt');
        $this->assertSame(0, $slots[2]['childcare_kids_0_2']);
    }

    public function testSlotNotAttendedMeansNoChildcare(): void
    {
        $slots = $this->validate(['attend' => ['2' => '1']]);

        $this->assertSame(0, array_sum($slots[1]));
    }

    public function testCustomSplitRestGoesToChildcare(): void
    {
        $slots = $this->validate([
            'custom_split' => '1',
            'split' => ['1' => ['adults' => '2', 'kids_7_12' => '1', 'kids_3_6' => '1', 'kids_0_2' => '0']],
        ]);

        $this->assertSame(1, $slots[1]['kids_3_6'], 'eingetragenes Kind beim Programmpunkt');
        $this->assertSame(1, $slots[1]['childcare_kids_3_6'], 'Rest in der Betreuung');
        $this->assertSame(1, $slots[1]['childcare_kids_0_2']);
        $this->assertSame(0, $slots[1]['childcare_kids_7_12'], '7–12 nicht betreut');
    }

    public function testCustomSplitWithNobodyAtSlotMeansNoChildcare(): void
    {
        $slots = $this->validate([
            'custom_split' => '1',
            'split' => ['1' => ['adults' => '0', 'kids_7_12' => '0', 'kids_3_6' => '0', 'kids_0_2' => '0']],
        ]);

        $this->assertSame(0, $slots[1]['childcare_kids_3_6']);
        $this->assertSame(0, $slots[1]['childcare_kids_0_2']);
    }

    public function testChildrenOnlyRegistrationStillGetsChildcare(): void
    {
        $slots = $this->validate(['adults' => '0', 'kids_7_12' => '0', 'attend' => ['1' => '1']]);

        $this->assertSame(0, array_sum(array_intersect_key($slots[1], AGE_GROUPS)));
        $this->assertSame(2, $slots[1]['childcare_kids_3_6']);
    }
}
