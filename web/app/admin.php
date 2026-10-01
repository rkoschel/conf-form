<?php
declare(strict_types=1);

/**
 * Aufruf am Anfang jeder Admin-Seite. Der Zugriffsschutz selbst kommt per
 * Basic Auth aus admin/.htaccess.
 */
function admin_init(): void
{
    // Erst die Session: session_start() setzt selbst einen Cache-Control-Header
    session_start_once();
    // Teilnehmerdaten nicht im Browser- oder Proxy-Cache ablegen
    header('Cache-Control: no-store');
}

/**
 * Veranstaltung für Seiten mit Auswahl (?event=<id>): gewählte, sonst die
 * aktive, sonst die neueste. Unbekannte ID → 404.
 *
 * @param list<array<string, mixed>> $events aus event_list()
 * @return array<string, mixed>|null
 */
function admin_selected_event(array $events): ?array
{
    if (isset($_GET['event'])) {
        $event = event_find((int) $_GET['event']);
        if ($event === null) {
            abort(404, 'Veranstaltung nicht gefunden.');
        }
        return $event;
    }
    return event_active() ?? ($events ? event_find((int) $events[0]['id']) : null);
}

function admin_render(string $template, array $vars): void
{
    render('admin/' . $template, $vars + ['flashes' => flash_take()], 'admin_layout');
}
