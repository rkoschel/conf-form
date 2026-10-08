<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Zählweise Programmpunkte und Kinderbetreuung (SPEC §5.4) */
final class RegistrationChildcareTest extends TestCase
{
    private const SLOTS = [
        ['id' => 1, 'time' => '10:00', 'label' => 'Mit Betreuung 0–6', 'childcare' => ['group_5', 'group_4']],
        ['id' => 2, 'time' => '14:00', 'label' => 'Ohne Betreuung', 'childcare' => []],
    ];

    /** Familie: 2 Erwachsene, 1 Kind 7–12, 2 Kinder 3–6, 1 Kind 0–2 */
    private function validate(array $overrides): array
    {
        [$data, $errors] = registration_validate($overrides + [
            'first_name' => 'Anna', 'last_name' => 'Muster', 'congregation' => 'Hamm', 'email' => 'anna@example.org',
            'group_1' => '2', 'group_3' => '1', 'group_4' => '2', 'group_5' => '1',
        ], self::SLOTS);
        $this->assertSame([], $errors);
        return $data['slots'];
    }

    public function testStandardAttendancePutsCoveredChildrenIntoChildcare(): void
    {
        $slots = $this->validate(['attend' => ['1' => '1', '2' => '1']]);

        $this->assertSame(
            ['group_1' => 2, 'group_2' => 0, 'group_3' => 1, 'group_4' => 0, 'group_5' => 0,
             'childcare_group_3' => 0, 'childcare_group_4' => 2, 'childcare_group_5' => 1],
            $slots[1],
            'betreute Gruppen in der Betreuung, 7–12 (nicht betreut) beim Programmpunkt'
        );
        $this->assertSame(1, $slots[2]['group_5'], 'ohne Betreuungsangebot alle beim Programmpunkt');
        $this->assertSame(0, $slots[2]['childcare_group_5']);
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
            'split' => ['1' => ['group_1' => '2', 'group_3' => '1', 'group_4' => '1', 'group_5' => '0']],
        ]);

        $this->assertSame(1, $slots[1]['group_4'], 'eingetragenes Kind beim Programmpunkt');
        $this->assertSame(1, $slots[1]['childcare_group_4'], 'Rest in der Betreuung');
        $this->assertSame(1, $slots[1]['childcare_group_5']);
        $this->assertSame(0, $slots[1]['childcare_group_3'], '7–12 nicht betreut');
    }

    public function testCustomSplitWithNobodyAtSlotMeansNoChildcare(): void
    {
        $slots = $this->validate([
            'custom_split' => '1',
            'split' => ['1' => ['group_1' => '0', 'group_3' => '0', 'group_4' => '0', 'group_5' => '0']],
        ]);

        $this->assertSame(0, $slots[1]['childcare_group_4']);
        $this->assertSame(0, $slots[1]['childcare_group_5']);
    }

    public function testChildrenOnlyRegistrationStillGetsChildcare(): void
    {
        $slots = $this->validate(['group_1' => '0', 'group_3' => '0', 'attend' => ['1' => '1']]);

        $this->assertSame(0, array_sum(array_intersect_key($slots[1], PERSON_GROUPS)));
        $this->assertSame(2, $slots[1]['childcare_group_4']);
    }
}
