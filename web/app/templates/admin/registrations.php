<?php
/* Anfragen-Tabelle (SPEC §7.2) */
$statusBadge = [
    'pending' => 'text-bg-warning',
    'confirmed' => 'text-bg-success',
    'cancelled' => 'text-bg-secondary',
    'rejected' => 'text-bg-danger',
];
// Details je Anmeldung: Personen je Gruppe und je Programmpunkt (aufklappbar, admin.js)
$eventGroups = $event !== null ? event_groups($event) : [];
$eventHasChildcare = $event !== null && (bool) array_filter($event['slots'], fn ($slot) => !empty($slot['childcare']));
?>
<h1 class="h3 mb-3">Anfragen</h1>

<?php if ($event === null): ?>
  <p class="text-body-secondary">Noch keine Veranstaltungen angelegt.</p>
<?php else: ?>
  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
      <label for="f-event" class="form-label small mb-1">Veranstaltung</label>
      <select id="f-event" name="event" class="form-select">
        <?php foreach ($events as $option): ?>
          <option value="<?= (int) $option['id'] ?>"<?= (int) $event['id'] === (int) $option['id'] ? ' selected' : '' ?>>
            <?= e(format_date($option['date']) . ' – ' . $option['title']) ?><?= $option['active'] ? ' (aktiv)' : '' ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label for="f-status" class="form-label small mb-1">Status</label>
      <select id="f-status" name="status" class="form-select">
        <option value="">alle</option>
        <?php foreach (STATUS_LABELS as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label for="f-q" class="form-label small mb-1">Suche</label>
      <input type="search" id="f-q" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Name, Ort, E-Mail">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-outline-primary">Filtern</button>
      <?php if ($status !== '' || $search !== ''): ?>
        <a href="<?= e(url('admin/registrations.php?event=' . (int) $event['id'])) ?>" class="btn btn-link">Zurücksetzen</a>
      <?php endif ?>
    </div>
  </form>

  <p class="small text-body-secondary mb-2">
    <?= count($rows) ?> <?= count($rows) === 1 ? 'Anfrage' : 'Anfragen' ?>
    · <strong>fett</strong> = bevorzugter Ort · 2️⃣ = mögliche Dublette
  </p>

  <?php if (!$rows): ?>
    <p class="text-body-secondary">Keine Anfragen gefunden.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Eingang</th>
            <th>Name</th>
            <th class="text-end">Personen</th>
            <th>Ort</th>
            <th>Status</th>
            <th>Mail</th>
            <th class="text-end">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php $name = $row['first_name'] . ' ' . $row['last_name'] ?>
            <tr>
              <td class="text-nowrap small"><?= e(format_utc_datetime($row['created_at'], $event['timezone'])) ?></td>
              <td>
                <?php if ($row['is_duplicate']): ?>
                  <span title="Mögliche Dublette: gleiche E-Mail oder gleicher Name"
                        aria-label="Mögliche Dublette">2️⃣</span>
                <?php endif ?>
                <?= e($name) ?>
                <div class="small text-body-secondary"><?= e($row['no_email'] ? 'Tel. ' . $row['phone'] : $row['email']) ?></div>
                <?php if (($row['message'] ?? '') !== ''): ?>
                  <div class="small text-body-secondary message-preview" title="<?= e($row['message']) ?>">
                    <span aria-hidden="true">💬</span><span class="visually-hidden">Nachricht:</span> <?= e($row['message']) ?>
                  </div>
                <?php endif ?>
              </td>
              <td class="text-end tabular-nums"><?= (int) $row['person_count'] ?></td>
              <td>
                <?php if ($row['is_preferred_place']): ?>
                  <strong><?= e($row['congregation']) ?></strong>
                  <span class="visually-hidden">(bevorzugter Ort)</span>
                <?php else: ?>
                  <?= e($row['congregation']) ?>
                <?php endif ?>
              </td>
              <td><span class="badge <?= $statusBadge[$row['status']] ?>"><?= e(STATUS_LABELS[$row['status']]) ?></span></td>
              <td class="small">
                <?php if ($row['no_email']): ?>
                  <span class="text-body-secondary">keine E-Mail</span>
                <?php elseif ($row['mail'] !== null): ?>
                  <span title="<?= e($row['mail']['error'] ?? '') ?>">
                    <?= $row['mail']['success'] ? '✓' : '<span class="text-danger-emphasis">✗ Fehler:</span>' ?>
                    <?= e(MAIL_TYPES[$row['mail']['type']] ?? $row['mail']['type']) ?>
                  </span>
                  <div class="text-body-secondary"><?= e(format_utc_datetime($row['mail']['sent_at'], $event['timezone'])) ?></div>
                <?php else: ?>
                  <span class="text-body-secondary">–</span>
                <?php endif ?>
              </td>
              <td class="text-end text-nowrap">
                <?php
                // Reihenfolge: Bestätigen, Details, Ablehnen. Bestätigen/Ablehnen bleiben stehen;
                // ist der Status schon erreicht, grau und nicht klickbar (SPEC §7.2)
                $statusButton = function (string $action) use ($row, $name): string {
                    [$label, $class, $target] = $action === 'confirm'
                        ? ['Bestätigen', 'btn-outline-success', 'confirmed']
                        : ['Ablehnen', 'btn-outline-warning', 'rejected'];
                    if ($row['status'] === $target) {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Bereits '
                            . e(STATUS_LABELS[$target]) . '">' . $label . '</button>';
                    }
                    return '<button type="button" class="btn btn-sm ' . $class . '"'
                        . ' data-bs-toggle="modal" data-bs-target="#status-modal"'
                        . ' data-action="' . $action . '" data-id="' . (int) $row['id'] . '" data-name="' . e($name) . '"'
                        . ' data-count="' . (int) $row['person_count'] . '"'
                        . ' data-email="' . e($row['no_email'] ? '' : (string) $row['email']) . '"'
                        . ' data-phone="' . e((string) $row['phone']) . '"'
                        . ' data-exceeds="' . ($action === 'confirm' && $row['exceeds_quota'] ? '1' : '0') . '">' . $label . '</button>';
                };
                ?>
                <?= $statusButton('confirm') ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-details-toggle
                        aria-expanded="false" aria-controls="details-<?= (int) $row['id'] ?>">Details</button>
                <?= $statusButton('reject') ?>
              </td>
            </tr>
            <?php
            // Spalten: nur Gruppen mit Personen in dieser Anmeldung
            $rowGroups = array_filter($eventGroups, fn ($group) => (int) $row[$group] > 0, ARRAY_FILTER_USE_KEY);
            ?>
            <tr class="registration-details" id="details-<?= (int) $row['id'] ?>" hidden>
              <td colspan="7">
                <div class="row g-3">
                  <div class="col-12">
                    <div class="small fw-semibold mb-1">
                      Programmpunkte
                      <span class="fw-normal text-body-secondary">
                        (<?= $row['custom_split'] ? 'individuell aufgeteilt' : 'angekreuzt, gilt für die ganze Anmeldung' ?>)
                      </span>
                    </div>
                    <?php if (!$event['slots']): ?>
                      <p class="small text-body-secondary mb-0">Keine Programmpunkte.</p>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm small mb-0 tabular-nums registration-details-table">
                          <thead>
                            <tr>
                              <th>Programmpunkt</th>
                              <?php foreach ($rowGroups as $groupName): ?>
                                <th class="text-end text-nowrap"><?= e($groupName) ?></th>
                              <?php endforeach ?>
                              <th class="text-end">Summe</th>
                              <?php if ($eventHasChildcare): ?>
                                <th class="text-end">Kinderbetreuung</th>
                              <?php endif ?>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($event['slots'] as $slot): ?>
                              <?php
                              $counts = $row['slot_counts'][(int) $slot['id']] ?? [];
                              $target = $slot['target_groups'] ?? [];
                              $sum = array_sum(array_intersect_key($counts, PERSON_GROUPS));
                              $care = array_sum(array_filter($counts, fn ($key) => str_starts_with($key, 'childcare_'), ARRAY_FILTER_USE_KEY));
                              ?>
                              <tr class="<?= $sum + $care === 0 ? 'text-body-secondary' : '' ?>">
                                <td><?= e($slot['time']) ?> <?= e($slot['label']) ?></td>
                                <?php foreach ($rowGroups as $group => $groupName): ?>
                                  <td class="text-end"><?= $target && !in_array($group, $target, true) ? '–' : (int) ($counts[$group] ?? 0) ?></td>
                                <?php endforeach ?>
                                <td class="text-end fw-semibold"><?= $sum ?></td>
                                <?php if ($eventHasChildcare): ?>
                                  <td class="text-end"><?= !empty($slot['childcare']) ? $care : '–' ?></td>
                                <?php endif ?>
                              </tr>
                            <?php endforeach ?>
                          </tbody>
                        </table>
                      </div>
                      <div class="form-text">
                        Summe = Personen beim Programmpunkt<?= $eventHasChildcare ? ', ohne Kinder in der Kinderbetreuung' : '' ?>.
                        „–“ = Gruppe gehört nicht zur Zielgruppe bzw. keine Betreuung.
                      </div>
                    <?php endif ?>
                  </div>
                  <div class="col-12 registration-history">
                    <div class="small fw-semibold mb-1">Verlauf</div>
                    <?php if (!$row['status_log']): ?>
                      <p class="small text-body-secondary mb-0">Noch keine Einträge.</p>
                    <?php else: ?>
                      <div class="table-responsive">
                      <table class="table table-sm small mb-0 tabular-nums text-nowrap registration-details-table">
                        <thead>
                          <tr><th>Datum</th><th>Aktion</th><th>Status</th><th>E-Mail</th></tr>
                        </thead>
                        <tbody>
                          <?php foreach ($row['status_log'] as $entry): ?>
                            <tr>
                              <td><?= e(format_utc_datetime($entry['changed_at'], $event['timezone'])) ?></td>
                              <td><?= e(STATUS_LOG_ACTIONS[$entry['action']] ?? $entry['action']) ?></td>
                              <td><?= e(STATUS_LABELS[$entry['status']] ?? $entry['status']) ?></td>
                              <td><?= $entry['mail'] === 'sent' ? 'ja' : ($entry['mail'] === 'failed' ? '<span class="text-danger-emphasis">fehlgeschlagen</span>' : 'nein') ?></td>
                            </tr>
                          <?php endforeach ?>
                        </tbody>
                      </table>
                      </div>
                    <?php endif ?>
                  </div>
                  <?php if (($row['message'] ?? '') !== ''): ?>
                    <div class="col-12">
                      <div class="small fw-semibold mb-1">Nachricht</div>
                      <div class="small text-pre-line"><?= e($row['message']) ?></div>
                    </div>
                  <?php endif ?>
                  <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                    <a href="<?= e(url('admin/registration.php?id=' . (int) $row['id'])) ?>" class="btn btn-sm btn-outline-orange">Bearbeiten</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#delete-registration-modal"
                            data-id="<?= (int) $row['id'] ?>" data-name="<?= e($name) ?>"
                            data-count="<?= (int) $row['person_count'] ?>">Löschen</button>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <div class="modal fade" id="status-modal" tabindex="-1" aria-labelledby="status-modal-title" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="">
        <input type="hidden" name="id" value="">
        <div class="modal-header">
          <h2 class="modal-title h5" id="status-modal-title" data-status-title></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body">
          <p>Anmeldung von <strong data-status-name></strong> (<span data-status-count></span> Personen).</p>
          <div class="alert alert-warning" role="alert" data-status-exceeds hidden>
            ⚠ Mit dieser Bestätigung wird das Kontingent überschritten. Bestätigen ist trotzdem möglich.
          </div>
          <p class="mb-0" data-status-with-email>E-Mail an <span data-status-email></span> senden?</p>
          <p class="mb-0 text-body-secondary" data-status-without-email hidden>
            Keine E-Mail-Adresse hinterlegt – bitte telefonisch informieren: <span data-status-phone></span>
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" name="send_mail" value="0" class="btn btn-outline-primary" data-status-no-mail>Nein, ohne E-Mail</button>
          <button type="submit" name="send_mail" value="1" class="btn btn-primary" data-status-mail>Ja, mit E-Mail</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="delete-registration-modal" tabindex="-1" aria-labelledby="delete-registration-title" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="">
        <div class="modal-header">
          <h2 class="modal-title h5" id="delete-registration-title">Anmeldung löschen</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body">
          <p>Anmeldung von <strong data-delete-name></strong> (<span data-delete-count></span> Personen) endgültig löschen?</p>
          <p class="mb-0 text-body-secondary small">
            Die Aufteilung auf Programmpunkte und das Mail-Protokoll werden ebenfalls gelöscht. Es wird keine E-Mail verschickt.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-danger">Endgültig löschen</button>
        </div>
      </form>
    </div>
  </div>
<?php endif ?>
