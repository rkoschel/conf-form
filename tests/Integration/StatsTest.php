<?php
declare(strict_types=1);

final class StatsTest extends DbTestCase
{
    private int $eventId;
    /** @var list<int> */
    private array $slotIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventId = $this->createEvent(['max_participants' => 10]);
        foreach ([['14:00', 'Nachmittag', 1], ['10:00', 'Vormittag', 0]] as [$time, $label, $sort]) {
            db()->prepare('INSERT INTO event_slots (event_id, time, label, sort) VALUES (?, ?, ?, ?)')
                ->execute([$this->eventId, $time, $label, $sort]);
            $this->slotIds[$label] = (int) db()->lastInsertId();
        }
    }

    public function testEmptyEventHasZeroStats(): void
    {
        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame(['registrations' => 0, 'people' => 0], $stats['by_status']['pending']);
        $this->assertSame(array_keys(STATUS_LABELS), array_keys($stats['by_status']));
        $this->assertSame(0, $stats['quota_used']);
        $this->assertSame(0, $stats['quota_pending']);
        $this->assertSame(10, $stats['quota_max']);
        $this->assertSame(array_keys(PERSON_GROUPS), array_keys($stats['groups']), 'ohne Auswahl: alle Gruppen');
        $this->assertSame(['name' => 'Erwachsene', 'confirmed' => 0, 'pending' => 0], $stats['groups']['group_1']);
        $this->assertSame(['Vormittag', 'Nachmittag'], array_column($stats['slots'], 'label'));
        $this->assertSame([0, 0], array_column($stats['slots'], 'total'));
    }

    public function testCountsByStatusIncludingToddlers(): void
    {
        $this->registration('confirmed', ['group_1' => 2, 'group_5' => 1]);
        $this->registration('confirmed', ['group_1' => 1, 'group_2' => 1]);
        $this->registration('pending', ['group_1' => 3]);
        $this->registration('cancelled', ['group_1' => 1, 'group_4' => 2]);

        $byStatus = stats_for_event(event_find($this->eventId))['by_status'];

        $this->assertSame(['registrations' => 2, 'people' => 5], $byStatus['confirmed']);
        $this->assertSame(['registrations' => 1, 'people' => 3], $byStatus['pending']);
        $this->assertSame(['registrations' => 1, 'people' => 3], $byStatus['cancelled']);
        $this->assertSame(['registrations' => 0, 'people' => 0], $byStatus['rejected']);
    }

    public function testQuotaCountsAllGroupsOfConfirmed(): void
    {
        $this->registration('confirmed', ['group_1' => 2, 'group_2' => 1, 'group_3' => 1, 'group_4' => 1, 'group_5' => 2]);
        $this->registration('pending', ['group_1' => 4]);
        $this->registration('rejected', ['group_1' => 4]);

        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame(7, $stats['quota_used']);
        $this->assertSame(['name' => 'Kindergruppe 3', 'confirmed' => 2, 'pending' => 0], $stats['groups']['group_5']);
        $this->assertSame(['name' => 'Erwachsene', 'confirmed' => 2, 'pending' => 4], $stats['groups']['group_1']);
    }

    public function testGroupsFollowEventSelectionAndNames(): void
    {
        db()->prepare('UPDATE events SET person_groups = ? WHERE id = ?')
            ->execute(['{"group_1":"Eltern","group_4":"Kinder 3–6"}', $this->eventId]);
        $this->registration('confirmed', ['group_1' => 2, 'group_4' => 1]);
        $this->registration('pending', ['group_4' => 3]);

        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame([
            'group_1' => ['name' => 'Eltern', 'confirmed' => 2, 'pending' => 0],
            'group_4' => ['name' => 'Kinder 3–6', 'confirmed' => 1, 'pending' => 3],
        ], $stats['groups']);
        $this->assertSame(['group_1', 'group_4'], array_keys($stats['slots'][0]['groups']), 'Programmpunkte nur mit gewählten Gruppen');
    }

    public function testPendingQuotaCountsAllGroupsOfPending(): void
    {
        $this->registration('pending', ['group_1' => 2, 'group_4' => 1, 'group_5' => 3]);
        $this->registration('pending', ['group_2' => 1]);
        $this->registration('confirmed', ['group_1' => 5]);
        $this->registration('cancelled', ['group_1' => 4]);
        $this->registration('rejected', ['group_1' => 4]);

        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame(7, $stats['quota_pending']);
        $this->assertSame(5, $stats['quota_used']);
    }

    public function testCountsPeoplePerPlaceForConfirmedAndPending(): void
    {
        preferred_places_set(['Hamm']);
        $this->registration('confirmed', ['group_1' => 2, 'group_5' => 1], congregation: 'Hamm');
        $this->registration('pending', ['group_1' => 1], congregation: 'hamm');
        $this->registration('pending', ['group_1' => 5], congregation: 'Unna');
        $this->registration('cancelled', ['group_1' => 9], congregation: 'Soest');
        $this->registration('rejected', ['group_1' => 9], congregation: 'Hamm');

        $places = stats_for_event(event_find($this->eventId))['places'];

        $this->assertSame([
            ['name' => 'Unna', 'confirmed' => 0, 'pending' => 5, 'preferred' => false],
            ['name' => 'Hamm', 'confirmed' => 3, 'pending' => 1, 'preferred' => true],
        ], $places, 'meiste zuerst, Schreibweisen zusammengefasst, ohne storniert/abgelehnt');
    }

    public function testSlotAttendanceCountsOnlyConfirmed(): void
    {
        $confirmed = $this->registration('confirmed', ['group_1' => 2, 'group_5' => 1]);
        $this->attend($confirmed, 'Vormittag', ['group_1' => 2, 'group_5' => 1]);
        $this->attend($confirmed, 'Nachmittag', ['group_1' => 1]);

        $other = $this->registration('confirmed', ['group_2' => 3]);
        $this->attend($other, 'Nachmittag', ['group_2' => 3]);

        $pending = $this->registration('pending', ['group_1' => 5]);
        $this->attend($pending, 'Vormittag', ['group_1' => 5]);

        $slots = stats_for_event(event_find($this->eventId))['slots'];

        $this->assertSame('Vormittag', $slots[0]['label']);
        $this->assertSame(['group_1' => 2, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 1], $slots[0]['groups']);
        $this->assertSame(3, $slots[0]['total']);
        $this->assertSame(4, $slots[1]['total']);
        $this->assertSame(3, $slots[1]['groups']['group_2']);
    }

    public function testSlotStatsIncludeChildcareSeparately(): void
    {
        db()->prepare("UPDATE event_slots SET childcare = 'group_4,group_5' WHERE id = ?")
            ->execute([$this->slotIds['Vormittag']]);
        $confirmed = $this->registration('confirmed', ['group_1' => 2, 'group_4' => 2, 'group_5' => 1]);
        $this->attend($confirmed, 'Vormittag', ['group_1' => 2, 'group_4' => 1, 'childcare_group_4' => 1, 'childcare_group_5' => 1]);
        $pending = $this->registration('pending', ['group_4' => 4]);
        $this->attend($pending, 'Vormittag', ['childcare_group_4' => 4]);

        $slots = stats_for_event(event_find($this->eventId))['slots'];

        $this->assertSame(['group_4', 'group_5'], $slots[0]['childcare_groups']);
        $this->assertSame(['group_4' => 1, 'group_5' => 1], $slots[0]['childcare'], 'nur bestätigte, nur betreute Gruppen');
        $this->assertSame(2, $slots[0]['childcare_total']);
        $this->assertSame(3, $slots[0]['total'], 'Summe ohne Kinder in der Betreuung');
        $this->assertSame([], $slots[1]['childcare_groups']);
        $this->assertSame(0, $slots[1]['childcare_total']);
    }

    public function testIgnoresOtherEvents(): void
    {
        $other = $this->createEvent();
        $this->registration('confirmed', ['group_1' => 3], $other);

        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame(0, $stats['quota_used']);
        $this->assertSame(0, $stats['by_status']['confirmed']['registrations']);
    }

    /** @param array<string, int> $people */
    private function registration(string $status, array $people, ?int $eventId = null, string $congregation = 'Hamm'): int
    {
        $fields = $people + [
            'event_id' => $eventId ?? $this->eventId,
            'created_at' => now_utc(),
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'congregation' => $congregation,
            'status' => $status,
            'cancel_token' => bin2hex(random_bytes(32)),
        ];
        $columns = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(fn ($k) => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO registrations ($columns) VALUES ($params)")->execute($fields);
        return (int) db()->lastInsertId();
    }

    /** @param array<string, int> $people */
    private function attend(int $registrationId, string $slot, array $people): void
    {
        $fields = $people + ['registration_id' => $registrationId, 'slot_id' => $this->slotIds[$slot]];
        $columns = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(fn ($k) => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO registration_slots ($columns) VALUES ($params)")->execute($fields);
    }
}
