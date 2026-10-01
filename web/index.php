<?php
require __DIR__ . '/app/bootstrap.php';

$event = db()->query('SELECT * FROM events WHERE active = 1')->fetch();

if (!$event) {
    $text = db()->query("SELECT value FROM settings WHERE key = 'inactive_text'")->fetchColumn();
    render('info_inactive', ['title' => 'Konferenz', 'text' => (string) $text]);
    exit;
}

render('placeholder', ['title' => $event['title']]);
