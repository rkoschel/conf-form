<?php
declare(strict_types=1);

/** Anmeldungen mit echter DB (SPEC §5.3, §6, §7.2–7.4, §7.6) */
final class RegistrationsTest extends DbTestCase
{
    private array $event;

    protected function setUp(): void
    {
        parent::setUp();
        $id = $this->createEvent(['max_participants' => 10, 'active' => 1]);
        db()->exec("INSERT INTO event_slots (event_id, time, label, sort) VALUES ($id, '10:00', 'Vortrag', 0)");
        db()->exec("INSERT INTO event_slots (event_id, time, label, sort) VALUES ($id, '14:00', 'Mittag', 1)");
        preferred_places_set(['Hamm']);
        $this->event = event_find($id);
    }

    public function testCreateConfirmsPreferredPlaceWithinQuota(): void
    {
        $registration = $this->register(['group_1' => 2, 'group_5' => 1]);

        $this->assertSame('confirmed', $registration['status']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $registration['cancel_token']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $registration['created_at']);
        $this->assertSame(3, registration_occupied($this->event['id']), 'alle Gruppen zählen');
    }

    public function testCreatePutsOtherPlaceOnWaitlist(): void
    {
        $registration = $this->register(['congregation' => 'Dortmund']);

        $this->assertSame('pending', $registration['status']);
        $this->assertSame(0, registration_occupied($this->event['id']));
    }

    public function testPreferredPlaceMatchesCaseInsensitive(): void
    {
        $this->assertSame('confirmed', $this->register(['congregation' => 'hamm'])['status']);
    }

    public function testQuotaFullGoesToWaitlistAndAllGroupsCount(): void
    {
        $this->register(['group_1' => 4, 'group_5' => 4]);
        $this->assertSame('confirmed', $this->register(['group_1' => 2])['status'], 'genau voll');
        $this->assertSame('pending', $this->register(['group_1' => 1])['status']);
        $this->assertSame('pending', $this->register(['group_1' => 0, 'group_5' => 1])['status'], 'auch die jüngste Gruppe braucht einen Platz');
        $this->assertSame(10, registration_occupied($this->event['id']));
    }

    public function testMoreThan99PersonsGoToWaitlist(): void
    {
        db()->exec('UPDATE events SET max_participants = 1000');
        $this->event = event_find($this->event['id']);

        $this->assertSame('confirmed', $this->register(['group_1' => 98, 'group_5' => 1])['status']);
        $this->assertSame('pending', $this->register(['group_1' => 99, 'group_5' => 1])['status']);
    }

    public function testOnlyConfirmedCountAsOccupied(): void
    {
        $a = $this->register(['group_1' => 3]);
        $this->register(['group_1' => 4, 'congregation' => 'Dortmund']);
        $c = $this->register(['group_1' => 2]);
        registration_cancel($c['id']);

        $this->assertSame(3, registration_occupied($this->event['id']));
        $this->assertSame(0, registration_occupied($this->event['id'], $a['id']), 'ohne sich selbst');
    }

    public function testOccupiedCountsOnlyOwnEvent(): void
    {
        $this->register(['group_1' => 3]);
        $otherId = $this->createEvent();

        $this->assertSame(0, registration_occupied($otherId));
    }

    public function testCreateStoresSlotCounts(): void
    {
        $slots = $this->event['slots'];
        $registration = $this->register(['group_1' => 2], [
            $slots[0]['id'] => ['group_1' => 2, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 0],
            $slots[1]['id'] => ['group_1' => 1, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 0],
        ]);

        $stored = registration_slot_counts($registration['id']);
        $this->assertSame(2, $stored[$slots[0]['id']]['group_1']);
        $this->assertSame(1, $stored[$slots[1]['id']]['group_1']);
    }

    public function testCreateWithoutEmailStoresPhone(): void
    {
        $registration = $this->register(['email' => null, 'phone' => '0123', 'no_email' => true]);

        $this->assertNull($registration['email']);
        $this->assertSame('0123', $registration['phone']);
        $this->assertSame(1, $registration['no_email']);
    }

    public function testFindByToken(): void
    {
        $registration = $this->register();

        $this->assertSame($registration['id'], registration_find_by_token($registration['cancel_token'])['id']);
        $this->assertNull(registration_find_by_token('nicht-vorhanden'));
        $this->assertNull(registration_find_by_token(''));
    }

    public function testCancelOnlyFromPendingOrConfirmed(): void
    {
        $confirmed = $this->register();
        $pending = $this->register(['congregation' => 'Dortmund']);
        $rejected = $this->register();
        registration_set_status($rejected['id'], 'rejected');

        $this->assertTrue(registration_cancel($confirmed['id']));
        $this->assertTrue(registration_cancel($pending['id']));
        $this->assertFalse(registration_cancel($confirmed['id']), 'schon storniert');
        $this->assertFalse(registration_cancel($rejected['id']));
        $this->assertSame('cancelled', registration_find($confirmed['id'])['status']);
        $this->assertSame('rejected', registration_find($rejected['id'])['status']);
    }

    public function testSetStatusRejectsUnknownStatus(): void
    {
        $registration = $this->register();

        $this->expectException(InvalidArgumentException::class);
        registration_set_status($registration['id'], 'bestaetigt');
    }

