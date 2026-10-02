<?php
// Hilfe für Admins: erklärt die Logik im Hintergrund (SPEC §7.9). Feste Texte.
require __DIR__ . '/../app/bootstrap.php';
admin_init();

admin_render('help', [
    'title' => 'Hilfe',
    'active' => 'admin/help.php',
]);
