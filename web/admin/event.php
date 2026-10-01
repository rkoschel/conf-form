<?php
require __DIR__ . '/../app/bootstrap.php';

render('placeholder', ['title' => 'Veranstaltung', 'active' => 'admin/events.php'], 'admin_layout');
