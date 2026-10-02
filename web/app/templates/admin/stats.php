<div class="d-flex flex-wrap gap-3 justify-content-between align-items-end mb-4">
  <h1 class="h3 mb-0">Auswertung</h1>
  <?php if ($events): ?>
    <form method="get" class="d-flex gap-2 align-items-end">
      <div>
        <label for="f-event" class="form-label small mb-1">Veranstaltung</label>
        <select id="f-event" name="event" class="form-select">
          <?php foreach ($events as $option): ?>
            <option value="<?= (int) $option['id'] ?>"<?= $event && (int) $event['id'] === (int) $option['id'] ? ' selected' : '' ?>>
              <?= e(format_date($option['date']) . ' – ' . $option['title']) ?><?= $option['active'] ? ' (aktiv)' : '' ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <button type="submit" class="btn btn-outline-primary">Anzeigen</button>
    </form>
  <?php endif ?>
</div>

<?php if ($event === null): ?>
  <p class="text-body-secondary">Noch keine Veranstaltungen angelegt.</p>
<?php else: ?>
  <?php
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
  $confirmedPeople = array_sum($stats['age_groups']);
  ?>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small">Belegung Kontingent</div>
          <div class="display-6 my-1"><?= $used ?> <span class="fs-4 text-body-secondary">/ <?= $max ?></span></div>
          <div class="quota-meter mb-2">
            <div class="progress-stacked">
              <?php if ($used > 0): ?>
                <div class="progress" role="progressbar" aria-label="Bestätigt" style="width: <?= $percent($used) ?>%"
                     aria-valuenow="<?= $used ?>" aria-valuemin="0" aria-valuemax="<?= $max ?>">
                  <div class="progress-bar <?= $used > $max ? 'bg-danger' : 'bg-primary' ?>"></div>
                </div>
              <?php endif ?>
              <?php if ($pending > 0): ?>
                <div class="progress" role="progressbar" aria-label="Offen" style="width: <?= $percent($pending) ?>%"
                     aria-valuenow="<?= $pending ?>" aria-valuemin="0" aria-valuemax="<?= $max ?>">
                  <div class="progress-bar progress-bar-striped bg-warning"></div>
                </div>
              <?php endif ?>
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
            <span class="text-body-secondary">· Personen ohne Kinder 0–2</span>
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
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small">Anmeldungen</div>
          <div class="fs-2 my-1"><?= $registrations ?></div>
          <div class="small text-body-secondary"><?= $people ?> Personen, alle Status</div>
          <?php if ($stats['places']): ?>
            <table class="table table-sm small mt-2 mb-0">
              <caption class="caption-top pb-1 pt-0">Personen je Ort</caption>
              <thead>
                <tr><th>Ort</th><th class="text-end">bestätigt</th><th class="text-end">offen</th></tr>
              </thead>
              <tbody class="tabular-nums">
                <?php foreach ($stats['places'] as $place): ?>
                  <tr>
                    <td>
                      <?php if ($place['preferred']): ?>
                        <strong><?= e($place['name']) ?></strong><span class="visually-hidden"> (bevorzugter Ort)</span>
                      <?php else: ?>
                        <?= e($place['name']) ?>
                      <?php endif ?>
                    </td>
                    <td class="text-end"><?= $place['confirmed'] ?></td>
                    <td class="text-end"><?= $place['pending'] ?></td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
            <div class="small text-body-secondary mt-1">inkl. Kinder 0–2 · <strong>fett</strong> = bevorzugter Ort</div>
          <?php endif ?>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small">Bestätigte Personen</div>
          <div class="fs-2 my-1"><?= $confirmedPeople ?></div>
          <div class="small text-body-secondary">inkl. Kinder 0–2</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <h2 class="h5">Anfragen nach Status</h2>
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
      <p class="small text-body-secondary">Personen = Einzelpersonen inkl. Kinder 0–2.</p>
    </div>

    <div class="col-lg-6">
      <h2 class="h5">Bestätigte Personen nach Altersgruppe</h2>
      <table class="table table-sm">
        <thead>
          <tr><th>Altersgruppe</th><th class="text-end">Personen</th></tr>
        </thead>
        <tbody class="tabular-nums">
          <?php foreach (AGE_GROUPS as $column => $label): ?>
            <tr>
              <td><?= e($label) ?><?= in_array($column, QUOTA_AGE_GROUPS, true) ? '' : ' <span class="text-body-secondary small">(nicht im Kontingent)</span>' ?></td>
              <td class="text-end"><?= $stats['age_groups'][$column] ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot class="tabular-nums fw-semibold">
          <tr><td>Gesamt</td><td class="text-end"><?= $confirmedPeople ?></td></tr>
        </tfoot>
      </table>
    </div>
  </div>

  <h2 class="h5">Voraussichtliche Anwesenheit je Programmpunkt</h2>
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
            <dl class="row small mb-0 mt-1 tabular-nums">
              <?php foreach (AGE_GROUPS as $column => $label): ?>
                <dt class="col-8 fw-normal text-body-secondary"><?= e($label) ?></dt>
                <dd class="col-4 text-end mb-0"><?= $slot['groups'][$column] ?></dd>
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
            <?php foreach (AGE_GROUPS as $label): ?>
              <th class="text-end"><?= e($label) ?></th>
            <?php endforeach ?>
            <th class="text-end">Summe</th>
          </tr>
        </thead>
        <tbody class="tabular-nums">
          <?php foreach ($stats['slots'] as $slot): ?>
            <tr>
              <td class="text-nowrap"><?= e($slot['time']) ?></td>
              <td><?= e($slot['label']) ?></td>
              <?php foreach ($slot['groups'] as $count): ?>
                <td class="text-end"><?= $count ?></td>
              <?php endforeach ?>
              <td class="text-end fw-semibold"><?= $slot['total'] ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <p class="small text-body-secondary">Nur bestätigte Anmeldungen.</p>
  <?php endif ?>
<?php endif ?>
