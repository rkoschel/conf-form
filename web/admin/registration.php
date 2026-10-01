<?php
// Anmeldung bearbeiten (SPEC §7.4)
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$registration = registration_find((int) ($_GET['id'] ?? 0));
if ($registration === null) {
    abort(404, 'Anmeldung nicht gefunden.');
}
$id = (int) $registration['id'];
$event = event_find((int) $registration['event_id']);

$errors = [];

if (is_post()) {
    csrf_check();
    $form = $_POST;
    [$data, $errors] = registration_validate($_POST, $event['slots']);
    $status = post_string('status');
    if (!isset(STATUS_LABELS[$status])) {
        $errors['status'] = 'Bitte einen Status auswählen.';
    }
    if (!$errors) {
        registration_update($id, $data, $status);
        flash('success', 'Anmeldung von ' . $data['first_name'] . ' ' . $data['last_name'] . ' gespeichert.');
        if ($status === 'confirmed' && registration_exceeds_quota($event, $data + ['id' => $id])) {
            flash('warning', 'Hinweis: Mit dieser Anmeldung ist das Kontingent überschritten.');
        }
        redirect('admin/?event=' . (int) $event['id']);
    }
} else {
    $form = registration_form_values($registration, registration_slot_counts($id));
}

admin_render('registration_form', [
    'title' => 'Anmeldung bearbeiten',
    'active' => 'admin/',
    'event' => $event,
    'registration' => $registration,
    'form' => $form,
    'errors' => $errors,
]);
