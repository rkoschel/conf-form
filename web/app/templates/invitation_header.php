<?php /* Kopf der „Einladungskarte“ (Info- und Anmeldeseite): optionale Überzeile, Titel, Datum · Ort, Frist */ ?>
<header class="invitation-header">
  <?php if (!empty($eyebrow)): ?>
    <p class="invitation-eyebrow"><?= e($eyebrow) ?></p>
  <?php endif ?>
  <h1 class="invitation-title"><?= e($event['title']) ?></h1>
  <p class="invitation-meta mb-1">
    <span class="text-nowrap"><?= e(format_date_long($event['date'])) ?></span>
    <span aria-hidden="true">·</span>
    <span><?= e($event['location']) ?></span>
  </p>
  <p class="invitation-meta small mb-0">
    Anmeldung bis <?= e(format_local_datetime($event['registration_deadline'])) ?>
    <?php if ($event['timezone'] !== 'Europe/Berlin'): ?>
      (<?= e($event['timezone']) ?>)
    <?php endif ?>
  </p>
</header>
