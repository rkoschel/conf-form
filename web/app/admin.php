<?php
declare(strict_types=1);

/**
 * Aufruf am Anfang jeder Admin-Seite. Der Zugriffsschutz selbst kommt per
 * Basic Auth aus admin/.htaccess.
 */
function admin_init(): void
{
    // Erst die Session: session_start() setzt selbst einen Cache-Control-Header
    session_start_once();
    // Teilnehmerdaten nicht im Browser- oder Proxy-Cache ablegen
    header('Cache-Control: no-store');
}

function admin_render(string $template, array $vars): void
{
    render('admin/' . $template, $vars + ['flashes' => flash_take()], 'admin_layout');
}
