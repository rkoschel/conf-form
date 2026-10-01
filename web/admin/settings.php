<?php
require __DIR__ . '/../app/bootstrap.php';

render('placeholder', ['title' => 'Einstellungen', 'active' => 'admin/settings.php'], 'admin_layout');
