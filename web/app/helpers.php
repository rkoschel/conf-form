<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL innerhalb der App, z. B. url('anmelden/') → /konferenz/anmelden/ */
function url(string $path = ''): string
{
    return rtrim((string) config('base_url'), '/') . '/' . ltrim($path, '/');
}

/** URL einer Datei unter assets/, mit Cache-Busting über die Änderungszeit */
function asset(string $path): string
{
    $file = APP_DIR . '/../assets/' . $path;
    $version = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . $path) . '?v=' . $version;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function now_utc(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/**
 * Rendert templates/$template.php in ein Layout.
 * Variablen aus $vars stehen im Template und im Layout zur Verfügung.
 */
function render(string $template, array $vars = [], string $layout = 'layout'): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    require APP_DIR . '/templates/' . $layout . '.php';
}

function abort(int $status, string $message): never
{
    http_response_code($status);
    render('error', ['title' => 'Fehler', 'message' => $message]);
    exit;
}
