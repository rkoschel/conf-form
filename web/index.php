<?php
require __DIR__ . '/app/bootstrap.php';

$event = event_active();

if ($event === null) {
    render('info_inactive', ['title' => 'Konferenz', 'text' => setting_get('inactive_text')]);
    exit;
}

render('info', [
    'title' => $event['title'],
    'event' => $event,
    'registrationOpen' => event_registration_open($event),
]);
