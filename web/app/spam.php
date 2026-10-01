<?php
declare(strict_types=1);

// Spamschutz für das Anmeldeformular (SPEC §5.2): Honeypot, signierter
// Zeitstempel, Rate-Limit pro IP. Kein Captcha.

const SPAM_HONEYPOT_FIELD = 'website';
const SPAM_TIMESTAMP_FIELD = 'form_ts';
const SPAM_MIN_SECONDS = 3;
const SPAM_MAX_SECONDS = 2 * 60 * 60;

const SPAM_OK = 'ok';
const SPAM_HONEYPOT = 'honeypot';
const SPAM_TIMESTAMP = 'timestamp';
const SPAM_RATE_LIMIT = 'rate_limit';

/** Hidden-Felder für das Formular: Honeypot und signierter Zeitstempel */
function spam_fields(?int $now = null): string
{
    return '<div class="hp-field" aria-hidden="true">'
        . '<label for="' . SPAM_HONEYPOT_FIELD . '">Website</label>'
        . '<input type="text" id="' . SPAM_HONEYPOT_FIELD . '" name="' . SPAM_HONEYPOT_FIELD . '"'
        . ' value="" autocomplete="off" tabindex="-1">'
        . '</div>'
        . '<input type="hidden" name="' . SPAM_TIMESTAMP_FIELD . '" value="'
        . e(spam_timestamp($now ?? time())) . '">';
}

/** Zeitpunkt der Formularauslieferung + HMAC, z. B. 1790000000.ab12… */
function spam_timestamp(int $time): string
{
    return $time . '.' . spam_signature($time);
}

function spam_signature(int $time): string
{
    return hash_hmac('sha256', 'form-ts|' . $time, (string) config('app_secret'));
}

/** Signatur gültig und Formular zwischen 3 Sekunden und 2 Stunden alt? */
function spam_timestamp_valid(string $value, ?int $now = null): bool
{
    if (!preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $value, $m)) {
        return false;
    }
    $time = (int) $m[1];
    if (!hash_equals(spam_signature($time), $m[2])) {
        return false;
    }
    $age = ($now ?? time()) - $time;
    return $age >= SPAM_MIN_SECONDS && $age <= SPAM_MAX_SECONDS;
}

function spam_honeypot_filled(array $post): bool
{
    $value = $post[SPAM_HONEYPOT_FIELD] ?? '';
    return !is_string($value) || trim($value) !== '';
}

/** IP nur als HMAC speichern (ein einfacher Hash wäre bei IPv4 leicht umkehrbar) */
function ip_hash(string $ip): string
{
    return hash_hmac('sha256', 'ip|' . $ip, (string) config('app_secret'));
}

/**
 * Zählt einen Absendeversuch und prüft das Rate-Limit. Löscht vorher alle
 * Einträge außerhalb des Zeitfensters (kein Cron nötig).
 *
 * @return bool true, wenn das Limit überschritten ist
 */
function rate_limit_exceeded(string $ip, ?int $now = null): bool
{
    $limit = (array) config('rate_limit');
    $max = (int) ($limit['max_attempts'] ?? 10);
    $windowMinutes = (int) ($limit['window_minutes'] ?? 60);

    $now ??= time();
    $nowUtc = gmdate('Y-m-d\TH:i:s\Z', $now);
    $windowStart = gmdate('Y-m-d\TH:i:s\Z', $now - $windowMinutes * 60);
    $hash = ip_hash($ip);

    return db_transaction(function (PDO $pdo) use ($hash, $nowUtc, $windowStart, $max): bool {
        $pdo->prepare('DELETE FROM rate_limit WHERE created_at <= ?')->execute([$windowStart]);
        $pdo->prepare('INSERT INTO rate_limit (ip_hash, created_at) VALUES (?, ?)')
            ->execute([$hash, $nowUtc]);
        $count = $pdo->prepare('SELECT COUNT(*) FROM rate_limit WHERE ip_hash = ?');
        $count->execute([$hash]);
        return (int) $count->fetchColumn() > $max;
    });
}

/**
 * Alle Spamprüfungen für einen Absendeversuch. Reihenfolge: Rate-Limit
 * (zählt jeden POST), Honeypot, Zeitstempel.
 *
 * @return string SPAM_OK, SPAM_RATE_LIMIT, SPAM_HONEYPOT oder SPAM_TIMESTAMP
 */
function spam_check(array $post, string $ip, ?int $now = null): string
{
    if (rate_limit_exceeded($ip, $now)) {
        return SPAM_RATE_LIMIT;
    }
    if (spam_honeypot_filled($post)) {
        return SPAM_HONEYPOT;
    }
    $timestamp = $post[SPAM_TIMESTAMP_FIELD] ?? '';
    if (!is_string($timestamp) || !spam_timestamp_valid($timestamp, $now)) {
        return SPAM_TIMESTAMP;
    }
    return SPAM_OK;
}

/** Meldung für den Nutzer; null bei SPAM_OK und SPAM_HONEYPOT (Antwort wie bei Erfolg) */
function spam_message(string $result): ?string
{
    return match ($result) {
        SPAM_TIMESTAMP => 'Bitte Formular neu laden und erneut absenden.',
        SPAM_RATE_LIMIT => 'Zu viele Anfragen von deinem Anschluss. Bitte versuche es später erneut.',
        default => null,
    };
}
