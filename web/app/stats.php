<?php
declare(strict_types=1);

/** Altersgruppen, die zum Kontingent zählen (SPEC §5.3: Kinder 0–2 nicht) */
const QUOTA_AGE_GROUPS = ['group_1', 'group_2', 'group_3', 'group_4'];

/**
 * Auswertung einer Veranstaltung (SPEC §7.7).
 *
 * - by_status: je Status Anzahl Anmeldungen und Einzelpersonen (inkl. 0–2)
 * - quota_used / quota_max: bestätigte Personen ohne 0–2 / max. Teilnehmer
 * - quota_pending: offene (unbestätigte) Personen ohne 0–2, für die Belegung
 *   „wenn alle bestätigt würden“
 * - age_groups: bestätigte Personen je Altersgruppe
 * - places: Personen (inkl. 0–2) je Heimatversammlung, bestätigt und offen,
 *   meiste zuerst; Schreibweisen ohne Rücksicht auf Groß-/Kleinschreibung
 *   zusammengefasst
 * - slots: je Programmpunkt bestätigte Personen je Altersgruppe und Summe
 *   (beim Programmpunkt), dazu betreute Altersgruppen und Kinder in der
 *   Kinderbetreuung je betreuter Gruppe (SPEC §5.4)
 *
 * @param array<string, mixed> $event
 * @return array{
 *     by_status: array<string, array{registrations: int, people: int}>,
 *     quota_used: int,
 *     quota_pending: int,
 *     quota_max: int,
 *     age_groups: array<string, int>,
 *     places: list<array{name: string, confirmed: int, pending: int, preferred: bool}>,
 *     slots: list<array{time: string, label: string, groups: array<string, int>, total: int,
 *         childcare_groups: list<string>, childcare: array<string, int>, childcare_total: int}>
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

    $childcareColumns = array_map(fn ($group) => 'childcare_' . $group, array_keys(CHILDCARE_AGE_GROUPS));
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
        $groups = [];
        foreach ($columns as $column) {
            $groups[$column] = (int) $row[$column];
        }
        $childcareGroups = childcare_parse((string) $row['childcare_groups']);
        $childcare = [];
        foreach ($childcareGroups as $group) {
            $childcare[$group] = (int) $row['childcare_' . $group];
        }
        $slots[] = [
            'time' => $row['time'],
            'label' => $row['label'],
            'groups' => $groups,
            'total' => array_sum($groups),
            'childcare_groups' => $childcareGroups,
            'childcare' => $childcare,
            'childcare_total' => array_sum($childcare),
        ];
    }

    return [
        'by_status' => $byStatus,
        'quota_used' => array_sum(array_intersect_key($ageGroups, array_flip(QUOTA_AGE_GROUPS))),
        'quota_pending' => $quotaPending,
        'quota_max' => (int) $event['max_participants'],
        'age_groups' => $ageGroups,
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
