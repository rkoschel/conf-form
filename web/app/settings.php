<?php
declare(strict_types=1);

function setting_get(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function setting_set(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT (key) DO UPDATE SET value = excluded.value'
    )->execute([$key, $value]);
}
