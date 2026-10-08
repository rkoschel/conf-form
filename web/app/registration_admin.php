<?php
declare(strict_types=1);

// Admin-spezifische Ergänzungen zu registrations.php (SPEC §7.2–7.4)

/**
 * Letzter Mailversand je Anmeldung einer Veranstaltung (für die Spalte
 * Mail-Status).
 *
 * @return array<int, array{type: string, sent_at: string, success: int, error: ?string}> registration_id → Eintrag
 */
function mail_status_for_event(int $eventId): array
{
    $stmt = db()->prepare(
        'SELECT m.registration_id, m.type, m.sent_at, m.success, m.error
         FROM mail_log m
         JOIN registrations r ON r.id = m.registration_id
         WHERE r.event_id = ?
         ORDER BY m.sent_at, m.id'
    );
    $stmt->execute([$eventId]);
    $latest = [];
    foreach ($stmt->fetchAll() as $row) {
        $latest[(int) $row['registration_id']] = $row;
    }
    return $latest;
}

/**
 * Formularwerte für das Bearbeiten aus einer gespeicherten Anmeldung,
 * im selben Format wie die POST-Daten des Anmeldeformulars.
 *
 * @param array<string, mixed> $registration
 * @param array<int, array<string, int>> $slotCounts aus registration_slot_counts()
 * @return array<string, mixed>
 */
function registration_form_values(array $registration, array $slotCounts): array
{
    $form = [
        'first_name' => $registration['first_name'],
        'last_name' => $registration['last_name'],
        'congregation' => $registration['congregation'],
        'email' => (string) $registration['email'],
        'phone' => (string) $registration['phone'],
        'no_email' => (bool) $registration['no_email'],
        'custom_split' => (bool) $registration['custom_split'],
        'status' => $registration['status'],
        'attend' => [],
        'split' => [],
    ];
    foreach (array_keys(PERSON_GROUPS) as $group) {
        $form[$group] = (string) $registration[$group];
    }
    foreach ($slotCounts as $slotId => $counts) {
        // Teilnahme auch, wenn nur Kinder in der Betreuung sind
        if (array_sum($counts) > 0) {
            $form['attend'][$slotId] = '1';
        }
        // Aufteilung = Personen beim Programmpunkt (ohne childcare_*)
        $form['split'][$slotId] = array_map('strval', array_intersect_key($counts, PERSON_GROUPS));
    }
    return $form;
}
