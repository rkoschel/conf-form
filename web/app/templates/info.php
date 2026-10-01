<?php /* Infoseite der aktiven Veranstaltung (SPEC §4); Kontingent wird bewusst nicht angezeigt */ ?>
<h1 class="mb-4"><?= e($event['title']) ?></h1>

<dl class="row mb-4">
  <dt class="col-sm-4">Datum</dt>
  <dd class="col-sm-8"><?= e(format_date_long($event['date'])) ?></dd>

  <dt class="col-sm-4">Ort</dt>
  <dd class="col-sm-8"><?= e($event['location']) ?></dd>

  <dt class="col-sm-4">Anmeldefrist</dt>
  <dd class="col-sm-8">
    <?= e(format_local_datetime($event['registration_deadline'])) ?>
    <?php if ($event['timezone'] !== 'Europe/Berlin'): ?>
      <span class="text-body-secondary">(<?= e($event['timezone']) ?>)</span>
    <?php endif ?>
  </dd>
</dl>

<?php if ($event['description'] !== ''): ?>
  <p class="text-pre-line mb-4"><?= e($event['description']) ?></p>
<?php endif ?>

<?php if ($event['slots']): ?>
  <h2 class="h4">Ablauf</h2>
  <ul class="list-group mb-4">
    <?php foreach ($event['slots'] as $slot): ?>
      <li class="list-group-item d-flex gap-3">
        <span class="fw-semibold text-nowrap"><?= e($slot['time']) ?> Uhr</span>
        <span><?= e($slot['label']) ?></span>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>

<?php if (!$registrationOpen): ?>
  <div class="alert alert-secondary" role="status">Der Anmeldezeitraum ist abgelaufen.</div>
<?php endif ?>

<div class="d-flex flex-wrap gap-2 align-items-center">
  <?php if ($registrationOpen): ?>
    <a href="<?= e(url('register/')) ?>" class="btn btn-primary btn-lg">Anmelden</a>
  <?php endif ?>
  <a href="<?= e(config('info_url')) ?>" class="btn btn-link">Weitere Informationen</a>
</div>
