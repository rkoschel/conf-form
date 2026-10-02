<?php
declare(strict_types=1);

/** Platzhalter in Mail-Texten (englisch) → Beschreibung für die Einstellungsseite */
const MAIL_PLACEHOLDERS = [
    '{first_name}' => 'Vorname',
    '{last_name}' => 'Nachname',
    '{title}' => 'Titel der Veranstaltung',
    '{date}' => 'Datum der Veranstaltung',
    '{location}' => 'Ort der Veranstaltung',
    '{organizer}' => 'Veranstalter (sonst Ersatz-Unterschrift)',
];

/**
 * Einstellbare Texte (SPEC §7.5): Schlüssel → Abschnitt, Bezeichnung,
 * Standardtext. Ein leerer Eintrag in `settings` bedeutet: Standardtext.
 * 'line' = einzeiliges Feld, 'mail' = Platzhalter aus MAIL_PLACEHOLDERS.
 */
const SETTING_TEXTS = [
    'inactive_text' => [
        'section' => 'Allgemein',
        'label' => 'Text, wenn keine Veranstaltung aktiv ist',
        'help' => 'Auf der Infoseite und der Anmeldeseite.',
        'default' => 'Derzeit ist keine Anmeldung möglich.',
    ],
    'congregation_label' => [
        'section' => 'Anmeldeformular',
        'label' => 'Bezeichnung des Feldes „Heimatversammlung“',
        'default' => 'Heimatversammlung',
        'line' => true,
    ],
    'attendance_hint' => [
        'section' => 'Anmeldeformular',
        'label' => 'Hinweis unter „Voraussichtliche Anwesenheit“',
        'default' => 'Deine Angaben helfen uns, die Räumlichkeiten besser zu nutzen und möglichst vielen die Teilnahme zu ermöglichen.',
    ],
    'fully_booked_hint' => [
        'section' => 'Anmeldeformular',
        'label' => 'Hinweis, wenn die Veranstaltung ausgebucht ist',
        'help' => 'Auf der Infoseite unter der Belegung.',
        'default' => "Aktuell scheint die Veranstaltung ausgebucht zu sein.\n"
            . "Eine Anmeldung lohnt sich trotzdem, um auf die Warteliste zu kommen.\n"
            . 'Sobald Plätze wieder frei sind (durch Absagen oder Änderungen), melden wir uns bei dir.',
    ],
    'result_confirmed' => [
        'section' => 'Nach dem Absenden',
        'label' => 'Text bei „Anmeldung bestätigt“',
        'default' => 'Vielen Dank! Deine Teilnahme ist bestätigt.',
    ],
    'result_waitlist' => [
        'section' => 'Nach dem Absenden',
        'label' => 'Text bei „Du stehst auf der Warteliste“',
        'default' => 'Vielen Dank für deine Anmeldung. Sobald ein Platz für dich frei ist, melden wir uns bei dir.',
    ],
    'result_mail_hint' => [
        'section' => 'Nach dem Absenden',
        'label' => 'Hinweis zur Bestätigungs-Mail',
        'default' => "Falls du eine E-Mail-Adresse angegeben hast, bekommst du eine Bestätigung per E-Mail.\n"
            . 'Darin findest du auch einen Link, mit dem du wieder absagen kannst.',
    ],
    'cancel_done' => [
        'section' => 'Absage',
        'label' => 'Text nach erfolgreicher Absage',
        'default' => 'Deine Teilnahme ist abgesagt. Danke, dass du Bescheid gegeben hast.',
    ],
    'mail_intro_received_confirmed' => [
        'section' => 'E-Mails',
        'label' => 'Eingang, bestätigt',
        'default' => "Hallo {first_name},\n\nvielen Dank für deine Anmeldung. Deine Teilnahme ist bestätigt.",
        'mail' => true,
    ],
    'mail_intro_received_waitlist' => [
        'section' => 'E-Mails',
        'label' => 'Eingang, Warteliste',
        'default' => "Hallo {first_name},\n\nvielen Dank für deine Anmeldung. Du stehst zurzeit auf der Warteliste.\n"
            . 'Sobald ein Platz für dich frei ist, melden wir uns bei dir.',
        'mail' => true,
    ],
    'mail_intro_confirmed' => [
        'section' => 'E-Mails',
        'label' => 'Bestätigung durch den Admin',
        'default' => "Hallo {first_name},\n\ngute Nachricht: Deine Teilnahme ist jetzt bestätigt.",
        'mail' => true,
    ],
    'mail_intro_rejected' => [
        'section' => 'E-Mails',
        'label' => 'Absage durch den Admin',
        'default' => "Hallo {first_name},\n\nleider können wir deine Anmeldung zu folgender Veranstaltung nicht\n"
            . 'berücksichtigen. Wir bitten um dein Verständnis.',
        'mail' => true,
    ],
    'mail_cancel_hint' => [
        'section' => 'E-Mails',
        'label' => 'Hinweis vor dem Absage-Link (bestätigt)',
        'default' => "Falls du doch nicht kommen kannst, sag bitte über diesen Link ab,\ndamit dein Platz frei wird:",
        'mail' => true,
    ],
    'mail_cancel_hint_waitlist' => [
        'section' => 'E-Mails',
        'label' => 'Hinweis vor dem Absage-Link (Warteliste)',
        'default' => 'Falls du deine Anfrage zurückziehen möchtest, nutze bitte diesen Link:',
        'mail' => true,
    ],
    'mail_signature' => [
        'section' => 'E-Mails',
        'label' => 'Grußformel',
        'default' => "Viele Grüße\n{organizer}",
        'mail' => true,
    ],
    'mail_organizer_fallback' => [
        'section' => 'E-Mails',
        'label' => 'Ersatz-Unterschrift, wenn kein Veranstalter-Name eingetragen ist',
        'default' => 'Das Vorbereitungsteam',
        'line' => true,
    ],
];

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

