<?php
/* Anmeldeformular (SPEC §5.1) */
$checked = fn (string $name): string => !empty($form[$name]) ? 'checked' : '';
$attend = is_array($form['attend'] ?? null) ? $form['attend'] : [];
$split = is_array($form['split'] ?? null) ? $form['split'] : [];
$noEmail = !empty($form['no_email']);
$customSplit = !empty($form['custom_split']);
?>
<h1 class="h3 mb-1">Anmeldung</h1>
<p class="lead mb-4"><?= e($event['title']) ?>, <?= e(format_date_long($event['date'])) ?></p>

<?php if ($notice !== null): ?>
  <div class="alert alert-warning" role="alert"><?= e($notice) ?></div>
<?php elseif ($errors): ?>
  <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
<?php endif ?>

<form method="post" action="<?= e(url('register/')) ?>" class="vstack gap-4" id="register-form" novalidate>
  <?= csrf_field() ?>
  <?= spam_fields() ?>

  <fieldset class="row g-3">
    <legend class="h5">Kontakt</legend>
    <div class="col-sm-6"><?= input_field($form, $errors, 'first_name', 'Vorname', 'text', 'required autocomplete="given-name"') ?></div>
    <div class="col-sm-6"><?= input_field($form, $errors, 'last_name', 'Nachname', 'text', 'required autocomplete="family-name"') ?></div>
    <div class="col-12">
      <?= input_field($form, $errors, 'congregation', 'Heimatversammlung', 'text', 'required list="places" autocomplete="off"') ?>
      <datalist id="places">
        <?php foreach ($event['places'] as $place): ?>
          <option value="<?= e($place) ?>">
        <?php endforeach ?>
      </datalist>
    </div>
    <div class="col-12" data-email-field<?= $noEmail ? ' hidden' : '' ?>>
      <?= input_field($form, $errors, 'email', 'E-Mail', 'email', 'autocomplete="email"') ?>
    </div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="f-no_email" name="no_email" value="1" <?= $checked('no_email') ?>>
        <label class="form-check-label" for="f-no_email">Ich habe keine E-Mail-Adresse</label>
      </div>
    </div>
    <div class="col-12" data-phone-field<?= $noEmail ? '' : ' hidden' ?>>
      <?= input_field($form, $errors, 'phone', 'Telefon', 'tel', 'autocomplete="tel"') ?>
      <div class="form-text">Ohne E-Mail-Adresse melden wir uns telefonisch bei dir.</div>
    </div>
  </fieldset>

  <fieldset class="row g-3">
    <legend class="h5 mb-0">Anzahl Teilnehmer</legend>
    <?php foreach (AGE_GROUPS as $group => $label): ?>
      <div class="col-6 col-md">
        <?= input_field($form + [$group => '0'], $errors, $group, $label, 'number',
            'min="0" max="' . MAX_PER_GROUP . '" step="1" inputmode="numeric" data-count="' . $group . '"') ?>
      </div>
    <?php endforeach ?>
    <?php if (isset($errors['persons'])): ?>
      <div class="col-12 text-danger small"><?= e($errors['persons']) ?></div>
    <?php endif ?>
  </fieldset>

  <?php if ($event['slots']): ?>
    <fieldset>
      <legend class="h5">Voraussichtliche Anwesenheit</legend>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="f-custom_split" name="custom_split" value="1"
               <?= $checked('custom_split') ?>>
        <label class="form-check-label" for="f-custom_split">Anzahl individuell aufteilen</label>
      </div>
      <?php if (isset($errors['split'])): ?>
        <div class="text-danger small mb-2"><?= e($errors['split']) ?></div>
      <?php endif ?>

      <div class="vstack gap-2">
        <?php foreach ($event['slots'] as $slot): ?>
          <?php $slotId = (int) $slot['id'] ?>
          <div class="border rounded p-3">
            <div class="form-check" data-attend<?= $customSplit ? ' hidden' : '' ?>>
              <input class="form-check-input" type="checkbox" id="attend-<?= $slotId ?>"
                     name="attend[<?= $slotId ?>]" value="1" <?= !empty($attend[$slotId]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="attend-<?= $slotId ?>">
                <span class="fw-semibold"><?= e($slot['time']) ?> Uhr</span> <?= e($slot['label']) ?>
              </label>
            </div>
            <div data-split<?= $customSplit ? '' : ' hidden' ?>>
              <div class="mb-2"><span class="fw-semibold"><?= e($slot['time']) ?> Uhr</span> <?= e($slot['label']) ?></div>
              <div class="row g-2">
                <?php foreach (AGE_GROUPS as $group => $label): ?>
                  <?php $value = $split[$slotId][$group] ?? ($form[$group] ?? '0') ?>
                  <div class="col-6 col-md" data-split-group="<?= $group ?>">
                    <label class="form-label small mb-1" for="split-<?= $slotId ?>-<?= $group ?>"><?= e($label) ?></label>
                    <input type="number" class="form-control form-control-sm" min="0" step="1" inputmode="numeric"
                           id="split-<?= $slotId ?>-<?= $group ?>" name="split[<?= $slotId ?>][<?= $group ?>]"
                           value="<?= e(is_string($value) ? $value : '0') ?>">
                  </div>
                <?php endforeach ?>
              </div>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </fieldset>
  <?php endif ?>

  <div>
    <button type="submit" class="btn btn-primary btn-lg">Teilnahme anfragen</button>
    <p class="form-text mt-2">
      Mit dem Absenden werden deine Angaben zur Organisation der Veranstaltung gespeichert.
      Details in der <a href="<?= e(config('privacy_url')) ?>">Datenschutzerklärung</a>.
    </p>
  </div>
</form>