    public function testExceedsQuotaForAdminWarning(): void
    {
        $a = $this->register(['group_1' => 6]);
        $b = $this->register(['group_1' => 4, 'congregation' => 'Dortmund']);

        $this->assertFalse(registration_exceeds_quota($this->event, $b));
        $this->register(['group_1' => 1]);
        $this->assertTrue(registration_exceeds_quota($this->event, $b));
        // eine bereits bestätigte Anmeldung zählt nicht doppelt
        $this->assertFalse(registration_exceeds_quota($this->event, $a));
        $this->assertTrue(registration_exceeds_quota($this->event, ['group_1' => 10] + $a));
    }

    public function testUpdateChangesFieldsSlotsAndStatusWithoutMail(): void
    {
        $registration = $this->register(['group_1' => 2]);
        $slotId = $this->event['slots'][0]['id'];

        registration_update($registration['id'], $this->data([
            'first_name' => 'Anne',
            'group_1' => 3,
            'custom_split' => true,
            'slots' => [$slotId => ['group_1' => 1, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 0]],
        ]), 'pending');

        $updated = registration_find($registration['id']);
        $this->assertSame('Anne', $updated['first_name']);
        $this->assertSame(3, $updated['group_1']);
        $this->assertSame(1, $updated['custom_split']);
        $this->assertSame('pending', $updated['status']);
        $this->assertSame($registration['cancel_token'], $updated['cancel_token']);
        $this->assertSame([$slotId], array_keys(registration_slot_counts($registration['id'])));
        $this->assertSame(0, $this->rowCount('mail_log'));
    }

    public function testDeleteRemovesSlotsAndMailLog(): void
    {
        $registration = $this->register([], [
            $this->event['slots'][0]['id'] => ['group_1' => 2, 'group_2' => 0, 'group_3' => 0, 'group_4' => 0, 'group_5' => 0],
        ]);
        db()->exec("INSERT INTO mail_log (registration_id, type, sent_at, success)
                    VALUES ({$registration['id']}, 'received_confirmed', '2027-01-01T00:00:00Z', 1)");

        registration_delete($registration['id']);

        $this->assertNull(registration_find($registration['id']));
        $this->assertSame(0, $this->rowCount('registration_slots'));
        $this->assertSame(0, $this->rowCount('mail_log'));
    }

    public function testListFiltersByStatusAndSearches(): void
    {
        $this->register(['first_name' => 'Anna', 'last_name' => 'Muster', 'email' => 'anna@example.org', 'group_5' => 1]);
        $this->register(['first_name' => 'Ben', 'last_name' => 'Beispiel', 'congregation' => 'Dortmund', 'email' => 'ben@example.org']);
        $this->register(['first_name' => 'Carla', 'last_name' => 'Weg', 'email' => 'carla@mail.example']);

        $names = fn (array $rows) => array_column($rows, 'first_name');
        $id = $this->event['id'];

        $this->assertSame(['Anna', 'Ben', 'Carla'], $names(registration_list($id)));
        $this->assertSame(['Ben'], $names(registration_list($id, 'pending')));
        $this->assertSame(['Ben'], $names(registration_list($id, null, 'dort')));
        $this->assertSame(['Anna'], $names(registration_list($id, null, 'MUSTER')));
        $this->assertSame(['Carla'], $names(registration_list($id, null, 'mail.example')));
        $this->assertSame(['Anna', 'Ben'], $names(registration_list($id, null, 'example.org')));
        $this->assertSame([], registration_list($id, null, '%'), 'LIKE-Platzhalter werden escaped');

        $row = registration_list($id)[0];
        $this->assertSame(2, $row['person_count']);
        $this->assertTrue($row['is_preferred_place']);
        $this->assertFalse($row['is_duplicate']);
    }

    public function testListMarksDuplicatesAcrossAllStatuses(): void
    {
        $a = $this->register(['first_name' => 'Anna', 'email' => 'anna@example.org']);
        $b = $this->register(['first_name' => 'ANNA ', 'email' => 'other@example.org']);
        registration_cancel($b['id']);
        $this->register(['first_name' => 'Ben', 'email' => 'ben@example.org']);
        // andere Veranstaltung zählt nicht
        $other = $this->createEvent();
        registration_create(event_find($other), $this->data(['first_name' => 'Ben', 'email' => 'ben@example.org']));

        $flags = array_column(registration_list($this->event['id']), 'is_duplicate', 'id');

        $this->assertTrue($flags[$a['id']]);
        $this->assertTrue($flags[$b['id']]);
        $this->assertSame(1, count(array_filter($flags, fn ($f) => !$f)));
    }

    private function register(array $fields = [], array $slots = []): array
    {
        return registration_create($this->event, $this->data($fields + ['slots' => $slots]));
    }

    private function data(array $fields): array
    {
        return $fields + [
            'first_name' => 'Anna',
            'last_name' => 'Muster',
            'congregation' => 'Hamm',
            'email' => 'anna@example.org',
            'phone' => null,
            'no_email' => false,
            'group_1' => 1,
            'group_2' => 0,
            'group_3' => 0,
            'group_4' => 0,
            'group_5' => 0,
            'custom_split' => false,
            'slots' => [],
        ];
    }
}
