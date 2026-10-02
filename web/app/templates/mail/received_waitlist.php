Warteliste: <?= $event['title'] ?>

<?= setting_text('mail_intro_received_waitlist', $placeholders) ?>


<?php require __DIR__ . '/_event.php' ?>

<?php require __DIR__ . '/_people.php' ?>

<?php $cancel_hint = 'mail_cancel_hint_waitlist' ?>
<?php require __DIR__ . '/_cancel.php' ?>

<?php require __DIR__ . '/_signature.php' ?>
