<?php
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$errors = [];
$text = setting_get('inactive_text');

if (is_post()) {
    csrf_check();
    $text = normalize_text(post_string('inactive_text'));
    if ($text === '') {
        $errors['inactive_text'] = 'Bitte einen Text angeben.';
    } else {
        setting_set('inactive_text', $text);
        flash('success', 'Einstellungen gespeichert.');
        redirect('admin/settings.php');
    }
}

admin_render('settings', [
    'title' => 'Einstellungen',
    'active' => 'admin/settings.php',
    'text' => $text,
    'errors' => $errors,
]);
