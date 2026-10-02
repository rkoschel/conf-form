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
    ? array_map(fn ($s) => ['time' => $s['time'], 'label' => $s['label'], 'childcare' => $s['childcare']], $event['slots'])
    : null;

$errors = [];

if (is_post()) {
    csrf_check();
    $form = [
        'title' => post_string('title'),
        'date' => post_string('date'),
        'location' => post_string('location'),
        'description' => post_string('description'),
        'registration_deadline_date' => post_string('registration_deadline_date'),
        'registration_deadline_time' => post_string('registration_deadline_time'),
        'timezone' => post_string('timezone'),
        'max_participants' => post_string('max_participants'),
        'organizer_name' => post_string('organizer_name'),
        'organizer_email' => post_string('organizer_email'),
        'active' => isset($_POST['active']),
        'slots' => post_rows('slots', ['time', 'label', 'childcare'], ['childcare_groups']),
        'places' => post_string('places'),
    ];
    [$data, $errors] = event_validate($form, $lockedSlots);
    if (!$errors) {
        event_save($id, $data);
        flash('success', $id === null ? 'Veranstaltung angelegt.' : 'Veranstaltung gespeichert.');
        redirect('admin/events.php');
    }
} elseif ($event !== null) {
    // Gespeichert wird ISO, angezeigt TT.MM.JJJJ und HH:MM
    [$deadlineDate, $deadlineTime] = explode('T', $event['registration_deadline']);
    $form = $event;
    $form['date'] = format_date($event['date']);
    $form['registration_deadline_date'] = format_date($deadlineDate);
    $form['registration_deadline_time'] = $deadlineTime;
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
