<?php /* Absage-Seite (SPEC §6) */ ?>
<h1 class="h3 mb-4">Teilnahme absagen</h1>

<?php if ($registration === null): ?>
  <div class="alert alert-secondary" role="status">
    Dieser Link ist ungültig. Bitte prüfe, ob du ihn vollständig aus der E-Mail übernommen hast.
  </div>
<?php else: ?>
  <dl class="row mb-4">
    <dt class="col-sm-4">Name</dt>
    <dd class="col-sm-8"><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></dd>
    <dt class="col-sm-4">Veranstaltung</dt>
    <dd class="col-sm-8"><?= e($event['title']) ?>, <?= e(format_date_long($event['date'])) ?></dd>
  </dl>

  <?php if ($done): ?>
    <div class="alert alert-success" role="status">
      Deine Teilnahme ist abgesagt. Danke, dass du Bescheid gegeben hast.
    </div>
  <?php elseif ($registration['status'] === 'cancelled'): ?>
    <div class="alert alert-secondary" role="status">Diese Anmeldung ist bereits storniert.</div>
  <?php elseif ($registration['status'] === 'rejected'): ?>
    <div class="alert alert-secondary" role="status">Diese Anmeldung wurde bereits abgelehnt.</div>
  <?php else: ?>
    <p>Möchtest du deine <?= $registration['status'] === 'pending' ? 'Anfrage' : 'Teilnahme' ?> wirklich absagen?</p>
    <form method="post" action="<?= e(url('cancel/')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <button type="submit" class="btn btn-danger">Teilnahme absagen</button>
    </form>
  <?php endif ?>
<?php endif ?>
