<?php
// Anmeldeformular (SPEC §5)
require __DIR__ . '/../app/bootstrap.php';

session_start_once();

// Ergebnis nach Post/Redirect/Get (einmalig aus der Session)
if (!is_post() && isset($_SESSION['register_result'])) {
    $status = $_SESSION['register_result'];
    unset($_SESSION['register_result']);
    render('register_result', ['title' => 'Anmeldung', 'status' => $status]);
    exit;
}

$event = event_active();
if ($event === null || !event_registration_open($event)) {
    render('register_closed', ['title' => 'Anmeldung', 'event' => $event]);
    exit;
}

$form = ['adults' => '1', 'attend' => array_fill_keys(array_column($event['slots'], 'id'), '1')];
$errors = [];
$notice = null;

if (is_post()) {
    $form = $_POST;
    $spam = spam_check($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($spam === SPAM_HONEYPOT) {
        // Antwort wie bei Erfolg, nichts speichern, keine Mail
        $_SESSION['register_result'] = 'confirmed';
        redirect('register/');
    }
    if ($spam !== SPAM_OK) {
        http_response_code($spam === SPAM_RATE_LIMIT ? 429 : 400);
        $notice = spam_message($spam);
    } else {
        csrf_check();
        [$data, $errors] = registration_validate($_POST, $event['slots'], (int) $event['max_participants']);
        if (!$errors) {
            $registration = registration_create($event, $data);
            mail_registration(
                $registration['status'] === 'confirmed' ? 'received_confirmed' : 'received_waitlist',
                $registration,
                $event
            );
            $_SESSION['register_result'] = $registration['status'];
            redirect('register/');
        }
    }
}

render('register', [
    'title' => 'Anmeldung: ' . $event['title'],
    'event' => $event,
    'form' => $form,
    'errors' => $errors,
    'notice' => $notice,
    'scripts' => ['form.js'],
]);
