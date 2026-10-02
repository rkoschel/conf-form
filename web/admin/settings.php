<?php
// Einstellungen (SPEC §7.5): bevorzugte Orte, einstellbare Texte, Team-Zugang (§7.8)
require __DIR__ . '/../app/bootstrap.php';
admin_init();

if (is_post()) {
    csrf_check();

    // Team-Zugang: eigene Formulare, dürfen die Texte nicht anfassen
    switch (post_string('action')) {
        case 'team_generate':
            $hadKey = team_key() !== '';
            team_key_generate();
            flash('success', $hadKey ? 'Neuer Team-Link erzeugt, der alte ist ungültig.' : 'Team-Link erzeugt.');
            redirect('admin/settings.php');
        case 'team_disable':
            team_key_disable();
            flash('success', 'Team-Zugang deaktiviert.');
            redirect('admin/settings.php');
    }

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
    'teamUrl' => team_url(),
]);
