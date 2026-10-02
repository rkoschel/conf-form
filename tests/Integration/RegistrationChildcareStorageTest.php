<?php
declare(strict_types=1);

final class RegistrationChildcareStorageTest extends DbTestCase
{
    public function testCreateAndUpdateStoreChildcareCounts(): void
    {
        $eventId = event_save(null, [
            'title' => 'K', 'date' => '2099-05-01', 'location' => 'Hamm', 'description' => '',
            'registration_deadline' => '2099-04-15T23:59', 'timezone' => 'Europe/Berlin', 'max_participants' => 100,
            'organizer_name' => '', 'organizer_email' => '', 'active' => true,
            'slots' => [['time' => '10:00', 'label' => 'Vortrag', 'childcare' => ['kids_3_6']]],
        ]);
        $event = event_find($eventId);
        $slotId = (int) $event['slots'][0]['id'];
        $input = [
            'first_name' => 'Anna', 'last_name' => 'Muster', 'congregation' => 'Hamm', 'email' => 'anna@example.org',
            'adults' => '1', 'kids_3_6' => '2', 'attend' => [$slotId => '1'],
        ];

        [$data] = registration_validate($input, $event['slots']);
        $registration = registration_create($event, $data);
        $counts = registration_slot_counts((int) $registration['id'])[$slotId];

        $this->assertSame([1, 0, 2], [$counts['adults'], $counts['kids_3_6'], $counts['childcare_kids_3_6']]);

        [$data] = registration_validate($input + [
            'custom_split' => '1', 'split' => [$slotId => ['adults' => '1', 'kids_3_6' => '1']],
        ], $event['slots']);
        registration_update((int) $registration['id'], $data, 'confirmed');
        $counts = registration_slot_counts((int) $registration['id'])[$slotId];

        $this->assertSame([1, 1, 1], [$counts['adults'], $counts['kids_3_6'], $counts['childcare_kids_3_6']]);
    }
}
