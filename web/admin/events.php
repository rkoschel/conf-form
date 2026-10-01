<?php
require __DIR__ . '/../app/bootstrap.php';

render('placeholder', ['title' => 'Veranstaltungen', 'active' => 'admin/events.php'], 'admin_layout');
