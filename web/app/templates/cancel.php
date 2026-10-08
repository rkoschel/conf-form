<?php /* Absage-Seite (SPEC §6) */ ?>
<article class="invitation invitation-compact">
  <header class="invitation-header">
    <h1 class="invitation-title">Teilnahme absagen</h1>
    <?php if ($registration !== null): ?>
      <p class="invitation-meta mb-1"><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></p>
      <p class="invitation-meta small mb-0">
        <?= e($event['title']) ?> · <span class="text-nowrap"><?= e(format_date_long($event['date'])) ?></span>
      </p>
    <?php endif ?>
  </header>

  <div>
    <?php if ($registration === null): ?>
      <div class="alert alert-secondary mb-0" role="status">
        Dieser Link ist ungültig. Bitte prüfe, ob du ihn vollständig aus der E-Mail übernommen hast.
      </div>
    <?php elseif ($done): ?>
      <div class="alert alert-success mb-0" role="status">
        <span class="text-pre-line"><?= e(setting_text('cancel_done')) ?></span>
      </div>
    <?php elseif ($registration['status'] === 'cancelled'): ?>
      <div class="alert alert-secondary mb-0" role="status">Diese Anmeldung ist bereits storniert.</div>
    <?php elseif ($registration['status'] === 'rejected'): ?>
      <div class="alert alert-secondary mb-0" role="status">Diese Anmeldung wurde bereits abgelehnt.</div>
    <?php else: ?>
      <p>Möchtest du deine <?= $registration['status'] === 'pending' ? 'Anfrage' : 'Teilnahme' ?> wirklich absagen?</p>
      <form method="post" action="<?= e(url('cancel/')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <button type="submit" class="btn btn-danger">Teilnahme absagen</button>
      </form>
    <?php endif ?>
  </div>
</article>
