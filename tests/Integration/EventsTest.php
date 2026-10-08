<?php
declare(strict_types=1);

final class EventsTest extends DbTestCase
{
    /** @return array<string, mixed> wie von event_validate() geliefert */
    private function data(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Konferenz',
            'date' => '2027-05-01',
            'location' => 'Hamm',
            'description' => '',
            'registration_deadline' => '2027-04-15T23:59',
            'timezone' => 'Europe/Berlin',
            'max_participants' => 100,
            'organizer_name' => '',
            'organizer_email' => '',
            'active' => false,
            'slots' => [['time' => '10:00', 'label' => 'Vortrag'], ['time' => '14:00', 'label' => 'Mittag']],
        ];
    }

    public function testSavesNewEventWithSlots(): void
    {
        $id = event_save(null, $this->data());

        $event = event_find($id);
        $this->assertSame('Konferenz', $event['title']);
        $this->assertSame(['10:00', '14:00'], array_column($event['slots'], 'time'));
    }

    public function testSavesAndLoadsChildcarePerSlot(): void
    {
        $id = event_save(null, $this->data(['slots' => [
            ['time' => '10:00', 'label' => 'Vortrag', 'childcare' => ['group_5', 'group_4']],
            ['time' => '14:00', 'label' => 'Mittag', 'childcare' => []],
        ]]));

        $slots = event_find($id)['slots'];

        $this->assertSame(['group_5', 'group_4'], $slots[0]['childcare']);
        $this->assertSame([], $slots[1]['childcare']);
    }

    public function testUpdateReplacesFieldsAndSlots(): void
    {
        $id = event_save(null, $this->data());

        event_save($id, $this->data([
            'title' => 'Neu',
            'slots' => [['time' => '09:00', 'label' => 'Start']],
        ]));

        $event = event_find($id);
        $this->assertSame('Neu', $event['title']);
        $this->assertSame(['Start'], array_column($event['slots'], 'label'));
        $this->assertSame(1, $this->rowCount('events'));
    }

    public function testSavingActiveEventDeactivatesOthers(): void
    {
        $first = event_save(null, $this->data(['active' => true]));
        $second = event_save(null, $this->data(['active' => true]));

        $this->assertSame(0, (int) event_find($first)['active']);
        $this->assertSame(1, (int) event_find($second)['active']);

        event_save($first, $this->data(['active' => true]));

        $this->assertSame(1, (int) event_find($first)['active']);
        $this->assertSame(0, (int) event_find($second)['active']);
    }

    public function testSetActiveSwitchesActiveEvent(): void
    {
        $first = event_save(null, $this->data(['active' => true]));
        $second = event_save(null, $this->data());

        event_set_active($second, true);
        $this->assertSame(0, (int) event_find($first)['active']);
        $this->assertSame(1, (int) event_find($second)['active']);

        event_set_active($second, false);
        $this->assertSame(0, (int) event_find($second)['active']);
    }

    public function testSlotsAreLockedOnceRegistrationsExist(): void
    {
        $id = event_save(null, $this->data());
        $this->insertRegistration($id);

        event_save($id, $this->data([
            'title' => 'Geändert',
            'slots' => [['time' => '08:00', 'label' => 'Manipuliert']],
        ]));

        $event = event_find($id);
        $this->assertSame('Geändert', $event['title'], 'Eckdaten bleiben änderbar');
        $this->assertSame(['Vortrag', 'Mittag'], array_column($event['slots'], 'label'), 'Ablauf bleibt unverändert');
    }

    public function testActiveEventIsNullWithoutActiveEvent(): void
    {
        event_save(null, $this->data());

        $this->assertNull(event_active());
    }

    public function testActiveEventIncludesSlots(): void
    {
        event_save(null, $this->data());
        $id = event_save(null, $this->data(['title' => 'Aktiv', 'active' => true]));

        $event = event_active();
        $this->assertSame($id, $event['id']);
        $this->assertSame(['Vortrag', 'Mittag'], array_column($event['slots'], 'label'));
    }

    public function testListIncludesRegistrationCount(): void
    {
        $withRegistrations = event_save(null, $this->data(['date' => '2027-06-01']));
        event_save(null, $this->data(['date' => '2026-06-01']));
        $this->insertRegistration($withRegistrations);
        $this->insertRegistration($withRegistrations);

        $list = event_list();

        $this->assertSame([$withRegistrations], [$list[0]['id']], 'neueste zuerst');
        $this->assertSame([2, 0], array_map('intval', array_column($list, 'registration_count')));
    }

    public function testDeleteRemovesEventAndRegistrations(): void
    {
        $id = event_save(null, $this->data());
        $this->insertRegistration($id);

        event_delete($id);

        $this->assertNull(event_find($id));
        $this->assertSame(0, $this->rowCount('registrations'));
        $this->assertSame(0, $this->rowCount('event_slots'));
    }

    private function insertRegistration(int $eventId): void
    {
        db()->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, now_utc(), 'Max', 'Muster', 'Hamm', 'pending', bin2hex(random_bytes(32))]);
    }
}
