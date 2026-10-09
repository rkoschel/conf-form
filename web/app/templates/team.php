<?php /* Team-Auswertung (SPEC §7.8): Titel der aktiven Veranstaltung oben, darunter die Auswertung */ ?>
<?php if ($event !== null): ?>
  <h1 class="h3 mb-1"><?= e($event['title']) ?></h1>
  <p class="text-body-secondary mb-4">
    <?= e(format_date_long($event['date'])) ?> · Auswertung, Stand <?= e(format_utc_datetime(now_utc(), $event['timezone'])) ?> Uhr
  </p>
<?php else: ?>
  <h1 class="h3 mb-4">Auswertung</h1>
<?php endif ?>

<?php if ($event === null): ?>
  <?php require __DIR__ . '/stats_content.php' ?>
<?php else: ?>
  <?php
  // Drei klar abgegrenzte Bereiche (SPEC §7.8); stats_content.php zeigt je Bereich nur den passenden Teil
  $areas = [
      'confirmed' => ['Zugesagte Anmeldungen', 'Nur bestätigte Anmeldungen: Anwesenheit und Kinderbetreuung je Programmpunkt.'],
      'occupancy' => ['Gesamtbelegung und Anmeldungen', 'Belegung des Kontingents und Zahl der Anmeldungen.'],
      'status' => ['Anfragen nach Status', 'Alle Anfragen nach Status, auch offene, stornierte und abgelehnte.'],
  ];
  ?>
  <?php foreach ($areas as $section => [$areaTitle, $areaHint]): ?>
    <section class="stats-area stats-area-<?= e($section) ?>" aria-labelledby="area-<?= e($section) ?>">
      <header class="stats-area-header">
        <h2 class="h4 mb-1" id="area-<?= e($section) ?>"><?= e($areaTitle) ?></h2>
        <p class="small text-body-secondary mb-0"><?= e($areaHint) ?></p>
      </header>
      <?php require __DIR__ . '/stats_content.php' ?>
    </section>
  <?php endforeach ?>
<?php endif ?>
