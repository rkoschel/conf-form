<?php
require __DIR__ . '/../app/bootstrap.php';
admin_init();

if (is_post()) {
    csrf_check();
    $id = (int) post_string('id');
    $event = event_find($id);
    if ($event === null) {
        abort(404, 'Veranstaltung nicht gefunden.');
    }
    $title = '„' . $event['title'] . '“';

    switch (post_string('action')) {
        case 'activate':
            event_set_active($id, true);
            flash('success', "$title ist jetzt aktiv.");
            break;
        case 'deactivate':
            event_set_active($id, false);
            flash('success', "$title ist jetzt inaktiv.");
            break;
        case 'delete':
            event_delete($id);
            flash('success', "$title wurde gelöscht.");
            break;
        default:
            abort(400, 'Unbekannte Aktion.');
    }
    redirect('admin/events.php');
}

admin_render('events', [
    'title' => 'Veranstaltungen',
    'active' => 'admin/events.php',
    'events' => event_list(),
]);
