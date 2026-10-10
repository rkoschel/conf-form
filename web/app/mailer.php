<?php
declare(strict_types=1);

// Mails an Teilnehmer (SPEC §9). Transport per config('mail_transport'):
// 'smtp' = PHPMailer über SMTP, 'log' = nur in config('mail_log_file') schreiben.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Schickt die Mail $type zu einer Anmeldung und protokolliert sie in mail_log.
 * Ohne E-Mail-Adresse wird nichts gesendet.
 *
 * @param array<string, mixed> $registration Zeile aus registrations
 * @param array<string, mixed> $event        Zeile aus events
 * @return bool true, wenn die Mail verschickt wurde
 */
function mail_registration(string $type, array $registration, array $event): bool
{
    if (!isset(MAIL_TYPES[$type])) {
        throw new InvalidArgumentException('Unbekannter Mail-Typ: ' . $type);
    }
    $to = trim((string) ($registration['email'] ?? ''));
    if ($to === '' || !empty($registration['no_email'])) {
        return false;
    }

    $error = null;
    try {
        $mail = mail_render($type, mail_vars($registration, $event));
        mail_send(
            $to,
            $registration['first_name'] . ' ' . $registration['last_name'],
            $mail['subject'],
            $mail['body'],
            (string) ($event['organizer_email'] ?? ''),
            (string) ($event['organizer_name'] ?? '')
        );
    } catch (Throwable $e) {
        $error = mb_substr($e->getMessage(), 0, 500);
    }

    db()->prepare(
        'INSERT INTO mail_log (registration_id, type, sent_at, success, error) VALUES (?, ?, ?, ?, ?)'
    )->execute([$registration['id'], $type, now_utc(), $error === null ? 1 : 0, $error]);

    return $error === null;
}

/**
 * Variablen für die Mail-Templates.
 *
 * @return array<string, mixed>
 */
function mail_vars(array $registration, array $event): array
{
    $people = [];
    // Personengruppen mit den Namen der Veranstaltung
    foreach (event_groups($event) as $column => $label) {
        $count = (int) ($registration[$column] ?? 0);
        if ($count > 0) {
            $people[$label] = $count;
        }
    }

    $eventDate = format_date_long((string) $event['date']);
    $organizer = trim((string) ($event['organizer_name'] ?? ''));

    return [
        'registration' => $registration,
        'event' => $event,
        'event_date' => $eventDate,
        'people' => $people,
        'cancel_url' => app_url('cancel/?t=' . $registration['cancel_token']),
        // Platzhalter für die einstellbaren Texte (SETTING_TEXTS, 'mail')
        'placeholders' => [
            '{first_name}' => (string) $registration['first_name'],
            '{last_name}' => (string) $registration['last_name'],
            '{title}' => (string) $event['title'],
            '{date}' => $eventDate,
            '{location}' => (string) $event['location'],
            '{organizer}' => $organizer !== '' ? $organizer : setting_text('mail_organizer_fallback'),
        ],
    ];
}

/**
 * Rendert templates/mail/$type.php. Die erste Zeile der Ausgabe ist der
 * Betreff, der Rest der Text.
 *
 * @param array<string, mixed> $vars
 * @return array{subject: string, body: string}
 */
function mail_render(string $type, array $vars): array
{
    $render = static function (string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string) ob_get_clean();
    };
    $output = $render(APP_DIR . '/templates/mail/' . $type . '.php', $vars);

    [$subject, $body] = array_pad(explode("\n", ltrim($output), 2), 2, '');
    $subject = trim($subject);
    if ($subject === '' || trim($body) === '') {
        throw new LogicException('Mail-Template ohne Betreff oder Text: ' . $type);
    }
    return ['subject' => $subject, 'body' => trim($body) . "\n"];
}

/** Absolute URL für Links in Mails, z. B. app_url('cancel/') → https://…/konferenz/cancel/ */
function app_url(string $path = ''): string
{
    $base = rtrim((string) config('app_url'), '/');
    if ($base === '') {
        throw new RuntimeException('app_url ist nicht konfiguriert.');
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Verschickt eine Textmail; wirft bei Fehlern eine Exception.
 *
 * @param (Closure(string): void)|null $debug erhält das SMTP-Protokoll zeilenweise (nur Testmail)
 */
function mail_send(
    string $to,
    string $toName,
    string $subject,
    string $body,
    string $replyTo = '',
    string $replyToName = '',
    ?Closure $debug = null
): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ungültige E-Mail-Adresse.');
    }
    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = '';
    }

    match (config('mail_transport')) {
        'smtp' => mail_send_smtp($to, $toName, $subject, $body, $replyTo, $replyToName, $debug),
        'log' => mail_send_log($to, $subject, $body, $replyTo),
        default => throw new RuntimeException('Unbekannter mail_transport.'),
    };
}

function mail_send_smtp(
    string $to,
    string $toName,
    string $subject,
    string $body,
    string $replyTo,
    string $replyToName,
    ?Closure $debug = null
): void {
    require_once APP_DIR . '/lib/PHPMailer/Exception.php';
    require_once APP_DIR . '/lib/PHPMailer/PHPMailer.php';
    require_once APP_DIR . '/lib/PHPMailer/SMTP.php';

    $smtp = (array) config('smtp');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) $smtp['host'];
    $mail->Port = (int) $smtp['port'];
    $mail->SMTPSecure = $smtp['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPAuth = (string) $smtp['username'] !== '';
    $mail->Username = (string) $smtp['username'];
    $mail->Password = (string) $smtp['password'];
    $mail->Timeout = 15;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
    $mail->XMailer = ' '; // keinen PHPMailer-Header mit Version senden
    if ($debug !== null) {
        $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
        $mail->Debugoutput = static fn (string $line) => $debug($line);
    }

    $mail->setFrom((string) $smtp['from_email'], (string) $smtp['from_name']);
    $mail->addAddress($to, $toName);
    if ($replyTo !== '') {
        $mail->addReplyTo($replyTo, $replyToName);
    }
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();
}

