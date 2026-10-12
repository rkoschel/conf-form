<?php
/*
 * Inhalt der Auswertung (SPEC §7.7): Kacheln und Tabellen. Genutzt von
 * admin/stats.php und der Team-Seite (SPEC §7.8). Erwartet $event, $stats
 * (stats_for_event()) und optional $emptyText.
 *
 * Optional $section wählt einen Teil aus (Team-Seite zeigt die Bereiche einzeln):
 * 'confirmed' = zugesagte Anmeldungen (Anwesenheit und Kinderbetreuung je
 * Programmpunkt), 'occupancy' = Gesamtbelegung und Anmeldungen (Kacheln),
 * 'status' = Anfragen nach Status. Ohne oder bei ungültigem Wert: alles (Admin):
 * Kacheln, Anwesenheit und Kinderbetreuung je Programmpunkt, zuletzt Anfragen nach Status.
 */
?>
<?php if ($event === null): ?>
  <p class="text-body-secondary"><?= e($emptyText ?? 'Noch keine Veranstaltungen angelegt.') ?></p>
<?php else: ?>
  <?php
  $section = in_array($section ?? 'all', ['confirmed', 'occupancy', 'status'], true) ? $section : 'all';
  $show = fn (string $name): bool => $section === 'all' || $section === $name;
  $used = $stats['quota_used'];
  $pending = $stats['quota_pending'];
  $max = $stats['quota_max'];
  $total = $used + $pending;
  $ratio = $max > 0 ? $used / $max : 0;
  // Skala wächst mit, wenn bestätigt + offen das Kontingent übersteigt; ein Strich markiert dann die Grenze
  $scale = max($max, $total, 1);
  $percent = fn (int $value): string => number_format($value / $scale * 100, 2, '.', '');
  $registrations = array_sum(array_column($stats['by_status'], 'registrations'));
  $people = array_sum(array_column($stats['by_status'], 'people'));
  $groupNames = array_column($stats['groups'], 'name');
  $groupLabels = array_map(fn ($group) => $group['name'], $stats['groups']);
  // Gruppe gehört zur Zielgruppe des Programmpunkts (ohne Zielgruppe: alle)
  $inTarget = fn (array $slot, string $column): bool => !$slot['target_groups'] || in_array($column, $slot['target_groups'], true);
  // Balkensegment im gemeinsamen Maßstab (Kapazität bzw. mehr bei Überbuchung)
  $segment = function (int $value, string $label, string $class) use ($percent, $max): string {
      if ($value <= 0) {
          return '';
      }
      return '<div class="progress" role="progressbar" aria-label="' . e($label) . '" style="width: ' . $percent($value) . '%"'
          . ' aria-valuenow="' . $value . '" aria-valuemin="0" aria-valuemax="' . $max . '">'
          . '<div class="progress-bar ' . $class . '"></div></div>';
  };
  ?>

  <?php if ($show('occupancy')): ?>
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small">Gesamtbelegung</div>
          <div class="display-6 my-1"><?= $used ?> <span class="fs-4 text-body-secondary">/ <?= $max ?></span></div>
          <div class="quota-meter mb-2">
            <div class="progress-stacked">
              <?= $segment($used, 'Bestätigt', $used > $max ? 'bg-danger' : 'bg-primary') ?>
              <?= $segment($pending, 'Offen', 'progress-bar-striped bg-warning') ?>
            </div>
            <?php if ($scale > $max): ?>
              <div class="quota-meter-limit" style="left: <?= $percent($max) ?>%" title="Kontingent: <?= $max ?>"></div>
            <?php endif ?>
          </div>
          <div class="d-flex flex-wrap column-gap-3 row-gap-1 small mb-1">
            <span><span class="quota-swatch <?= $used > $max ? 'bg-danger' : 'bg-primary' ?>"></span> Bestätigt <?= $used ?></span>
            <span><span class="quota-swatch progress-bar-striped bg-warning"></span> Offen <?= $pending ?></span>
          </div>
          <div class="small">
            <?php if ($used > $max): ?>
              <span class="text-danger-emphasis">⚠ Kontingent um <?= $used - $max ?> überschritten</span>
            <?php else: ?>
              <?= round($ratio * 100) ?> % belegt, <?= $max - $used ?> frei
            <?php endif ?>
            <span class="text-body-secondary">· alle Personengruppen</span>
          </div>
          <?php if ($pending > 0): ?>
            <div class="small mt-1">
              Wenn alle offenen bestätigt würden: <?= $total ?> von <?= $max ?>
              <?php if ($total > $max): ?>
                – <span class="text-danger-emphasis">⚠ Kontingent um <?= $total - $max ?> überschritten</span>
              <?php else: ?>
                (<?= round($total / max($max, 1) * 100) ?> %)
              <?php endif ?>
            </div>
          <?php endif ?>

          <h3 class="h6 mt-4 mb-2">Je Personengruppe</h3>
          <?php foreach ($stats['groups'] as $group): ?>
            <div class="mb-2">
              <div class="d-flex flex-wrap justify-content-between column-gap-3 small">
                <span><?= e($group['name']) ?></span>
                <span class="tabular-nums text-body-secondary"><?= $group['confirmed'] ?> bestätigt · <?= $group['pending'] ?> offen</span>
              </div>
              <div class="quota-meter quota-meter-sm">
                <div class="progress-stacked">
                  <?= $segment($group['confirmed'], $group['name'] . ' bestätigt', 'bg-primary') ?>
                  <?= $segment($group['pending'], $group['name'] . ' offen', 'progress-bar-striped bg-warning') ?>
                </div>
              </div>
            </div>
          <?php endforeach ?>
          <div class="small text-body-secondary">Alle Balken im selben Maßstab wie die Gesamtbelegung (Kapazität).</div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small">Anmeldungen</div>
          <div class="fs-2 my-1"><?= $registrations ?></div>
          <div class="small text-body-secondary"><?= $people ?> Personen, alle Status</div>
          <?php $placeBars = places_chart($stats['places']) ?>
          <?php if ($placeBars): ?>
            <?php /* Senkrechte Balken je Ort: größter Ort = 100 %, Beschriftung „Name (bestätigt/offen)“ (SPEC §7.7) */ ?>
            <h3 class="h6 mt-3 mb-2">Personen je Ort</h3>
            <div class="place-chart" role="list">
              <?php foreach ($placeBars as $bar): ?>
                <div class="place-chart-item" role="listitem">
                  <div class="place-chart-bar" aria-hidden="true">
                    <?php if ($bar['pending'] > 0): ?>
                      <div class="progress-bar progress-bar-striped bg-warning" style="height: <?= number_format($bar['pending'], 2, '.', '') ?>%"></div>
                    <?php endif ?>
                    <?php if ($bar['confirmed'] > 0): ?>
                      <div class="progress-bar bg-primary" style="height: <?= number_format($bar['confirmed'], 2, '.', '') ?>%"></div>
                    <?php endif ?>
                  </div>
                  <div class="place-chart-label<?= $bar['other'] ? ' fst-italic' : '' ?>" title="<?= e($bar['name']) ?>">
                    <?php if ($bar['preferred']): ?>
                      <strong><?= e($bar['short']) ?></strong><span class="visually-hidden"> (bevorzugter Ort)</span>
                    <?php else: ?>
                      <?= e($bar['short']) ?>
                    <?php endif ?>
                    <span class="tabular-nums">(<strong><?= $bar['people_confirmed'] ?></strong>/<?= $bar['people_pending'] ?>)</span>
                  </div>
                </div>
              <?php endforeach ?>
            </div>
            <div class="d-flex flex-wrap column-gap-3 row-gap-1 small mt-2">
              <span><span class="quota-swatch bg-primary"></span> Bestätigt</span>
              <span><span class="quota-swatch progress-bar-striped bg-warning"></span> Offen</span>
            </div>
            <div class="small text-body-secondary mt-1">
              Personen (bestätigt und offen) relativ zum Ort mit den meisten; Orte unter 5 % davon unter „Andere“.
              In Klammern: <strong>bestätigt</strong>/offen. Name gekürzt, voller Name beim Darüberfahren.
              <strong>Fett</strong> = bevorzugter Ort.
            </div>
          <?php endif ?>
        </div>
      </div>
    </div>
  </div>

  <?php endif ?>

  <?php if ($show('confirmed')): ?>
  <h2 class="h5">Bestätigte Anwesenheit je Programmpunkt</h2>
  <?php if (!$stats['slots']): ?>
    <p class="text-body-secondary">Für diese Veranstaltung ist kein Ablauf angelegt.</p>
  <?php else: ?>
    <div class="d-md-none vstack gap-2 mb-2">
      <?php foreach ($stats['slots'] as $slot): ?>
        <div class="card">
          <div class="card-body py-2">
            <div class="d-flex justify-content-between fw-semibold">
              <span><?= e($slot['time']) ?> <?= e($slot['label']) ?></span>
              <span class="tabular-nums"><?= $slot['total'] ?></span>
            </div>
            <?php if ($slot['target_groups']): ?>
              <div class="small text-body-secondary"><?= e(target_notice($slot['target_groups'], $groupLabels)) ?></div>
            <?php endif ?>
            <dl class="row small mb-0 mt-1 tabular-nums">
              <?php foreach ($slot['groups'] as $column => $count): ?>
                <dt class="col-8 fw-normal text-body-secondary"><?= e($stats['groups'][$column]['name']) ?></dt>
                <dd class="col-4 text-end mb-0"><?= $inTarget($slot, $column) ? $count : '–' ?></dd>
              <?php endforeach ?>
            </dl>
          </div>
        </div>
      <?php endforeach ?>
    </div>
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Uhrzeit</th>
            <th>Programmpunkt</th>
            <?php foreach ($groupNames as $label): ?>
              <th class="text-end"><?= e($label) ?></th>
            <?php endforeach ?>
            <th class="text-end">Summe</th>
          </tr>
        </thead>
        <tbody class="tabular-nums">
          <?php foreach ($stats['slots'] as $slot): ?>
            <tr>
              <td class="text-nowrap"><?= e($slot['time']) ?></td>
              <td><?= e($slot['label']) ?>
                <?php if ($slot['target_groups']): ?>
                  <span class="d-block small text-body-secondary"><?= e(target_notice($slot['target_groups'], $groupLabels)) ?></span>
                <?php endif ?>
              </td>
              <?php foreach ($slot['groups'] as $column => $count): ?>
                <td class="text-end"><?= $inTarget($slot, $column) ? $count : '–' ?></td>
              <?php endforeach ?>
              <td class="text-end fw-semibold"><?= $slot['total'] ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <p class="small text-body-secondary">
      Nur bestätigte Anmeldungen. Summe = Personen beim Programmpunkt, ohne Kinder in der Kinderbetreuung.
      „–“ = Gruppe gehört nicht zur Zielgruppe des Programmpunkts.
    </p>

    <?php
    $childcareSlots = array_values(array_filter($stats['slots'], fn ($slot) => $slot['childcare_groups']));
    // Spalten: Kindergruppen der Veranstaltung (nur diese sind betreubar)
    $childcareColumns = kids_groups(array_keys($stats['groups']));
    ?>
    <?php if ($childcareSlots): ?>
      <h2 class="h5 mt-4">Kinderbetreuung je Programmpunkt</h2>
      <div class="d-md-none vstack gap-2 mb-2">
        <?php foreach ($childcareSlots as $slot): ?>
          <div class="card">
            <div class="card-body py-2">
              <div class="d-flex justify-content-between fw-semibold">
                <span><?= e($slot['time']) ?> <?= e($slot['label']) ?></span>
                <span class="tabular-nums"><?= $slot['childcare_total'] ?></span>
              </div>
              <div class="small text-body-secondary">Betreuung für <?= e(childcare_names($slot['childcare_groups'], $groupLabels)) ?></div>
              <dl class="row small mb-0 mt-1 tabular-nums">
                <?php foreach ($childcareColumns as $column): ?>
                  <?php if (array_key_exists($column, $slot['childcare'])): ?>
                    <dt class="col-8 fw-normal text-body-secondary"><?= e($groupLabels[$column]) ?></dt>
                    <dd class="col-4 text-end mb-0"><?= $slot['childcare'][$column] ?></dd>
                  <?php endif ?>
                <?php endforeach ?>
              </dl>
            </div>
          </div>
        <?php endforeach ?>
      </div>
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Uhrzeit</th>
              <th>Programmpunkt</th>
              <th>Betreuung für</th>
              <?php foreach ($childcareColumns as $column): ?>
                <th class="text-end"><?= e($groupLabels[$column]) ?></th>
              <?php endforeach ?>
              <th class="text-end">Summe</th>
            </tr>
          </thead>
          <tbody class="tabular-nums">
            <?php foreach ($childcareSlots as $slot): ?>
              <tr>
                <td class="text-nowrap"><?= e($slot['time']) ?></td>
                <td><?= e($slot['label']) ?></td>
                <td><?= e(childcare_names($slot['childcare_groups'], $groupLabels)) ?></td>
                <?php foreach ($childcareColumns as $column): ?>
                  <td class="text-end">
                    <?= array_key_exists($column, $slot['childcare'])
                        ? $slot['childcare'][$column]
                        : '<span class="text-body-secondary" title="keine Betreuung für diese Gruppe">–</span>' ?>
                  </td>
                <?php endforeach ?>
                <td class="text-end fw-semibold"><?= $slot['childcare_total'] ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <p class="small text-body-secondary">
        Nur bestätigte Anmeldungen.<span class="d-none d-md-inline"> – = für diese Gruppe keine Betreuung.</span>
      </p>
    <?php endif ?>
  <?php endif ?>
  <?php endif ?>
  <?php if ($show('status')): ?>
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <?php if ($section === 'all'): ?>
        <h2 class="h5 mt-4">Anfragen nach Status</h2>
      <?php endif ?>
      <table class="table table-sm">
        <thead>
          <tr><th>Status</th><th class="text-end">Anmeldungen</th><th class="text-end">Personen</th></tr>
        </thead>
        <tbody class="tabular-nums">
          <?php foreach (STATUS_LABELS as $status => $label): ?>
            <tr>
              <td><?= e($label) ?></td>
              <td class="text-end"><?= $stats['by_status'][$status]['registrations'] ?></td>
              <td class="text-end"><?= $stats['by_status'][$status]['people'] ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot class="tabular-nums fw-semibold">
          <tr><td>Gesamt</td><td class="text-end"><?= $registrations ?></td><td class="text-end"><?= $people ?></td></tr>
        </tfoot>
      </table>
      <p class="small text-body-secondary">Personen = Einzelpersonen aller Personengruppen.</p>
    </div>

  </div>

  <?php endif ?>
<?php endif ?>
