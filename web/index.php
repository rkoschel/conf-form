<?php
require __DIR__ . '/app/bootstrap.php';

$event = event_active();

if ($event === null) {
    render('info_inactive', ['title' => 'Konferenz', 'text' => setting_text('inactive_text')]);
    exit;
}

$registrationOpen = event_registration_open($event);
$shares = null;
if ($registrationOpen) {
    $stats = stats_for_event($event);
    $shares = quota_shares($stats['quota_used'], $stats['quota_pending'], $stats['quota_max']);
}

render('info', [
    'title' => $event['title'],
    'event' => $event,
    'registrationOpen' => $registrationOpen,
    'shares' => $shares,
]);
