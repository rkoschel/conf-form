<?php /* Besuchte Programmpunkte der Anmeldung (mail_slot_lines()) */ ?>
<?php if ($slots): ?>
Programmpunkte:
<?php foreach ($slots as $line): ?>
  <?= $line ?>

<?php endforeach ?>
<?php endif ?>
