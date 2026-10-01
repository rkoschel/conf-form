<?php /* Personenzahlen der Anmeldung */ ?>
Angemeldete Personen:
<?php foreach ($people as $label => $count): ?>
  <?= $label ?>: <?= $count ?>

<?php endforeach ?>