/**
 * Einstellbarer Text aus SETTING_TEXTS: eigener Text oder, wenn leer, der
 * Standardtext. $vars ersetzt Platzhalter, z. B. ['{first_name}' => 'Anna'].
 *
 * @param array<string, string> $vars
 */
function setting_text(string $key, array $vars = []): string
{
    if (!isset(SETTING_TEXTS[$key])) {
        throw new InvalidArgumentException('Unbekannter Text: ' . $key);
    }
    $text = setting_get($key);
    if (trim($text) === '') {
        $text = SETTING_TEXTS[$key]['default'];
    }
    return strtr($text, $vars);
}

/**
 * Speichert einen einstellbaren Text. Leer oder gleich dem Standardtext →
 * leer (dann gilt der Standardtext, auch wenn er sich im Code ändert).
 */
function setting_text_set(string $key, string $value): void
{
    if (!isset(SETTING_TEXTS[$key])) {
        throw new InvalidArgumentException('Unbekannter Text: ' . $key);
    }
    $value = !empty(SETTING_TEXTS[$key]['line']) ? normalize_line($value) : normalize_text($value);
    setting_set($key, $value === SETTING_TEXTS[$key]['default'] ? '' : $value);
}

/**
 * Bevorzugte Orte, global für alle Veranstaltungen (SPEC §7.5).
 *
 * @return list<string>
 */
function preferred_places(): array
{
    return places_parse(setting_get('preferred_places'));
}

/** @param list<string> $places */
function preferred_places_set(array $places): void
{
    setting_set('preferred_places', implode("\n", $places));
}

/** @return list<string> ein Ort pro Zeile, normalisiert, ohne Dubletten (Groß-/Kleinschreibung egal) */
function places_parse(string $text): array
{
    $places = [];
    foreach (explode("\n", normalize_text($text)) as $line) {
        $place = normalize_line($line);
        if ($place !== '') {
            $places[mb_strtolower($place)] ??= $place;
        }
    }
    return array_values($places);
}
