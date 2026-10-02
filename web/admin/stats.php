<?php
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$events = event_list();

$event = admin_selected_event($events);

admin_render('stats', [
    'title' => 'Auswertungen',
    'active' => 'admin/stats.php',
    'events' => $events,
    'event' => $event,
    'stats' => $event !== null ? stats_for_event($event) : null,
]);
