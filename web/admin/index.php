<?php
// Startseite des Admin-Bereichs: Auswertungen (SPEC §7.9). Die Anfragen liegen unter registrations.php.
require __DIR__ . '/../app/bootstrap.php';
admin_init();

redirect('admin/stats.php');
