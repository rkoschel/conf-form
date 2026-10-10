<?php
// Einstellungen (SPEC §7.5): bevorzugte Orte, einstellbare Texte, Mailversand-Test, Team-Zugang (§7.8)
require __DIR__ . '/../app/bootstrap.php';
admin_init();

$mailTest = null;
$mailTestTo = '';
$notify = admin_notify_settings();
$notifyError = null;
$mailTab = false;
if (is_post()) {
    csrf_check();

    // Team-Zugang: eigene Formulare, dürfen die Texte nicht anfassen
    switch (post_string('action')) {
        case 'team_generate':
            $hadKey = team_key() !== '';
            team_key_generate();
            flash('success', $hadKey ? 'Neuer Team-Link erzeugt, der alte ist ungültig.' : 'Team-Link erzeugt.');
            redirect('admin/settings.php#team');
        case 'team_disable':
            team_key_disable();
            flash('success', 'Team-Zugang deaktiviert.');
            redirect('admin/settings.php#team');
        case 'mail_test':
            // Ergebnis direkt anzeigen (kein Redirect): das Protokoll wird nicht gespeichert
            $mailTestTo = post_string('mail_test_to');
            $mailTest = filter_var($mailTestTo, FILTER_VALIDATE_EMAIL)
                ? mail_test($mailTestTo)
                : ['ok' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse angeben.', 'log' => []];
            $mailTab = true;
            break;
        case 'admin_notify_save':
            $notify = ['enabled' => isset($_POST['admin_notify']), 'email' => post_string('admin_notify_email')];
            $notifyError = admin_notify_validate($notify['enabled'], $notify['email']);
            if ($notifyError === null) {
                admin_notify_set($notify['enabled'], $notify['email']);
                flash('success', $notify['enabled']
                    ? 'Benachrichtigung bei neuen Anmeldungen eingeschaltet.'
                    : 'Benachrichtigung bei neuen Anmeldungen ausgeschaltet.');
                redirect('admin/settings.php#mailversand');
            }
            $mailTab = true;
            break;
    }
}

if (is_post() && !$mailTab) {
    preferred_places_set(places_parse(post_string('preferred_places')));
    foreach (array_keys(SETTING_TEXTS) as $key) {
        setting_text_set($key, post_string($key));
    }
    flash('success', 'Einstellungen gespeichert.');
    // zurück in den Tab, aus dem gespeichert wurde
    $tab = post_string('tab');
    redirect('admin/settings.php' . (preg_match('/^[a-z0-9-]+$/', $tab) ? '#' . $tab : ''));
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
    'mailTest' => $mailTest,
    'mailTab' => $mailTab,
    'notify' => $notify,
    'notifyError' => $notifyError,
    'mailTestTo' => $mailTestTo,
    'mailConfig' => mail_config_summary(),
]);
