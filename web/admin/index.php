<?php
require __DIR__ . '/../app/bootstrap.php';

render('placeholder', ['title' => 'Anfragen', 'active' => 'admin/'], 'admin_layout');
