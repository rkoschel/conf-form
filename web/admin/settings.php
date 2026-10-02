<?php
// Einstellungen (SPEC §7.5): bevorzugte Orte und einstellbare Texte
require __DIR__ . '/../app/bootstrap.php';
admin_init();

if (is_post()) {
    csrf_check();
    preferred_places_set(places_parse(post_string('preferred_places')));
    foreach (array_keys(SETTING_TEXTS) as $key) {
        setting_text_set($key, post_string($key));
    }
    flash('success', 'Einstellungen gespeichert.');
    redirect('admin/settings.php');
}

// Eigene Texte; leer = Standardtext (wird im Feld grau als placeholder gezeigt)
$values = [];
foreach (SETTING_TEXTS as $key => $text) {
    $value = setting_get($key);
    $values[$key] = $value === $text['default'] ? '' : $value;
}

admin_render('settings', [
    'title' => 'Einstellungen',
    'active' => 'admin/settings.php',
    'places' => implode("\n", preferred_places()),
    'values' => $values,
]);
