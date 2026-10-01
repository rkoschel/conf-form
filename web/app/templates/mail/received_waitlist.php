Warteliste: <?= $event['title'] ?>

Hallo <?= $registration['first_name'] ?>,

vielen Dank für deine Anmeldung. Du stehst zurzeit auf der Warteliste.
Sobald ein Platz für dich frei ist, melden wir uns bei dir.

<?php require __DIR__ . '/_event.php' ?>

<?php require __DIR__ . '/_people.php' ?>

Falls du deine Anfrage zurückziehen möchtest, nutze bitte diesen Link:
<?= $cancel_url ?>


<?php require __DIR__ . '/_signature.php' ?>
