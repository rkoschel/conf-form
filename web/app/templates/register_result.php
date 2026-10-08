<?php /* Ergebnis nach dem Absenden (SPEC §5.3) */ ?>
<article class="invitation invitation-compact">
  <header class="invitation-header">
    <p class="invitation-eyebrow">Anmeldung</p>
    <h1 class="invitation-title">
      <?= $status === 'confirmed' ? 'Anmeldung bestätigt' : 'Du stehst auf der Warteliste' ?>
    </h1>
  </header>
  <div>
    <p class="text-pre-line"><?= e(setting_text($status === 'confirmed' ? 'result_confirmed' : 'result_waitlist')) ?></p>
    <p class="text-body-secondary text-pre-line mb-0"><?= e(setting_text('result_mail_hint')) ?></p>
  </div>
</article>
<p class="text-center mt-4 mb-0"><a href="<?= e(url('')) ?>" class="link-secondary">Zur Startseite</a></p>
