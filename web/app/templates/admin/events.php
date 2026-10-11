<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Veranstaltungen</h1>
  <a href="<?= e(url('admin/event.php')) ?>" class="btn btn-primary">Neue Veranstaltung</a>
</div>

<?php
// Titel der aktiven Veranstaltung: Hinweis im Dialog, dass sie beim Aktivieren einer anderen deaktiviert wird
$activeTitle = '';
foreach ($events as $listed) {
    if ($listed['active']) {
        $activeTitle = (string) $listed['title'];
    }
}
?>
<?php if (!$events): ?>
  <p class="text-body-secondary">Noch keine Veranstaltungen angelegt.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Titel</th>
          <th>Datum</th>
          <th>Anmeldefrist</th>
          <th class="text-end">Anmeldungen</th>
          <th class="text-end">Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $event): ?>
          <tr>
            <td>
              <?= e($event['title']) ?>
              <?php if ($event['active']): ?><span class="badge text-bg-success ms-1">aktiv</span><?php endif ?>
            </td>
            <td class="text-nowrap"><?= e(format_date($event['date'])) ?></td>
            <td class="text-nowrap">
              <?= e(format_local_datetime($event['registration_deadline'])) ?>
              <?php if ($event['timezone'] !== 'Europe/Berlin'): ?>
                <span class="text-body-secondary small">(<?= e($event['timezone']) ?>)</span>
              <?php endif ?>
            </td>
            <td class="text-end"><?= (int) $event['registration_count'] ?></td>
            <td class="text-end text-nowrap">
              <a href="<?= e(url('admin/event.php?id=' . $event['id'])) ?>" class="btn btn-sm btn-outline-primary">Bearbeiten</a>
              <?php /* Aktivieren/Deaktivieren erst nach Bestätigung im Dialog (admin.js) */ ?>
              <button type="button" class="btn btn-sm <?= $event['active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                      data-bs-toggle="modal" data-bs-target="#active-modal"
                      data-action="<?= $event['active'] ? 'deactivate' : 'activate' ?>" data-id="<?= (int) $event['id'] ?>"
                      data-title="<?= e($event['title']) ?>"
                      data-other="<?= e($event['active'] ? '' : $activeTitle) ?>"><?= $event['active'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
              <button type="button" class="btn btn-sm btn-outline-danger"
                      data-bs-toggle="modal" data-bs-target="#delete-modal"
                      data-id="<?= (int) $event['id'] ?>" data-title="<?= e($event['title']) ?>"
                      data-count="<?= (int) $event['registration_count'] ?>">Löschen</button>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<div class="modal fade" id="active-modal" tabindex="-1" aria-labelledby="active-modal-title" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="">
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h2 class="modal-title h5" id="active-modal-title" data-active-heading></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body" data-active-body></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-primary" data-active-submit></button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="delete-modal-title" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h2 class="modal-title h5" id="delete-modal-title">Veranstaltung löschen</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <p>
          Veranstaltung „<strong data-delete-title></strong>“ mit
          <strong data-delete-count></strong> Anmeldungen endgültig löschen?
        </p>
        <p class="mb-0 text-body-secondary small">
          Ablauf, bevorzugte Orte, Anmeldungen und Mail-Protokoll werden ebenfalls gelöscht.
          Das kann nicht rückgängig gemacht werden.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-danger">Endgültig löschen</button>
      </div>
    </form>
  </div>
</div>
