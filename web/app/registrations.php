<?php
declare(strict_types=1);

// Anmeldungen: Validierung, Kontingent/Status, Speichern, Absage,
// Admin-Funktionen (SPEC §5, §6, §7.2–7.4, §7.6)

/**
 * Größere Anmeldungen (Personen gesamt) werden nie automatisch bestätigt,
 * sondern kommen zur Prüfung auf die Warteliste.
 */
const AUTO_CONFIRM_MAX_PERSONS = 99;

/** Personen, die zum Kontingent zählen: alle Personengruppen (SPEC §5.3) */
function registration_group_size(array $counts): int
{
    return registration_person_count($counts);
}

/** Alle Personen über alle Personengruppen */
function registration_person_count(array $counts): int
{
    $count = 0;
    foreach (array_keys(PERSON_GROUPS) as $group) {
        $count += (int) ($counts[$group] ?? 0);
    }
    return $count;
}

/**
 * Status einer neuen Anmeldung: bestätigt nur bei bevorzugtem Ort,
 * ausreichendem Kontingent und höchstens AUTO_CONFIRM_MAX_PERSONS Personen,
 * sonst Warteliste.
 */
function registration_decide_status(
    bool $preferredPlace,
    int $occupied,
    int $groupSize,
    int $maxParticipants,
    int $personCount
): string {
    return $preferredPlace
        && $personCount <= AUTO_CONFIRM_MAX_PERSONS
        && $occupied + $groupSize <= $maxParticipants
        ? 'confirmed' : 'pending';
}

