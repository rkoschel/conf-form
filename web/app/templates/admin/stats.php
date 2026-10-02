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

<?php require dirname(__DIR__) . '/stats_content.php' ?>
