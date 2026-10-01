<?php
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$event = $id !== null ? event_find($id) : null;
if ($id !== null && $event === null) {
    abort(404, 'Veranstaltung nicht gefunden.');
}
// Ablauf ist gesperrt, sobald es Anmeldungen gibt (SPEC §7.1)
$slotsLocked = $id !== null && event_has_registrations($id);
$lockedSlots = $slotsLocked
    ? array_map(fn ($s) => ['time' => $s['time'], 'label' => $s['label']], $event['slots'])
    : null;

$errors = [];

if (is_post()) {
    csrf_check();
    $form = [
        'title' => post_string('title'),
        'date' => post_string('date'),
        'location' => post_string('location'),
        'description' => post_string('description'),
        'registration_deadline' => post_string('registration_deadline'),
        'timezone' => post_string('timezone'),
        'max_participants' => post_string('max_participants'),
        'organizer_name' => post_string('organizer_name'),
        'organizer_email' => post_string('organizer_email'),
        'active' => isset($_POST['active']),
        'slots' => post_rows('slots', ['time', 'label']),
        'places' => post_string('places'),
    ];
    [$data, $errors] = event_validate($form, $lockedSlots);
    if (!$errors) {
        event_save($id, $data);
        flash('success', $id === null ? 'Veranstaltung angelegt.' : 'Veranstaltung gespeichert.');
        redirect('admin/events.php');
    }
} elseif ($event !== null) {
    $form = $event;
    $form['places'] = implode("\n", $event['places']);
} else {
    $form = ['timezone' => 'Europe/Berlin', 'active' => false, 'slots' => [], 'places' => ''];
}

if ($lockedSlots !== null) {
    $form['slots'] = $lockedSlots;
} elseif (!$form['slots']) {
    $form['slots'] = [['time' => '', 'label' => '']];
}

admin_render('event_form', [
    'title' => $id === null ? 'Neue Veranstaltung' : 'Veranstaltung bearbeiten',
    'active' => 'admin/events.php',
    'form' => $form,
    'errors' => $errors,
    'slotsLocked' => $slotsLocked,
]);
