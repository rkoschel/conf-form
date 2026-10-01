<?php
declare(strict_types=1);

/**
 * Prüft und normalisiert die Eingaben des Veranstaltungs-Formulars.
 *
 * $input: title, date (TT.MM.JJJJ), location, description,
 * registration_deadline_date (TT.MM.JJJJ), registration_deadline_time
 * (HH:MM, 24 h), timezone, max_participants, organizer_name,
 * organizer_email (Strings), active (bool), slots (Liste von
 * ['time' => HH:MM, 'label' => …]), places (String, ein Ort pro Zeile).
 * Die Daten werden als ISO zurückgegeben (date: Y-m-d,
 * registration_deadline: Y-m-d\TH:i).
 *
 * Ist der Ablauf gesperrt (es gibt Anmeldungen), wird $lockedSlots
 * übergeben: Eingegebene Programmpunkte werden dann ignoriert und die
 * bestehenden für die Fristprüfung verwendet.
 *
 * @param list<array{time: string, label: string}>|null $lockedSlots
 * @return array{0: array<string, mixed>, 1: array<string, string>} [Daten, Fehler je Feld]
 */
function event_validate(array $input, ?array $lockedSlots = null): array
{
    $errors = [];
    $data = [
        'title' => normalize_line((string) ($input['title'] ?? '')),
        'date' => parse_date_de((string) ($input['date'] ?? '')),
        'location' => normalize_line((string) ($input['location'] ?? '')),
        'description' => normalize_text((string) ($input['description'] ?? '')),
        'timezone' => trim((string) ($input['timezone'] ?? '')),
        'max_participants' => trim((string) ($input['max_participants'] ?? '')),
        'organizer_name' => normalize_line((string) ($input['organizer_name'] ?? '')),
        'organizer_email' => trim((string) ($input['organizer_email'] ?? '')),
        'active' => !empty($input['active']),
    ];

    foreach (['title' => 'Titel', 'location' => 'Ort'] as $field => $label) {
        if ($data[$field] === '') {
            $errors[$field] = "$label ist erforderlich.";
        }
    }

    if ($data['date'] === null) {
        $errors['date'] = 'Bitte ein gültiges Datum im Format TT.MM.JJJJ angeben.';
    }
    $deadlineDate = parse_date_de((string) ($input['registration_deadline_date'] ?? ''));
    $deadlineTime = parse_time((string) ($input['registration_deadline_time'] ?? ''));
    $data['registration_deadline'] = $deadlineDate !== null && $deadlineTime !== null
        ? $deadlineDate . 'T' . $deadlineTime
        : null;
    if ($data['registration_deadline'] === null) {
        $errors['registration_deadline'] = 'Bitte Datum (TT.MM.JJJJ) und Uhrzeit (HH:MM) der Anmeldefrist angeben.';
    }
    if (!in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
        $errors['timezone'] = 'Bitte eine Zeitzone auswählen.';
    }
    if (!ctype_digit($data['max_participants']) || (int) $data['max_participants'] < 1) {
        $errors['max_participants'] = 'Bitte eine ganze Zahl ab 1 angeben.';
    } else {
        $data['max_participants'] = (int) $data['max_participants'];
    }
    if ($data['organizer_email'] !== '' && !filter_var($data['organizer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['organizer_email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }

    if ($lockedSlots !== null) {
        $data['slots'] = $lockedSlots;
    } else {
        [$data['slots'], $slotError] = event_parse_slots($input['slots'] ?? []);
        if ($slotError !== null) {
            $errors['slots'] = $slotError;
        }
    }

    $data['places'] = event_parse_places((string) ($input['places'] ?? ''));

    // Frist spätestens zum Beginn des ersten Programmpunkts (ohne Ablauf:
    // am Veranstaltungstag 23:59). Gleiche Zeitzone → Textvergleich genügt.
    if (!isset($errors['date']) && !isset($errors['registration_deadline']) && !isset($errors['slots'])) {
        $firstTime = $data['slots'][0]['time'] ?? '23:59';
        $latest = $data['date'] . 'T' . $firstTime;
        if ($data['registration_deadline'] > $latest) {
            $errors['registration_deadline'] = 'Die Anmeldefrist darf spätestens am '
                . format_local_datetime($latest) . ' enden'
                . ($data['slots'] ? ' (Beginn des ersten Programmpunkts).' : ' (Veranstaltungstag).');
        }
    }

    return [$data, $errors];
}

/**
 * @return array{0: list<array{time: string, label: string}>, 1: ?string} [Programmpunkte nach Uhrzeit, Fehler]
 */
function event_parse_slots(mixed $rows): array
{
    $slots = [];
    $invalid = [];
    $number = 0;
    foreach (is_array($rows) ? $rows : [] as $row) {
        $number++;
        $rawTime = trim((string) ($row['time'] ?? ''));
        $label = normalize_line((string) ($row['label'] ?? ''));
        if ($rawTime === '' && $label === '') {
            continue;
        }
        $time = parse_time($rawTime);
        if ($time === null || $label === '') {
            $invalid[] = $number;
            continue;
        }
        $slots[] = ['time' => $time, 'label' => $label];
    }

    if ($invalid) {
        return [$slots, 'Programmpunkt ' . implode(', ', $invalid) . ': Uhrzeit (HH:MM) und Bezeichnung angeben.'];
    }

    usort($slots, fn ($a, $b) => strcmp($a['time'], $b['time']));
    return [$slots, null];
}

/** @return list<string> Orte, normalisiert und ohne Dubletten (Groß-/Kleinschreibung egal) */
function event_parse_places(string $text): array
{
    $places = [];
    foreach (explode("\n", normalize_text($text)) as $line) {
        $place = normalize_line($line);
        if ($place !== '') {
            $places[mb_strtolower($place)] ??= $place;
        }
    }
    return array_values($places);
}

/**
 * Speichert eine Veranstaltung samt Ablauf und bevorzugten Orten.
 * Der Ablauf wird nur geändert, solange es keine Anmeldungen gibt.
 *
 * @param array<string, mixed> $data Ergebnis von event_validate()
 */
function event_save(?int $id, array $data): int
{
    return db_transaction(function (PDO $pdo) use ($id, $data): int {
        $fields = [
            'title' => $data['title'],
            'date' => $data['date'],
            'location' => $data['location'],
            'description' => $data['description'],
            'registration_deadline' => $data['registration_deadline'],
            'timezone' => $data['timezone'],
            'max_participants' => $data['max_participants'],
            'organizer_name' => $data['organizer_name'],
            'organizer_email' => $data['organizer_email'],
            'active' => $data['active'] ? 1 : 0,
        ];

        // Vorher alle anderen deaktivieren, sonst greift der Unique-Index
        if ($data['active']) {
            $pdo->prepare('UPDATE events SET active = 0 WHERE id IS NOT ?')->execute([$id]);
        }

        if ($id === null) {
            $columns = implode(', ', array_keys($fields));
            $params = implode(', ', array_map(fn ($k) => ':' . $k, array_keys($fields)));
            $pdo->prepare("INSERT INTO events ($columns) VALUES ($params)")->execute($fields);
            $id = (int) $pdo->lastInsertId();
        } else {
            $set = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($fields)));
            $pdo->prepare("UPDATE events SET $set WHERE id = :id")->execute($fields + ['id' => $id]);
        }

        if (!event_has_registrations($id)) {
            $pdo->prepare('DELETE FROM event_slots WHERE event_id = ?')->execute([$id]);
            $insert = $pdo->prepare('INSERT INTO event_slots (event_id, time, label, sort) VALUES (?, ?, ?, ?)');
            foreach ($data['slots'] as $sort => $slot) {
                $insert->execute([$id, $slot['time'], $slot['label'], $sort]);
            }
        }

        $pdo->prepare('DELETE FROM preferred_places WHERE event_id = ?')->execute([$id]);
        $insert = $pdo->prepare('INSERT INTO preferred_places (event_id, name) VALUES (?, ?)');
        foreach ($data['places'] as $place) {
            $insert->execute([$id, $place]);
        }

        return $id;
    });
}

