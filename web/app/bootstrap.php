<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);

require APP_DIR . '/helpers.php';
require APP_DIR . '/db.php';
require APP_DIR . '/csrf.php';
require APP_DIR . '/forms.php';
require APP_DIR . '/settings.php';
require APP_DIR . '/events.php';
require APP_DIR . '/admin.php';

function config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $path = getenv('CONF_FORM_CONFIG') ?: '/home/conf-form/config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit('Konfiguration nicht gefunden.');
        }
        $config = require $path;
        if (strlen($config['app_secret'] ?? '') < 32) {
            http_response_code(500);
            exit('app_secret fehlt oder ist zu kurz.');
        }
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');

// Intern wird mit UTC gerechnet; Anzeige erfolgt in der Zeitzone des Events.
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    // Verhindert, dass der Absage-Token per Referer an fremde Seiten geht
    header('Referrer-Policy: same-origin');
}
