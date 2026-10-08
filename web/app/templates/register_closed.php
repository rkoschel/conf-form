<?php /* Anmeldung nicht möglich: keine aktive Veranstaltung oder Frist abgelaufen */ ?>
<article class="invitation invitation-compact">
  <header class="invitation-header">
    <h1 class="invitation-title">Anmeldung</h1>
  </header>
  <p class="text-pre-line mb-0" role="status"><?= e($event === null ? setting_text('inactive_text') : 'Der Anmeldezeitraum ist abgelaufen.') ?></p>
</article>
<p class="text-center mt-4 mb-0"><a href="<?= e(url('')) ?>" class="link-secondary">Zur Startseite</a></p>
