<?php
// Absage per Link aus der Mail (SPEC §6). GET zeigt nur an, abgesagt wird per POST.
require __DIR__ . '/../app/bootstrap.php';

$token = is_post() ? post_string('t') : (is_string($_GET['t'] ?? null) ? $_GET['t'] : '');
$registration = registration_find_by_token($token);
$event = $registration ? event_find((int) $registration['event_id']) : null;

if ($registration === null || $event === null) {
    render('cancel', ['title' => 'Teilnahme absagen', 'registration' => null]);
    exit;
}

if (is_post()) {
    csrf_check();
    registration_cancel((int) $registration['id']);
    redirect('cancel/?t=' . $token . '&done=1');
}

render('cancel', [
    'title' => 'Teilnahme absagen',
    'registration' => $registration,
    'event' => $event,
    'token' => $token,
    'done' => isset($_GET['done']) && $registration['status'] === 'cancelled',
]);
