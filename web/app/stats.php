<?php
declare(strict_types=1);

/** Altersgruppen, die zum Kontingent zählen (SPEC §5.3: Kinder 0–2 nicht) */
const QUOTA_AGE_GROUPS = ['adults', 'youth', 'kids_7_12', 'kids_3_6'];

/**
 * Auswertung einer Veranstaltung (SPEC §7.7).
 *
 * - by_status: je Status Anzahl Anmeldungen und Einzelpersonen (inkl. 0–2)
 * - quota_used / quota_max: bestätigte Personen ohne 0–2 / max. Teilnehmer
 * - quota_pending: offene (unbestätigte) Personen ohne 0–2, für die Belegung
 *   „wenn alle bestätigt würden“
 * - age_groups: bestätigte Personen je Altersgruppe
 * - slots: je Programmpunkt bestätigte Personen je Altersgruppe und Summe
 *
 * @param array<string, mixed> $event
 * @return array{
 *     by_status: array<string, array{registrations: int, people: int}>,
 *     quota_used: int,
 *     quota_pending: int,
 *     quota_max: int,
 *     age_groups: array<string, int>,
 *     slots: list<array{time: string, label: string, groups: array<string, int>, total: int}>
 * }
 */
function stats_for_event(array $event): array
{
    $eventId = (int) $event['id'];
    $columns = array_keys(AGE_GROUPS);
    $allPeople = implode(' + ', $columns);

    $byStatus = array_fill_keys(array_keys(STATUS_LABELS), ['registrations' => 0, 'people' => 0]);
    $stmt = db()->prepare(
        "SELECT status, COUNT(*) AS registrations, SUM($allPeople) AS people
         FROM registrations WHERE event_id = ? GROUP BY status"
    );
    $stmt->execute([$eventId]);
    foreach ($stmt->fetchAll() as $row) {
        $byStatus[$row['status']] = ['registrations' => (int) $row['registrations'], 'people' => (int) $row['people']];
    }

    $quotaSum = implode(' + ', QUOTA_AGE_GROUPS);
    $stmt = db()->prepare("SELECT COALESCE(SUM($quotaSum), 0) FROM registrations WHERE event_id = ? AND status = 'pending'");
    $stmt->execute([$eventId]);
    $quotaPending = (int) $stmt->fetchColumn();

    $sums = implode(', ', array_map(fn ($c) => "COALESCE(SUM($c), 0) AS $c", $columns));
    $stmt = db()->prepare("SELECT $sums FROM registrations WHERE event_id = ? AND status = 'confirmed'");
    $stmt->execute([$eventId]);
    $ageGroups = array_map('intval', $stmt->fetch());

    $slotSums = implode(', ', array_map(fn ($c) => "COALESCE(SUM(c.$c), 0) AS $c", $columns));
    $stmt = db()->prepare(
        "SELECT s.time, s.label, $slotSums
         FROM event_slots s
         LEFT JOIN (
             SELECT rs.* FROM registration_slots rs
             JOIN registrations r ON r.id = rs.registration_id AND r.status = 'confirmed'
         ) c ON c.slot_id = s.id
         WHERE s.event_id = ?
         GROUP BY s.id
         ORDER BY s.sort, s.time"
    );
    $stmt->execute([$eventId]);
    $slots = [];
    foreach ($stmt->fetchAll() as $row) {
        $groups = [];
        foreach ($columns as $column) {
            $groups[$column] = (int) $row[$column];
        }
        $slots[] = ['time' => $row['time'], 'label' => $row['label'], 'groups' => $groups, 'total' => array_sum($groups)];
    }

    return [
        'by_status' => $byStatus,
        'quota_used' => array_sum(array_intersect_key($ageGroups, array_flip(QUOTA_AGE_GROUPS))),
        'quota_pending' => $quotaPending,
        'quota_max' => (int) $event['max_participants'],
        'age_groups' => $ageGroups,
        'slots' => $slots,
    ];
}
