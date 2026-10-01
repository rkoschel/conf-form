<?php /* Anmeldung bearbeiten (SPEC §7.4); Felder wie im Anmeldeformular */ ?>
<h1 class="h3 mb-1"><?= e($title) ?></h1>
<p class="text-body-secondary mb-4">
  <?= e($event['title']) ?>, <?= e(format_date($event['date'])) ?>
  · Eingang <?= e(format_utc_datetime($registration['created_at'], $event['timezone'])) ?>
</p>

<?php if ($errors): ?>
  <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
<?php endif ?>

<!-- id="register-form": assets/form.js steuert „keine E-Mail“ und die Aufteilung -->
<form method="post" class="vstack gap-4" id="register-form" novalidate>
  <?= csrf_field() ?>

  <fieldset class="row g-3">
    <legend class="h5">Status</legend>
    <div class="col-sm-6 col-lg-3">
      <label for="f-status" class="form-label visually-hidden">Status</label>
      <select id="f-status" name="status" class="form-select<?= invalid_class($errors, 'status') ?>">
        <?php foreach (STATUS_LABELS as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= ($form['status'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach ?>
      </select>
      <?= field_error($errors, 'status') ?>
    </div>
    <div class="col-12 form-text mt-1">
      Beim Speichern wird keine E-Mail verschickt. Für Bestätigen/Ablehnen mit E-Mail die Aktionen in der Liste verwenden.
    </div>
  </fieldset>

  <?php require dirname(__DIR__) . '/registration_fields.php' ?>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Speichern</button>
    <a href="<?= e(url('admin/?event=' . (int) $event['id'])) ?>" class="btn btn-outline-secondary">Abbrechen</a>
  </div>
</form>

<script src="<?= e(asset('form.js')) ?>"></script>
