<?php
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$event = $id !== null ? event_find($id) : null;
if ($id !== null && $event === null) {
    abort(404, 'Veranstaltung nicht gefunden.');
}
// Ablauf und Auswahl der Personengruppen sind gesperrt, sobald es Anmeldungen gibt (SPEC §7.1)
$slotsLocked = $id !== null && event_has_registrations($id);
$lockedGroups = $slotsLocked ? $event['groups'] : null;
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
        'allow_split' => isset($_POST['allow_split']),
        'slots' => post_rows('slots', ['time', 'label', 'childcare'], ['childcare_groups']),
        'person_groups' => array_values(array_filter((array) ($_POST['person_groups'] ?? []), 'is_string')),
        'group_names' => array_filter((array) ($_POST['group_names'] ?? []), 'is_string'),
    ];
    [$data, $errors] = event_validate($form, $lockedSlots, $lockedGroups);
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
    $form['person_groups'] = array_keys($event['groups']);
    $form['group_names'] = $event['groups'] + person_groups_default();
} else {
    // Neue Veranstaltung: Personengruppen der zuletzt angelegten übernehmen
    $lastGroups = event_last_groups();
    $form = [
        'timezone' => 'Europe/Berlin',
        'active' => false,
        'allow_split' => true,
        'slots' => [],
        'person_groups' => array_keys($lastGroups),
        'group_names' => $lastGroups + person_groups_default(),
    ];
}
if ($lockedGroups !== null) {
    $form['person_groups'] = array_keys($lockedGroups);
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
