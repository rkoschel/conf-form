<?php /* Team-Auswertung (SPEC §7.8): Titel der aktiven Veranstaltung oben, darunter die Auswertung */ ?>
<?php if ($event !== null): ?>
  <h1 class="h3 mb-1"><?= e($event['title']) ?></h1>
  <p class="text-body-secondary mb-4">
    <?= e(format_date_long($event['date'])) ?> · Auswertung, Stand <?= e(format_utc_datetime(now_utc(), $event['timezone'])) ?> Uhr
  </p>
<?php else: ?>
  <h1 class="h3 mb-4">Auswertung</h1>
<?php endif ?>

<?php require __DIR__ . '/stats_content.php' ?>
