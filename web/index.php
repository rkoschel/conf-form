<?php
require __DIR__ . '/app/bootstrap.php';

$event = db()->query('SELECT * FROM events WHERE active = 1')->fetch();

if (!$event) {
    render('info_inactive', ['title' => 'Konferenz', 'text' => setting_get('inactive_text')]);
    exit;
}

render('placeholder', ['title' => $event['title']]);
