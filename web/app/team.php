<?php
declare(strict_types=1);

// Team-Zugang zur Auswertung per geheimem Link (SPEC §7.8)

/** Aktueller Schlüssel, '' = Team-Zugang deaktiviert */
function team_key(): string
{
    return setting_get('team_key');
}

/** Erzeugt einen neuen Schlüssel (160 Bit); ein alter Link wird ungültig. */
function team_key_generate(): string
{
    $key = bin2hex(random_bytes(20));
    setting_set('team_key', $key);
    return $key;
}

function team_key_disable(): void
{
    setting_set('team_key', '');
}

/** Passt der übergebene Schlüssel? Bei deaktiviertem Zugang nie. */
function team_key_valid(mixed $given): bool
{
    $key = team_key();
    return $key !== '' && is_string($given) && hash_equals($key, $given);
}

/**
 * Vollständiger Team-Link oder null, wenn deaktiviert. Im Browser aus der
 * aufgerufenen Adresse gebildet (passt so lokal wie auf dem Server), sonst
 * aus der Config app_url.
 */
function team_url(): ?string
{
    $key = team_key();
    if ($key === '') {
        return null;
    }
    $path = 'team/?k=' . $key;
    if (isset($_SERVER['HTTP_HOST'])) {
        return (is_https() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . url($path);
    }
    return app_url($path);
}
