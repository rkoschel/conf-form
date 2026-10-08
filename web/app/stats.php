<?php
declare(strict_types=1);

/**
 * Auswertung einer Veranstaltung (SPEC §7.7).
 *
 * - by_status: je Status Anzahl Anmeldungen und Personen
 * - quota_used / quota_max: bestätigte Personen (alle Gruppen) / max. Teilnehmer
 * - quota_pending: offene (unbestätigte) Personen, für die Belegung
 *   „wenn alle bestätigt würden“
 * - groups: je Personengruppe der Veranstaltung Name, bestätigte und offene Personen
 * - places: Personen je Heimatversammlung, bestätigt und offen,
 *   meiste zuerst; Schreibweisen ohne Rücksicht auf Groß-/Kleinschreibung
 *   zusammengefasst
 * - slots: je Programmpunkt bestätigte Personen je Personengruppe und Summe
 *   (beim Programmpunkt), dazu betreute Kindergruppen und Kinder in der
 *   Kinderbetreuung je betreuter Gruppe (SPEC §5.4)
 *
 * @param array<string, mixed> $event
 * @return array{
 *     by_status: array<string, array{registrations: int, people: int}>,
 *     quota_used: int,
 *     quota_pending: int,
 *     quota_max: int,
 *     groups: array<string, array{name: string, confirmed: int, pending: int}>,
 *     places: list<array{name: string, confirmed: int, pending: int, preferred: bool}>,
 *     slots: list<array{time: string, label: string, groups: array<string, int>, total: int,
 *         childcare_groups: list<string>, childcare: array<string, int>, childcare_total: int}>
 * }
 */
function stats_for_event(array $event): array
{
    $eventId = (int) $event['id'];
    $eventGroups = event_groups($event);
    $columns = array_keys(PERSON_GROUPS);
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

    // Personen je Gruppe, bestätigt und offen
    $sums = implode(', ', array_map(fn ($c) => "COALESCE(SUM($c), 0) AS $c", $columns));
    $stmt = db()->prepare(
        "SELECT status, $sums FROM registrations
         WHERE event_id = ? AND status IN ('confirmed', 'pending') GROUP BY status"
    );
    $stmt->execute([$eventId]);
    $byGroup = ['confirmed' => array_fill_keys($columns, 0), 'pending' => array_fill_keys($columns, 0)];
    foreach ($stmt->fetchAll() as $row) {
        foreach ($columns as $column) {
            $byGroup[$row['status']][$column] = (int) $row[$column];
        }
    }
    $groups = [];
    foreach ($eventGroups as $key => $name) {
        $groups[$key] = ['name' => $name, 'confirmed' => $byGroup['confirmed'][$key], 'pending' => $byGroup['pending'][$key]];
    }

    $stmt = db()->prepare(
        "SELECT MIN(congregation) AS name,
                SUM(CASE WHEN status = 'confirmed' THEN $allPeople ELSE 0 END) AS confirmed,
                SUM(CASE WHEN status = 'pending' THEN $allPeople ELSE 0 END) AS pending
         FROM registrations
         WHERE event_id = ? AND status IN ('confirmed', 'pending')
         GROUP BY congregation COLLATE NOCASE
         ORDER BY confirmed + pending DESC, name COLLATE NOCASE"
    );
    $stmt->execute([$eventId]);
    $preferredPlaces = preferred_places();
    $places = [];
    foreach ($stmt->fetchAll() as $row) {
        $places[] = [
            'name' => $row['name'],
            'confirmed' => (int) $row['confirmed'],
            'pending' => (int) $row['pending'],
            'preferred' => registration_is_preferred_place($row['name'], $preferredPlaces),
        ];
    }

    $childcareColumns = array_map(fn ($group) => 'childcare_' . $group, kids_groups($columns));
    $slotSums = implode(', ', array_map(
        fn ($c) => "COALESCE(SUM(c.$c), 0) AS $c",
        array_merge($columns, $childcareColumns)
    ));
    $stmt = db()->prepare(
        "SELECT s.time, s.label, s.childcare AS childcare_groups, $slotSums
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
        $slotGroups = [];
        foreach (array_keys($eventGroups) as $column) {
            $slotGroups[$column] = (int) $row[$column];
        }
        $childcareGroups = array_values(array_intersect(childcare_parse((string) $row['childcare_groups']), array_keys($eventGroups)));
        $childcare = [];
        foreach ($childcareGroups as $group) {
            $childcare[$group] = (int) $row['childcare_' . $group];
        }
        $slots[] = [
            'time' => $row['time'],
            'label' => $row['label'],
            'groups' => $slotGroups,
            'total' => array_sum($slotGroups),
            'childcare_groups' => $childcareGroups,
            'childcare' => $childcare,
            'childcare_total' => array_sum($childcare),
        ];
    }

    return [
        'by_status' => $byStatus,
        'quota_used' => array_sum($byGroup['confirmed']),
        'quota_pending' => array_sum($byGroup['pending']),
        'quota_max' => (int) $event['max_participants'],
        'groups' => $groups,
        'places' => $places,
        'slots' => $slots,
    ];
}

/**
 * Belegung als ganze Prozent der Kapazität für die öffentliche Anzeige
 * (SPEC §4): bestätigt, Warteliste (offen), frei – Summe immer 100.
 * Bestätigte über dem Kontingent werden auf 100 gekappt, die Warteliste auf
 * den Rest. Nur ganze Prozent, damit sich keine Personenzahlen zurückrechnen
 * lassen.
 *
 * @return array{confirmed: int, waitlist: int, free: int}
 */
function quota_shares(int $confirmed, int $pending, int $max): array
{
    if ($max <= 0) {
        return ['confirmed' => 0, 'waitlist' => 0, 'free' => 100];
    }
    $confirmedShare = (int) round(min($confirmed, $max) / $max * 100);
    $waitlistShare = min((int) round(max($pending, 0) / $max * 100), 100 - $confirmedShare);
    return [
        'confirmed' => $confirmedShare,
        'waitlist' => $waitlistShare,
        'free' => 100 - $confirmedShare - $waitlistShare,
    ];
}