/** @param list<string> $places */
function registration_is_preferred_place(string $congregation, array $places): bool
{
    $needle = mb_strtolower(normalize_line($congregation));
    foreach ($places as $place) {
        if (mb_strtolower(normalize_line($place)) === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * IDs der Anmeldungen mit gleicher E-Mail oder gleichem Vor- + Nachnamen
 * (case-insensitive, getrimmt).
 *
 * @param list<array<string, mixed>> $rows id, first_name, last_name, email
 * @return list<int>
 */
function registration_duplicate_ids(array $rows): array
{
    $byKey = [];
    foreach ($rows as $row) {
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '') {
            $byKey['email:' . $email][] = (int) $row['id'];
        }
        $name = mb_strtolower(normalize_line((string) $row['first_name']) . '|' . normalize_line((string) $row['last_name']));
        $byKey['name:' . $name][] = (int) $row['id'];
    }

    $ids = [];
    foreach ($byKey as $group) {
        if (count($group) > 1) {
            foreach ($group as $id) {
                $ids[$id] = true;
            }
        }
    }
    $ids = array_keys($ids);
    sort($ids);
    return $ids;
}

/**
 * Prüft und normalisiert die Eingaben des Anmeldeformulars.
 *
 * $input: first_name, last_name, congregation, email, phone, no_email,
 * Anzahlen je Personengruppe (group_1 … group_5), custom_split,
 * attend[slot_id] (Checkbox je Programmpunkt, ohne Aufteilung),
 * split[slot_id][group_x] (Anzahlen, mit Aufteilung).
 *
 * Mit $maxParticipants (öffentliches Formular) dürfen die Personen die
 * Kapazität nicht überschreiten; der Admin darf überbuchen. Gruppen, die
 * nicht in $groups stehen (nicht gewählt bei der Veranstaltung), sind 0.
 *
 * @param list<array<string, mixed>> $slots Programmpunkte der Veranstaltung (mit id)
 * @param list<string>|null $groups Personengruppen der Veranstaltung (null = alle)
 * @return array{0: array<string, mixed>, 1: array<string, string>} [Daten, Fehler je Feld]
 */
function registration_validate(array $input, array $slots, ?int $maxParticipants = null, ?array $groups = null): array
{
    $groups ??= array_keys(PERSON_GROUPS);
    $errors = [];
    $string = fn (string $key): string => is_string($input[$key] ?? null) ? $input[$key] : '';

    $noEmail = !empty($input['no_email']);
    $data = [
        'first_name' => normalize_line($string('first_name')),
        'last_name' => normalize_line($string('last_name')),
        'congregation' => normalize_line($string('congregation')),
        'email' => $noEmail ? null : trim($string('email')),
        'phone' => $noEmail ? normalize_line($string('phone')) : null,
        'no_email' => $noEmail,
        'custom_split' => !empty($input['custom_split']),
    ];

    foreach (['first_name' => 'Vorname', 'last_name' => 'Nachname', 'congregation' => setting_text('congregation_label')] as $field => $label) {
        if ($data[$field] === '') {
            $errors[$field] = "$label ist erforderlich.";
        }
    }
    if ($noEmail) {
        if ($data['phone'] === '') {
            $errors['phone'] = 'Ohne E-Mail-Adresse ist eine Telefonnummer erforderlich.';
        }
    } elseif ($data['email'] === '') {
        $errors['email'] = 'E-Mail ist erforderlich.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }

    foreach (array_keys(PERSON_GROUPS) as $group) {
        if (!in_array($group, $groups, true)) {
            $data[$group] = 0;
            continue;
        }
        $count = registration_parse_count($input[$group] ?? '');
        if ($count === null) {
            $errors[$group] = 'Bitte eine ganze Zahl ab 0 angeben.';
            $count = 0;
        }
        $data[$group] = $count;
    }
    if (registration_person_count($data) < 1) {
        $errors['persons'] = 'Bitte mindestens eine Person angeben.';
    } elseif ($maxParticipants !== null && registration_group_size($data) > $maxParticipants) {
        // Kapazität bewusst nicht nennen (wird öffentlich nicht angezeigt, SPEC §4)
        $errors['persons'] = 'So viele Personen können wir leider nicht anmelden. Bitte nimm Kontakt mit uns auf.';
        foreach ($groups as $group) {
            $errors[$group] ??= '';
        }
    }

    $attend = is_array($input['attend'] ?? null) ? $input['attend'] : [];
    $split = is_array($input['split'] ?? null) ? $input['split'] : [];
    $data['slots'] = [];
    $splitInvalid = false;
    foreach ($slots as $slot) {
        $slotId = (int) $slot['id'];
        $childcare = $slot['childcare'] ?? [];
        $attending = !$data['custom_split'] && !empty($attend[$slotId]);
        $counts = [];
        foreach (array_keys(PERSON_GROUPS) as $group) {
            if ($data['custom_split']) {
                $value = is_array($split[$slotId] ?? null) ? ($split[$slotId][$group] ?? '') : '';
                $count = registration_parse_count($value);
                if ($count === null || $count > $data[$group]) {
                    $splitInvalid = true;
                    $count = 0;
                }
            } else {
                // Standard: Kinder betreuter Gruppen sind in der Kinderbetreuung (SPEC §5.4)
                $count = $attending && !in_array($group, $childcare, true) ? $data[$group] : 0;
            }
            $counts[$group] = $count;
        }
        // Kinderbetreuung je betreuter Gruppe: alle nicht beim Programmpunkt, sofern
        // jemand der Anmeldung den Programmpunkt besucht (SPEC §5.4)
        $present = $attending || array_sum($counts) > 0;
        foreach (kids_groups(array_keys(PERSON_GROUPS)) as $group) {
            $counts['childcare_' . $group] = $present && in_array($group, $childcare, true)
                ? max($data[$group] - $counts[$group], 0)
                : 0;
        }
        $data['slots'][$slotId] = $counts;
    }
    if ($splitInvalid) {
        $errors['split'] = 'Die Anzahl je Programmpunkt darf die Anzahl der Personen nicht überschreiten.';
    }

    return [$data, $errors];
}

/** '' → 0, '3' → 3, ungültig → null (höchstens 6 Stellen, Schutz vor Überlauf) */
function registration_parse_count(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    if (!ctype_digit($value) || strlen($value) > 6) {
        return null;
    }
    return (int) $value;
}

/** Summe der Gruppengrößen aller bestätigten Anmeldungen */
function registration_occupied(int $eventId, ?int $excludeId = null): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(' . implode(' + ', array_keys(PERSON_GROUPS)) . "), 0) FROM registrations
         WHERE event_id = ? AND status = 'confirmed' AND id != ?"
    );
    $stmt->execute([$eventId, $excludeId ?? 0]);
    return (int) $stmt->fetchColumn();
}

/**
 * Würde (weiterhin) bestätigt sein der Anmeldung $registration das
 * Kontingent überschreiten? Für die Warnung im Admin (§7.3, §7.4).
 *
 * @param array<string, mixed> $event
 * @param array<string, mixed> $registration Anzahlen, ggf. mit id (zählt dann nicht doppelt)
 */
function registration_exceeds_quota(array $event, array $registration): bool
{
    $excludeId = isset($registration['id']) ? (int) $registration['id'] : null;
    return registration_occupied((int) $event['id'], $excludeId) + registration_group_size($registration)
        > (int) $event['max_participants'];
}

/**
 * Speichert eine neue Anmeldung (Daten aus registration_validate) und
 * entscheidet in derselben Schreibtransaktion über den Status.
 *
 * @param array<string, mixed> $event Veranstaltung aus event_find()/event_active()
 * @return array<string, mixed> gespeicherte Anmeldung
 */
function registration_create(array $event, array $data): array
{
    $eventId = (int) $event['id'];
    $places = preferred_places();

    $id = db_transaction(function (PDO $pdo) use ($event, $eventId, $places, $data): int {
        $status = registration_decide_status(
            registration_is_preferred_place($data['congregation'], $places),
            registration_occupied($eventId),
            registration_group_size($data),
            (int) $event['max_participants'],
            registration_person_count($data)
        );

        $pdo->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, email, phone,
                no_email, group_1, group_2, group_3, group_4, group_5, custom_split, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventId, now_utc(), $data['first_name'], $data['last_name'], $data['congregation'],
            $data['email'], $data['phone'], $data['no_email'] ? 1 : 0,
            $data['group_1'], $data['group_2'], $data['group_3'], $data['group_4'], $data['group_5'],
            $data['custom_split'] ? 1 : 0, $status, bin2hex(random_bytes(32)),
        ]);
        $id = (int) $pdo->lastInsertId();
        registration_save_slots($pdo, $id, $data['slots']);
        return $id;
    });

    return registration_find($id);
}

