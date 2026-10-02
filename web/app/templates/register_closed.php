<?php /* Anmeldung nicht möglich: keine aktive Veranstaltung oder Frist abgelaufen */ ?>
<h1 class="h3 mb-4">Anmeldung</h1>
<div class="alert alert-secondary" role="status">
  <span class="text-pre-line"><?= e($event === null ? setting_text('inactive_text') : 'Der Anmeldezeitraum ist abgelaufen.') ?></span>
</div>
<p><a href="<?= e(url('')) ?>">Zur Startseite</a></p>
