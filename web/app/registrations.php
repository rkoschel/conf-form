<?php
declare(strict_types=1);

// Anmeldungen: Validierung, Kontingent/Status, Speichern, Absage,
// Admin-Funktionen (SPEC §5, §6, §7.2–7.4, §7.6)

/** Obergrenze je Altersgruppe (Schutz vor Tippfehlern wie 1000) */
const MAX_PER_GROUP = 99;

/** Personen, die zum Kontingent zählen */
function registration_group_size(array $counts): int
{
    $size = 0;
    foreach (QUOTA_AGE_GROUPS as $group) {
        $size += (int) ($counts[$group] ?? 0);
    }
    return $size;
}

/** Alle Personen inkl. Kinder 0–2 */
function registration_person_count(array $counts): int
{
    $count = 0;
    foreach (array_keys(AGE_GROUPS) as $group) {
        $count += (int) ($counts[$group] ?? 0);
    }
    return $count;
}

/**
 * Status einer neuen Anmeldung: bestätigt nur bei bevorzugtem Ort und
 * ausreichendem Kontingent, sonst Warteliste.
 */
function registration_decide_status(bool $preferredPlace, int $occupied, int $groupSize, int $maxParticipants): string
{
    return $preferredPlace && $occupied + $groupSize <= $maxParticipants ? 'confirmed' : 'pending';
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
 * Anzahlen je Altersgruppe (AGE_GROUPS), custom_split,
 * attend[slot_id] (Checkbox je Programmpunkt, ohne Aufteilung),
 * split[slot_id][Altersgruppe] (Anzahlen, mit Aufteilung).
 *
 * @param list<array<string, mixed>> $slots Programmpunkte der Veranstaltung (mit id)
 * @return array{0: array<string, mixed>, 1: array<string, string>} [Daten, Fehler je Feld]
 */
function registration_validate(array $input, array $slots): array
{
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

    foreach (['first_name' => 'Vorname', 'last_name' => 'Nachname', 'congregation' => 'Heimatversammlung'] as $field => $label) {
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

    foreach (AGE_GROUPS as $group => $label) {
        $count = registration_parse_count($input[$group] ?? '');
        if ($count === null) {
            $errors[$group] = 'Bitte eine ganze Zahl von 0 bis ' . MAX_PER_GROUP . ' angeben.';
            $count = 0;
        }
        $data[$group] = $count;
    }
    if (registration_person_count($data) < 1) {
        $errors['persons'] = 'Bitte mindestens eine Person angeben.';
    }

    $attend = is_array($input['attend'] ?? null) ? $input['attend'] : [];
    $split = is_array($input['split'] ?? null) ? $input['split'] : [];
    $data['slots'] = [];
    $splitInvalid = false;
    foreach ($slots as $slot) {
        $slotId = (int) $slot['id'];
        $counts = [];
        foreach (array_keys(AGE_GROUPS) as $group) {
            if ($data['custom_split']) {
                $value = is_array($split[$slotId] ?? null) ? ($split[$slotId][$group] ?? '') : '';
                $count = registration_parse_count($value);
                if ($count === null || $count > $data[$group]) {
                    $splitInvalid = true;
                    $count = 0;
                }
            } else {
                $count = !empty($attend[$slotId]) ? $data[$group] : 0;
            }
            $counts[$group] = $count;
        }
        $data['slots'][$slotId] = $counts;
    }
    if ($splitInvalid) {
        $errors['split'] = 'Die Anzahl je Programmpunkt darf die Anzahl der Personen nicht überschreiten.';
    }

    return [$data, $errors];
}

/** '' → 0, '3' → 3, ungültig → null */
function registration_parse_count(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    if (!ctype_digit($value) || (int) $value > MAX_PER_GROUP) {
        return null;
    }
    return (int) $value;
}

/** Summe der Gruppengrößen aller bestätigten Anmeldungen */
function registration_occupied(int $eventId, ?int $excludeId = null): int
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(adults + youth + kids_7_12 + kids_3_6), 0) FROM registrations
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
    $places = $event['places'] ?? event_places($eventId);

    $id = db_transaction(function (PDO $pdo) use ($event, $eventId, $places, $data): int {
        $status = registration_decide_status(
            registration_is_preferred_place($data['congregation'], $places),
            registration_occupied($eventId),
            registration_group_size($data),
            (int) $event['max_participants']
        );

        $pdo->prepare(
            'INSERT INTO registrations (event_id, created_at, first_name, last_name, congregation, email, phone,
                no_email, adults, youth, kids_7_12, kids_3_6, kids_0_2, custom_split, status, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventId, now_utc(), $data['first_name'], $data['last_name'], $data['congregation'],
            $data['email'], $data['phone'], $data['no_email'] ? 1 : 0,
            $data['adults'], $data['youth'], $data['kids_7_12'], $data['kids_3_6'], $data['kids_0_2'],
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
                no_email = ?, adults = ?, youth = ?, kids_7_12 = ?, kids_3_6 = ?, kids_0_2 = ?,
                custom_split = ?, status = ?
             WHERE id = ?'
        )->execute([
            $data['first_name'], $data['last_name'], $data['congregation'], $data['email'], $data['phone'],
            $data['no_email'] ? 1 : 0, $data['adults'], $data['youth'], $data['kids_7_12'], $data['kids_3_6'],
            $data['kids_0_2'], $data['custom_split'] ? 1 : 0, $status, $id,
        ]);
        $pdo->prepare('DELETE FROM registration_slots WHERE registration_id = ?')->execute([$id]);
        registration_save_slots($pdo, $id, $data['slots']);
    });
}

/** @param array<int, array<string, int>> $slots slot_id → Anzahl je Altersgruppe */
function registration_save_slots(PDO $pdo, int $registrationId, array $slots): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO registration_slots (registration_id, slot_id, adults, youth, kids_7_12, kids_3_6, kids_0_2)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($slots as $slotId => $counts) {
        $stmt->execute([
            $registrationId, $slotId,
            $counts['adults'] ?? 0, $counts['youth'] ?? 0, $counts['kids_7_12'] ?? 0,
            $counts['kids_3_6'] ?? 0, $counts['kids_0_2'] ?? 0,
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

/** @return array<int, array<string, int>> slot_id → Anzahl je Altersgruppe */
function registration_slot_counts(int $registrationId): array
{
    $stmt = db()->prepare(
        'SELECT slot_id, adults, youth, kids_7_12, kids_3_6, kids_0_2 FROM registration_slots WHERE registration_id = ?'
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
 * zuerst. Zusätzliche Felder: person_count (inkl. 0–2), is_preferred_place,
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
    $places = event_places($eventId);
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
