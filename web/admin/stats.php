<?php
require __DIR__ . '/../app/bootstrap.php';

render('placeholder', ['title' => 'Auswertung', 'active' => 'admin/stats.php'], 'admin_layout');
