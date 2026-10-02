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
        $this->assertSame(array_fill_keys(array_keys(AGE_GROUPS), 0), $stats['age_groups']);
        $this->assertSame(['Vormittag', 'Nachmittag'], array_column($stats['slots'], 'label'));
        $this->assertSame([0, 0], array_column($stats['slots'], 'total'));
    }

    public function testCountsByStatusIncludingToddlers(): void
    {
        $this->registration('confirmed', ['adults' => 2, 'kids_0_2' => 1]);
        $this->registration('confirmed', ['adults' => 1, 'youth' => 1]);
        $this->registration('pending', ['adults' => 3]);
        $this->registration('cancelled', ['adults' => 1, 'kids_3_6' => 2]);

        $byStatus = stats_for_event(event_find($this->eventId))['by_status'];

        $this->assertSame(['registrations' => 2, 'people' => 5], $byStatus['confirmed']);
        $this->assertSame(['registrations' => 1, 'people' => 3], $byStatus['pending']);
        $this->assertSame(['registrations' => 1, 'people' => 3], $byStatus['cancelled']);
        $this->assertSame(['registrations' => 0, 'people' => 0], $byStatus['rejected']);
    }

    public function testQuotaCountsOnlyConfirmedWithoutToddlers(): void
    {
        $this->registration('confirmed', ['adults' => 2, 'youth' => 1, 'kids_7_12' => 1, 'kids_3_6' => 1, 'kids_0_2' => 2]);
        $this->registration('pending', ['adults' => 4]);
        $this->registration('rejected', ['adults' => 4]);

        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame(5, $stats['quota_used']);
        $this->assertSame(
            ['adults' => 2, 'youth' => 1, 'kids_7_12' => 1, 'kids_3_6' => 1, 'kids_0_2' => 2],
            $stats['age_groups']
        );
    }

    public function testPendingQuotaCountsOnlyPendingWithoutToddlers(): void
    {
        $this->registration('pending', ['adults' => 2, 'kids_3_6' => 1, 'kids_0_2' => 3]);
        $this->registration('pending', ['youth' => 1]);
        $this->registration('confirmed', ['adults' => 5]);
        $this->registration('cancelled', ['adults' => 4]);
        $this->registration('rejected', ['adults' => 4]);

        $stats = stats_for_event(event_find($this->eventId));

        $this->assertSame(4, $stats['quota_pending']);
        $this->assertSame(5, $stats['quota_used']);
    }

    public function testCountsPeoplePerPlaceForConfirmedAndPending(): void
    {
        db()->prepare('INSERT INTO preferred_places (event_id, name) VALUES (?, ?)')->execute([$this->eventId, 'Hamm']);
        $this->registration('confirmed', ['adults' => 2, 'kids_0_2' => 1], congregation: 'Hamm');
        $this->registration('pending', ['adults' => 1], congregation: 'hamm');
        $this->registration('pending', ['adults' => 5], congregation: 'Unna');
        $this->registration('cancelled', ['adults' => 9], congregation: 'Soest');
        $this->registration('rejected', ['adults' => 9], congregation: 'Hamm');

        $places = stats_for_event(event_find($this->eventId))['places'];

        $this->assertSame([
            ['name' => 'Unna', 'confirmed' => 0, 'pending' => 5, 'preferred' => false],
            ['name' => 'Hamm', 'confirmed' => 3, 'pending' => 1, 'preferred' => true],
        ], $places, 'meiste zuerst, Schreibweisen zusammengefasst, ohne storniert/abgelehnt');
    }

    public function testSlotAttendanceCountsOnlyConfirmed(): void
    {
        $confirmed = $this->registration('confirmed', ['adults' => 2, 'kids_0_2' => 1]);
        $this->attend($confirmed, 'Vormittag', ['adults' => 2, 'kids_0_2' => 1]);
        $this->attend($confirmed, 'Nachmittag', ['adults' => 1]);

        $other = $this->registration('confirmed', ['youth' => 3]);
        $this->attend($other, 'Nachmittag', ['youth' => 3]);

        $pending = $this->registration('pending', ['adults' => 5]);
        $this->attend($pending, 'Vormittag', ['adults' => 5]);

        $slots = stats_for_event(event_find($this->eventId))['slots'];

        $this->assertSame('Vormittag', $slots[0]['label']);
        $this->assertSame(['adults' => 2, 'youth' => 0, 'kids_7_12' => 0, 'kids_3_6' => 0, 'kids_0_2' => 1], $slots[0]['groups']);
        $this->assertSame(3, $slots[0]['total']);
        $this->assertSame(4, $slots[1]['total']);
        $this->assertSame(3, $slots[1]['groups']['youth']);
    }

    public function testSlotStatsIncludeChildcareSeparately(): void
    {
        db()->prepare("UPDATE event_slots SET childcare = 'kids_3_6,kids_0_2' WHERE id = ?")
            ->execute([$this->slotIds['Vormittag']]);
        $confirmed = $this->registration('confirmed', ['adults' => 2, 'kids_3_6' => 2, 'kids_0_2' => 1]);
        $this->attend($confirmed, 'Vormittag', ['adults' => 2, 'kids_3_6' => 1, 'childcare_kids_3_6' => 1, 'childcare_kids_0_2' => 1]);
        $pending = $this->registration('pending', ['kids_3_6' => 4]);
        $this->attend($pending, 'Vormittag', ['childcare_kids_3_6' => 4]);

        $slots = stats_for_event(event_find($this->eventId))['slots'];

        $this->assertSame(['kids_0_2', 'kids_3_6'], $slots[0]['childcare_groups']);
        $this->assertSame(['kids_0_2' => 1, 'kids_3_6' => 1], $slots[0]['childcare'], 'nur bestätigte, nur betreute Gruppen');
        $this->assertSame(2, $slots[0]['childcare_total']);
        $this->assertSame(3, $slots[0]['total'], 'Summe ohne Kinder in der Betreuung');
        $this->assertSame([], $slots[1]['childcare_groups']);
        $this->assertSame(0, $slots[1]['childcare_total']);
    }

    public function testIgnoresOtherEvents(): void
    {
        $other = $this->createEvent();
        $this->registration('confirmed', ['adults' => 3], $other);

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