/** @return array<string, mixed>|null Veranstaltung mit 'slots' und 'places' */
function event_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    if (!$event) {
        return null;
    }
    $event['slots'] = event_slots($id);
    $event['places'] = event_places($id);
    return $event;
}

/** @return array<string, mixed>|null die aktive Veranstaltung mit 'slots' und 'places' */
function event_active(): ?array
{
    $id = db()->query('SELECT id FROM events WHERE active = 1')->fetchColumn();
    return $id === false ? null : event_find((int) $id);
}

/**
 * Ist die Anmeldefrist noch nicht abgelaufen? Ausgewertet in der Zeitzone
 * der Veranstaltung; die Minute der Frist zählt noch mit (23:59 → bis 23:59:59).
 *
 * @param array<string, mixed> $event
 */
function event_registration_open(array $event, ?DateTimeImmutable $now = null): bool
{
    $now ??= new DateTimeImmutable();
    $local = $now->setTimezone(new DateTimeZone((string) $event['timezone']))->format('Y-m-d\TH:i');
    return $local <= $event['registration_deadline'];
}

/** @return list<array{id: int, time: string, label: string}> */
function event_slots(int $eventId): array
{
    $stmt = db()->prepare('SELECT id, time, label FROM event_slots WHERE event_id = ? ORDER BY sort, time');
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/** @return list<string> */
function event_places(int $eventId): array
{
    $stmt = db()->prepare('SELECT name FROM preferred_places WHERE event_id = ? ORDER BY name COLLATE NOCASE');
    $stmt->execute([$eventId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** @return list<array<string, mixed>> alle Veranstaltungen, neueste zuerst, mit registration_count */
function event_list(): array
{
    return db()->query(
        'SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registration_count
         FROM events e ORDER BY e.date DESC, e.id DESC'
    )->fetchAll();
}

function event_has_registrations(int $eventId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM registrations WHERE event_id = ? LIMIT 1');
    $stmt->execute([$eventId]);
    return (bool) $stmt->fetchColumn();
}

/** Aktiviert eine Veranstaltung (alle anderen werden deaktiviert) oder deaktiviert sie. */
function event_set_active(int $id, bool $active): void
{
    db_transaction(function (PDO $pdo) use ($id, $active): void {
        if ($active) {
            $pdo->exec('UPDATE events SET active = 0 WHERE active = 1');
        }
        $pdo->prepare('UPDATE events SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    });
}

/** Löscht eine Veranstaltung inkl. aller zugehörigen Daten (ON DELETE CASCADE). */
function event_delete(int $id): void
{
    db()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
}

/** @return array<string, list<string>> Zeitzonen gruppiert nach Region */
function timezone_options(): array
{
    $groups = [];
    foreach (DateTimeZone::listIdentifiers() as $tz) {
        $region = str_contains($tz, '/') ? strstr($tz, '/', true) : 'Sonstige';
        $groups[$region][] = $tz;
    }
    return $groups;
}