/**
 * Admin: alle Felder ändern (§7.4). Verschickt keine Mail.
 *
 * @param array<string, mixed> $data aus registration_validate
 */
function registration_update(int $id, array $data, string $status): void
{
    registration_assert_status($status);
    db_transaction(function (PDO $pdo) use ($id, $data, $status): void {
        $pdo->prepare(
            'UPDATE registrations SET first_name = ?, last_name = ?, congregation = ?, email = ?, phone = ?,
                no_email = ?, group_1 = ?, group_2 = ?, group_3 = ?, group_4 = ?, group_5 = ?,
                custom_split = ?, status = ?
             WHERE id = ?'
        )->execute([
            $data['first_name'], $data['last_name'], $data['congregation'], $data['email'], $data['phone'],
            $data['no_email'] ? 1 : 0, $data['group_1'], $data['group_2'], $data['group_3'], $data['group_4'],
            $data['group_5'], $data['custom_split'] ? 1 : 0, $status, $id,
        ]);
        $pdo->prepare('DELETE FROM registration_slots WHERE registration_id = ?')->execute([$id]);
        registration_save_slots($pdo, $id, $data['slots']);
    });
}

/** @param array<int, array<string, int>> $slots slot_id → Anzahl je Personengruppe */
function registration_save_slots(PDO $pdo, int $registrationId, array $slots): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO registration_slots (registration_id, slot_id, group_1, group_2, group_3, group_4, group_5,
            childcare_group_3, childcare_group_4, childcare_group_5)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($slots as $slotId => $counts) {
        $stmt->execute([
            $registrationId, $slotId,
            $counts['group_1'] ?? 0, $counts['group_2'] ?? 0, $counts['group_3'] ?? 0,
            $counts['group_4'] ?? 0, $counts['group_5'] ?? 0,
            $counts['childcare_group_3'] ?? 0, $counts['childcare_group_4'] ?? 0, $counts['childcare_group_5'] ?? 0,
        ]);
    }
}

