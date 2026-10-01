<?php
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$events = event_list();

// Gewählte Veranstaltung, sonst die aktive, sonst die neueste
if (isset($_GET['event'])) {
    $event = event_find((int) $_GET['event']);
    if ($event === null) {
        abort(404, 'Veranstaltung nicht gefunden.');
    }
} else {
    $event = event_active() ?? ($events ? event_find((int) $events[0]['id']) : null);
}

admin_render('stats', [
    'title' => 'Auswertung',
    'active' => 'admin/stats.php',
    'events' => $events,
    'event' => $event,
    'stats' => $event !== null ? stats_for_event($event) : null,
]);
