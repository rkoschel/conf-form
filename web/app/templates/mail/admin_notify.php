<?php /* Benachrichtigung an den Admin bei neuer Anmeldung (SPEC §9); feste Texte */ ?>
Neue Anmeldung (<?= $status_label ?>): <?= $registration['first_name'] ?> <?= $registration['last_name'] ?>

Neue Anmeldung zu „<?= $event['title'] ?>“ (<?= $event_date ?>).

Status: <?= $status_label ?>

Name: <?= $registration['first_name'] ?> <?= $registration['last_name'] ?>

<?= $congregation_label ?>: <?= $registration['congregation'] ?>

<?php if (!empty($registration['email'])): ?>
E-Mail: <?= $registration['email'] ?>

<?php else: ?>
Telefon: <?= $registration['phone'] ?> (keine E-Mail-Adresse)
<?php endif ?>

<?php require __DIR__ . '/_people.php' ?>

<?php require __DIR__ . '/_slots.php' ?>
<?php if (trim((string) $registration['message']) !== ''): ?>

Nachricht:
<?= $registration['message'] ?>

<?php endif ?>

Anmeldung im Admin öffnen:
<?= $admin_url ?>

