<?php
// Team-Zugang zur Auswertung per geheimem Link (SPEC §7.8): nur lesend, nur
// die aktive Veranstaltung. Ohne gültigen Schlüssel 404 (Seite „existiert nicht“).
require __DIR__ . '/../app/bootstrap.php';

if (!team_key_valid($_GET['k'] ?? null)) {
    abort(404, 'Seite nicht gefunden.');
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$event = event_active();

render('team', [
    'title' => $event !== null ? 'Auswertung: ' . $event['title'] : 'Auswertung',
    'event' => $event,
    'stats' => $event !== null ? stats_for_event($event) : null,
    'emptyText' => 'Derzeit ist keine Veranstaltung aktiv.',
    'wide' => true,
]);
