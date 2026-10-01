<?php
// Anfragen (SPEC §7.2, §7.3, §7.6)
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$events = event_list();
$event = admin_selected_event($events);

$status = isset($_GET['status']) && isset(STATUS_LABELS[$_GET['status']]) ? $_GET['status'] : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? normalize_line($_GET['q']) : '';

// Zurück zur Liste mit denselben Filtern
$listPath = 'admin/?' . http_build_query(array_filter([
    'event' => $event['id'] ?? null,
    'status' => $status,
    'q' => $search,
]));

if (is_post()) {
    csrf_check();
    $registration = registration_find((int) post_string('id'));
    if ($registration === null || $event === null || (int) $registration['event_id'] !== (int) $event['id']) {
        abort(404, 'Anmeldung nicht gefunden.');
    }
    $name = $registration['first_name'] . ' ' . $registration['last_name'];

    switch (post_string('action')) {
        case 'confirm':
        case 'reject':
            $newStatus = post_string('action') === 'confirm' ? 'confirmed' : 'rejected';
            $exceeds = $newStatus === 'confirmed' && registration_exceeds_quota($event, $registration);
            registration_set_status((int) $registration['id'], $newStatus);
            flash('success', "Anmeldung von $name: " . STATUS_LABELS[$newStatus] . '.');
            if ($exceeds) {
                flash('warning', 'Hinweis: Mit dieser Bestätigung ist das Kontingent überschritten.');
            }
            if (post_string('send_mail') === '1' && !$registration['no_email'] && $registration['email']) {
                $sent = mail_registration($newStatus, registration_find((int) $registration['id']), $event);
                $sent
                    ? flash('success', 'E-Mail an ' . $registration['email'] . ' gesendet.')
                    : flash('danger', 'E-Mail an ' . $registration['email'] . ' konnte nicht gesendet werden (siehe Mail-Status).');
            }
            break;
        case 'delete':
            registration_delete((int) $registration['id']);
            flash('success', "Anmeldung von $name wurde gelöscht.");
            break;
        default:
            abort(400, 'Unbekannte Aktion.');
    }
    redirect($listPath);
}

$rows = $event !== null ? registration_list((int) $event['id'], $status, $search) : [];
if ($event !== null) {
    $mailStatus = mail_status_for_event((int) $event['id']);
    foreach ($rows as &$row) {
        // Für den Bestätigen-Dialog: würde die Bestätigung das Kontingent überschreiten?
        $row['exceeds_quota'] = $row['status'] !== 'confirmed' && registration_exceeds_quota($event, $row);
        $row['mail'] = $mailStatus[(int) $row['id']] ?? null;
    }
    unset($row);
}

admin_render('registrations', [
    'title' => 'Anfragen',
    'active' => 'admin/',
    'events' => $events,
    'event' => $event,
    'rows' => $rows,
    'status' => $status,
    'search' => $search,
]);
