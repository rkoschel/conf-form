<?php /* Ergebnis nach dem Absenden (SPEC §5.3) */ ?>
<?php if ($status === 'confirmed'): ?>
  <h1 class="h3 mb-3">Anmeldung bestätigt</h1>
  <p>Vielen Dank! Deine Teilnahme ist bestätigt.</p>
<?php else: ?>
  <h1 class="h3 mb-3">Du stehst auf der Warteliste</h1>
  <p>Vielen Dank für deine Anmeldung. Sobald ein Platz für dich frei ist, melden wir uns bei dir.</p>
<?php endif ?>
<p class="text-body-secondary">
  Falls du eine E-Mail-Adresse angegeben hast, bekommst du eine Bestätigung per E-Mail.
  Darin findest du auch einen Link, mit dem du wieder absagen kannst.
</p>
<p><a href="<?= e(url('')) ?>">Zur Startseite</a></p>