function mail_send_log(string $to, string $subject, string $body, string $replyTo): void
{
    $entry = '=== ' . now_utc() . "\n"
        . 'To: ' . $to . "\n"
        . ($replyTo !== '' ? 'Reply-To: ' . $replyTo . "\n" : '')
        . 'Subject: ' . $subject . "\n\n"
        . $body . "\n";

    if (file_put_contents((string) config('mail_log_file'), $entry, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Mail-Log nicht beschreibbar.');
    }
}

/**
 * Testmail aus den Einstellungen (SPEC §7.5): schickt eine kurze Mail an $to
 * und liefert ein ausführliches Protokoll (Umgebung, SMTP-Dialog). Das
 * Protokoll wird nur angezeigt, nicht gespeichert; Zugangsdaten sind darin
 * ausgeblendet (mail_debug_collector()).
 *
 * @return array{ok: bool, error: ?string, log: list<string>}
 */
function mail_test(string $to): array
{
    $log = [];
    $smtp = (array) config('smtp');
    $transport = (string) config('mail_transport');
    $log[] = 'PHP ' . PHP_VERSION . ', OpenSSL ' . (extension_loaded('openssl') ? OPENSSL_VERSION_TEXT : 'nicht geladen');
    $log[] = 'mail_transport: ' . $transport;
    if ($transport === 'smtp') {
        $host = (string) ($smtp['host'] ?? '');
        $ip = $host !== '' ? gethostbyname($host) : '';
        $log[] = 'SMTP: ' . $host . ':' . (int) ($smtp['port'] ?? 0)
            . ' (' . ($ip !== '' && $ip !== $host ? 'IP ' . $ip : 'Name nicht auflösbar') . '), Verschlüsselung '
            . (($smtp['encryption'] ?? '') === 'ssl' ? 'SSL/TLS' : 'STARTTLS');
    } elseif ($transport === 'log') {
        $log[] = 'Kein echter Versand: Mail wird in ' . (string) config('mail_log_file') . ' geschrieben.';
    }
    $log[] = '';

    $error = null;
    try {
        mail_send(
            $to,
            '',
            'Testmail Konferenz-Anmeldung',
            "Dies ist eine Testmail aus den Einstellungen der Konferenz-Anmeldung.\n\n"
                . 'Gesendet am ' . gmdate('d.m.Y, H:i') . " Uhr (UTC).\n",
            '',
            '',
            mail_debug_collector($log)
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    return ['ok' => $error === null, 'error' => $error, 'log' => $log];
}

/**
 * Sammelt PHPMailer-Debugzeilen in $log und blendet Zugangsdaten aus: die
 * Antworten des Clients auf Server-Rückfragen (334, AUTH LOGIN) und Daten
 * hinter AUTH PLAIN/LOGIN.
 *
 * @param list<string> $log
 * @return Closure(string): void
 */
function mail_debug_collector(array &$log): Closure
{
    $secretNext = false;
    return static function (string $line) use (&$log, &$secretNext): void {
        foreach (preg_split('/\r?\n/', rtrim($line)) ?: [] as $part) {
            $part = rtrim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\S+\s+)?CLIENT -> SERVER:/', $part)) {
                if ($secretNext) {
                    $part = preg_replace('/(CLIENT -> SERVER:).*/', '$1 [Zugangsdaten ausgeblendet]', $part);
                    $secretNext = false;
                } elseif (preg_match('/AUTH\s+(PLAIN|LOGIN|CRAM-MD5|XOAUTH2)\s+\S/i', $part)) {
                    $part = preg_replace('/(AUTH\s+\S+)\s+.*/i', '$1 [Zugangsdaten ausgeblendet]', $part);
                }
            } elseif (preg_match('/SERVER -> CLIENT:\s*334\b/', $part)) {
                $secretNext = true;
            }
            $log[] = (string) $part;
        }
    };
}

/**
 * Mail-Einstellungen zur Anzeige im Admin (ohne Passwort).
 *
 * @return array<string, string> Bezeichnung → Wert
 */
function mail_config_summary(): array
{
    $smtp = (array) config('smtp');
    $transport = (string) config('mail_transport');
    if ($transport !== 'smtp') {
        return ['Versand' => $transport === 'log' ? 'kein echter Versand (nur Log-Datei)' : $transport];
    }
    return [
        'Versand' => 'SMTP',
        'Server' => (string) ($smtp['host'] ?? '') . ':' . (int) ($smtp['port'] ?? 0),
        'Verschlüsselung' => ($smtp['encryption'] ?? '') === 'ssl' ? 'SSL/TLS' : 'STARTTLS',
        'Benutzer' => (string) ($smtp['username'] ?? ''),
        'Passwort' => (string) ($smtp['password'] ?? '') !== '' ? 'gesetzt' : 'fehlt',
        'Absender' => trim((string) ($smtp['from_name'] ?? '') . ' <' . (string) ($smtp['from_email'] ?? '') . '>'),
    ];
}
