<?php
declare(strict_types=1);

/** Einzelner POST-Wert als String (Arrays u. Ä. werden zu '') */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/**
 * POST-Werte aus Zeilen wie name[0][time], name[0][label].
 *
 * @param list<string> $keys
 * @return list<array<string, string>>
 */
function post_rows(string $name, array $keys): array
{
    $rows = [];
    foreach ((array) ($_POST[$name] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $clean = [];
        foreach ($keys as $key) {
            $clean[$key] = is_string($row[$key] ?? null) ? $row[$key] : '';
        }
        $rows[] = $clean;
    }
    return $rows;
}

/** CSS-Klasse für ein fehlerhaftes Feld */
function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/** Fehlermeldung unter einem Feld (Bootstrap invalid-feedback) */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
        : '';
}

/** Meldung für die nächste Seite (nach Redirect) */
function flash(string $type, string $message): void
{
    session_start_once();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> */
function flash_take(): array
{
    session_start_once();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}
