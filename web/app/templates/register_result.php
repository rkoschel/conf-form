<?php /* Ergebnis nach dem Absenden (SPEC §5.3) */ ?>
<?php if ($status === 'confirmed'): ?>
  <h1 class="h3 mb-3">Anmeldung bestätigt</h1>
  <p class="text-pre-line"><?= e(setting_text('result_confirmed')) ?></p>
<?php else: ?>
  <h1 class="h3 mb-3">Du stehst auf der Warteliste</h1>
  <p class="text-pre-line"><?= e(setting_text('result_waitlist')) ?></p>
<?php endif ?>
<p class="text-body-secondary text-pre-line"><?= e(setting_text('result_mail_hint')) ?></p>
<p><a href="<?= e(url('')) ?>">Zur Startseite</a></p>