/** @return array<string, mixed>|null */
function registration_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM registrations WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** @return array<string, mixed>|null */
function registration_find_by_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM registrations WHERE cancel_token = ?');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/** @return array<int, array<string, int>> slot_id → Anzahl je Personengruppe beim Programmpunkt und childcare_* (Kinderbetreuung) */
function registration_slot_counts(int $registrationId): array
{
    $stmt = db()->prepare(
        'SELECT slot_id, group_1, group_2, group_3, group_4, group_5,
                childcare_group_3, childcare_group_4, childcare_group_5
         FROM registration_slots WHERE registration_id = ?'
    );
    $stmt->execute([$registrationId]);
    $slots = [];
    foreach ($stmt->fetchAll() as $row) {
        $slots[(int) $row['slot_id']] = array_map('intval', array_diff_key($row, ['slot_id' => 0]));
    }
    return $slots;
}

/** Absage per Link (§6): nur aus „offen“ oder „bestätigt“. Kein Nachrücken. */
function registration_cancel(int $id): bool
{
    $stmt = db()->prepare(
        "UPDATE registrations SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'confirmed')"
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/** Admin: Statuswechsel (§7.3). Mail verschickt die Seite per mail_registration(). */
function registration_set_status(int $id, string $status): void
{
    registration_assert_status($status);
    db()->prepare('UPDATE registrations SET status = ? WHERE id = ?')->execute([$status, $id]);
}

/** Admin: endgültig löschen inkl. Aufteilung und Mail-Protokoll (§7.6) */
function registration_delete(int $id): void
{
    db()->prepare('DELETE FROM registrations WHERE id = ?')->execute([$id]);
}

function registration_assert_status(string $status): void
{
    if (!isset(STATUS_LABELS[$status])) {
        throw new InvalidArgumentException('Unbekannter Status: ' . $status);
    }
}

/**
 * Anmeldungen einer Veranstaltung für die Admin-Tabelle (§7.2), älteste
 * zuerst. Zusätzliche Felder: person_count (alle Gruppen), is_preferred_place,
 * is_duplicate (über alle Status der Veranstaltung).
 *
 * @param string|null $search Teilstring in Name, Ort oder E-Mail (Groß-/Kleinschreibung egal)
 * @return list<array<string, mixed>>
 */
function registration_list(int $eventId, ?string $status = null, ?string $search = null): array
{
    $stmt = db()->prepare('SELECT * FROM registrations WHERE event_id = ? ORDER BY created_at, id');
    $stmt->execute([$eventId]);
    $rows = $stmt->fetchAll();

    $duplicates = array_flip(registration_duplicate_ids($rows));
    $places = preferred_places();
    $search = normalize_line((string) $search);

    $result = [];
    foreach ($rows as $row) {
        if ($status !== null && $status !== '' && $row['status'] !== $status) {
            continue;
        }
        if ($search !== '') {
            $haystack = implode("\n", [
                $row['first_name'] . ' ' . $row['last_name'], $row['congregation'], (string) $row['email'],
            ]);
            if (mb_stripos($haystack, $search) === false) {
                continue;
            }
        }
        $row['person_count'] = registration_person_count($row);
        $row['is_preferred_place'] = registration_is_preferred_place($row['congregation'], $places);
        $row['is_duplicate'] = isset($duplicates[$row['id']]);
        $result[] = $row;
    }
    return $result;
}
