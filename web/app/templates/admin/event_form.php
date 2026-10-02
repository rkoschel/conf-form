<h1 class="h3 mb-4"><?= e($title) ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
<?php endif ?>

<form method="post" class="vstack gap-4" novalidate>
  <?= csrf_field() ?>

  <fieldset class="row g-3">
    <legend class="h5">Eckdaten</legend>
    <div class="col-12"><?= input_field($form, $errors, 'title', 'Titel', 'text', 'required') ?></div>
    <div class="col-sm-6 col-lg-3">
      <?= input_field($form, $errors, 'date', 'Datum', 'text', 'placeholder="TT.MM.JJJJ" autocomplete="off" required') ?>
    </div>
    <div class="col-sm-6 col-lg-9"><?= input_field($form, $errors, 'location', 'Ort', 'text', 'required') ?></div>
    <div class="col-sm-6 col-lg-3">
      <label for="f-registration_deadline_date" class="form-label">Anmeldefrist</label>
      <div class="input-group<?= isset($errors['registration_deadline']) ? ' has-validation' : '' ?>">
        <input type="text" id="f-registration_deadline_date" name="registration_deadline_date"
               value="<?= e($form['registration_deadline_date'] ?? '') ?>" placeholder="TT.MM.JJJJ" autocomplete="off"
               class="form-control<?= invalid_class($errors, 'registration_deadline') ?>" required>
        <input type="text" name="registration_deadline_time" aria-label="Uhrzeit der Anmeldefrist"
               value="<?= e($form['registration_deadline_time'] ?? '') ?>" placeholder="HH:MM" autocomplete="off"
               class="form-control time-input<?= invalid_class($errors, 'registration_deadline') ?>" required>
        <?= field_error($errors, 'registration_deadline') ?>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <label for="f-timezone" class="form-label">Zeitzone</label>
      <select id="f-timezone" name="timezone" class="form-select<?= invalid_class($errors, 'timezone') ?>" required>
        <?php foreach (timezone_options() as $region => $zones): ?>
          <optgroup label="<?= e($region) ?>">
            <?php foreach ($zones as $zone): ?>
              <option value="<?= e($zone) ?>"<?= ($form['timezone'] ?? '') === $zone ? ' selected' : '' ?>><?= e($zone) ?></option>
            <?php endforeach ?>
          </optgroup>
        <?php endforeach ?>
      </select>
      <?= field_error($errors, 'timezone') ?>
    </div>
    <div class="col-sm-6 col-lg-2">
      <?= input_field($form, $errors, 'max_participants', 'Max. Teilnehmer', 'number', 'min="1" step="1" required') ?>
    </div>
    <div class="col-12">
      <div class="form-text mt-0">
        Die Anmeldefrist liegt spätestens zum Beginn des ersten Programmpunkts.
        Kinder von 0–2 Jahren zählen nicht zum Kontingent.
      </div>
    </div>
    <div class="col-12">
      <label for="f-description" class="form-label">Beschreibung</label>
      <textarea id="f-description" name="description" rows="5" class="form-control"><?= e($form['description'] ?? '') ?></textarea>
      <div class="form-text">Klartext, Zeilenumbrüche bleiben erhalten.</div>
    </div>
    <div class="col-sm-6"><?= input_field($form, $errors, 'organizer_name', 'Veranstalter – Name') ?></div>
    <div class="col-sm-6"><?= input_field($form, $errors, 'organizer_email', 'Veranstalter – E-Mail', 'email') ?></div>
    <div class="col-12">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="f-active" name="active" value="1"
               <?= !empty($form['active']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="f-active">Aktiv (deaktiviert alle anderen Veranstaltungen)</label>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend class="h5">Ablauf</legend>
    <?php if ($slotsLocked): ?>
      <div class="alert alert-info">
        Es gibt bereits Anmeldungen – der Ablauf kann nicht mehr geändert werden.
      </div>
      <ul class="list-group">
        <?php foreach ($form['slots'] as $slot): ?>
          <li class="list-group-item">
            <strong><?= e($slot['time']) ?></strong> <?= e($slot['label']) ?>
            <?php if ($slot['childcare']): ?>
              <span class="text-body-secondary small">· Kinderbetreuung <?= e(childcare_ages($slot['childcare'])) ?> Jahre</span>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    <?php else: ?>
      <div id="slot-rows" data-next-index="<?= count($form['slots']) ?>">
        <?php foreach (array_values($form['slots']) as $i => $slot): ?>
          <?php
          // Aus der DB: childcare = Liste; aus dem Formular: childcare = '1' + childcare_groups
          $groups = is_array($slot['childcare'] ?? null) ? $slot['childcare'] : ($slot['childcare_groups'] ?? []);
          $row = ['index' => $i, 'time' => $slot['time'], 'label' => $slot['label'],
              'childcare' => !empty($slot['childcare']), 'groups' => $groups];
          ?>
          <?php require __DIR__ . '/slot_row.php' ?>
        <?php endforeach ?>
      </div>
      <?php if (isset($errors['slots'])): ?>
        <div class="text-danger small mb-2"><?= e($errors['slots']) ?></div>
      <?php endif ?>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-add-slot>+ Programmpunkt</button>
      <div class="form-text">
        Wird automatisch nach Uhrzeit sortiert. Leere Zeilen werden ignoriert.
        Kinderbetreuung: Kinder der gewählten Altersgruppen werden bei der Anmeldung automatisch für die Betreuung gezählt.
      </div>
      <template id="slot-row-template">
        <?php $row = ['index' => '__INDEX__', 'time' => '', 'label' => '', 'childcare' => false, 'groups' => []] ?>
        <?php require __DIR__ . '/slot_row.php' ?>
      </template>
    <?php endif ?>
  </fieldset>

  <p class="form-text mb-0">
    Die bevorzugten Orte gelten für alle Veranstaltungen und werden in den
    <a href="<?= e(url('admin/settings.php')) ?>">Einstellungen</a> gepflegt.
  </p>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Speichern</button>
    <a href="<?= e(url('admin/events.php')) ?>" class="btn btn-outline-secondary">Abbrechen</a>
  </div>
</form>
