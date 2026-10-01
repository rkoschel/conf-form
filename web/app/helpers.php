<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL innerhalb der App, z. B. url('register/') → /konferenz/register/ */
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

/** Einzeiliger Text: trim und Mehrfach-Leerzeichen zu einem zusammenfassen */
function normalize_line(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

/** Mehrzeiliger Text: einheitliche Zeilenumbrüche, trim */
function normalize_text(string $value): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", $value));
}

/** Eingabe TT.MM.JJJJ (auch 1.5.2027) → 2027-05-01, ungültig → null */
function parse_date_de(string $value): ?string
{
    if (!preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', trim($value), $m)
        || !checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
}

/** Eingabe HH:MM im 24-h-Format (auch 9:30) → 09:30, ungültig → null */
function parse_time(string $value): ?string
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
        return null;
    }
    return sprintf('%02d:%02d', $m[1], $m[2]);
}

/** 2027-05-01 → 01.05.2027 */
function format_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('d.m.Y') : $date;
}

/** 2027-05-01 → Samstag, 01.05.2027 */
function format_date_long(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? WEEKDAYS[(int) $parsed->format('w')] . ', ' . $parsed->format('d.m.Y') : $date;
}

/** 2027-04-15T23:59 → 15.04.2027, 23:59 Uhr */
function format_local_datetime(string $value): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    return $parsed ? $parsed->format('d.m.Y, H:i') . ' Uhr' : $value;
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
