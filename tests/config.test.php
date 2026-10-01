<?php
// Konfiguration für Tests. Enthält keine echten Zugangsdaten.
// Das Arbeitsverzeichnis (DB, Mail-Log) setzt tests/bootstrap.php bzw.
// HttpTestCase über CONF_FORM_TEST_DIR.

$dir = getenv('CONF_FORM_TEST_DIR') ?: sys_get_temp_dir() . '/conf-form-test';

return [
    'base_url' => '',
    'db_path' => $dir . '/test.sqlite',
    'app_secret' => 'test-only-secret-never-use-in-production',
    'debug' => true,
    'mail_transport' => 'log',
    'mail_log_file' => $dir . '/mail.log',
    'smtp' => [
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => 'test@example.org',
        'from_name' => 'Test',
    ],
    'privacy_url' => 'https://example.org/datenschutz/',
    'info_url' => 'https://example.org/info/',
    'rate_limit' => [
        'max_attempts' => 10,
        'window_minutes' => 60,
    ],
];
