<?php
/*
 * Felder einer Anmeldung: Kontakt, Anzahl Teilnehmer, Anwesenheit/Aufteilung
 * (SPEC §5.1). Genutzt vom Anmeldeformular und vom Admin-Bearbeiten (§7.4).
 * Erwartet $form, $errors, $event (mit 'slots' und 'places'). Die ids und
 * data-Attribute nutzt assets/form.js.
 */
$checked = fn (string $name): string => !empty($form[$name]) ? 'checked' : '';
$attend = is_array($form['attend'] ?? null) ? $form['attend'] : [];
$split = is_array($form['split'] ?? null) ? $form['split'] : [];
$noEmail = !empty($form['no_email']);
$customSplit = !empty($form['custom_split']);
$childcareSlots = array_values(array_filter($event['slots'], fn ($slot) => !empty($slot['childcare'])));
$hasCount = fn (string $group): bool => (int) ($form[$group] ?? 0) > 0;
// Sichtbarkeit der Hinweise ohne JS; form.js aktualisiert sie beim Tippen
$childcareVisible = fn (array $slot): bool => (bool) array_filter($slot['childcare'], $hasCount);
?>
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
            'min="0" step="1" inputmode="numeric" data-count="' . $group . '"') ?>
      </div>
    <?php endforeach ?>
    <?php if (isset($errors['persons'])): ?>
      <div class="col-12 text-danger small"><?= e($errors['persons']) ?></div>
    <?php endif ?>
    <?php if ($childcareSlots): ?>
      <div class="col-12" data-childcare-hint<?= array_filter($childcareSlots, $childcareVisible) ? '' : ' hidden' ?>>
        <div class="alert alert-info small mb-0">
          Kinder werden bei diesen Programmpunkten automatisch für die Kinderbetreuung berücksichtigt:
          <ul class="mb-0">
            <?php foreach ($childcareSlots as $slot): ?>
              <li data-childcare-groups="<?= e(implode(' ', $slot['childcare'])) ?>"<?= $childcareVisible($slot) ? '' : ' hidden' ?>>
                <?= e($slot['time']) ?> Uhr <?= e($slot['label']) ?>: Kinder von <?= e(childcare_ages($slot['childcare'])) ?> Jahren
              </li>
            <?php endforeach ?>
          </ul>
        </div>
      </div>
    <?php endif ?>
  </fieldset>

  <?php if ($event['slots']): ?>
    <fieldset>
      <legend class="h5 mb-1">Voraussichtliche Anwesenheit</legend>
      <p class="form-text mt-0 mb-3">
        Deine Angaben helfen uns, die Räumlichkeiten besser zu nutzen und möglichst vielen die Teilnahme zu ermöglichen.
      </p>
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
          <div class="border rounded p-3" data-childcare-slot="<?= e(implode(' ', $slot['childcare'] ?? [])) ?>">
            <div class="form-check" data-attend<?= $customSplit ? ' hidden' : '' ?>>
              <input class="form-check-input" type="checkbox" id="attend-<?= $slotId ?>"
                     name="attend[<?= $slotId ?>]" value="1" <?= !empty($attend[$slotId]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="attend-<?= $slotId ?>">
                <span class="fw-semibold"><?= e($slot['time']) ?> Uhr</span> <?= e($slot['label']) ?>
                <?php if (!empty($slot['childcare'])): ?>
                  <span class="d-block small text-body-secondary"><?= e(childcare_notice($slot['childcare'])) ?></span>
                <?php endif ?>
              </label>
            </div>
            <div data-split<?= $customSplit ? '' : ' hidden' ?>>
              <div class="mb-2">
                <span class="fw-semibold"><?= e($slot['time']) ?> Uhr</span> <?= e($slot['label']) ?>
                <?php if (!empty($slot['childcare'])): ?>
                  <span class="d-block small text-body-secondary"><?= e(childcare_notice($slot['childcare'])) ?></span>
                <?php endif ?>
              </div>
              <div class="row g-2">
                <?php foreach (AGE_GROUPS as $group => $label): ?>
                  <?php
                  // Betreute Kindergruppen sind mit 0 vorbelegt (sie sind in der Kinderbetreuung)
                  $default = in_array($group, $slot['childcare'] ?? [], true) ? '0' : ($form[$group] ?? '0');
                  $value = $split[$slotId][$group] ?? $default;
                  ?>
                  <div class="col-6 col-md" data-split-group="<?= $group ?>">
                    <label class="form-label small mb-1" for="split-<?= $slotId ?>-<?= $group ?>"><?= e($label) ?></label>
                    <input type="number" class="form-control form-control-sm" min="0" step="1" inputmode="numeric"
                           id="split-<?= $slotId ?>-<?= $group ?>" name="split[<?= $slotId ?>][<?= $group ?>]"
                           value="<?= e(is_string($value) ? $value : '0') ?>">
                  </div>
                <?php endforeach ?>
              </div>
              <?php if (!empty($slot['childcare'])): ?>
                <div class="small text-info-emphasis mt-2" data-childcare-split-hint hidden>
                  Eingetragene Kinder (<?= e(childcare_ages($slot['childcare'])) ?> J.) nehmen teil,
                  die übrigen sind in der Kinderbetreuung eingeplant.
                </div>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>

      <div class="modal fade" id="split-reset-modal" tabindex="-1" aria-labelledby="split-reset-title" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h2 class="modal-title h5" id="split-reset-title">Aufteilung zurücksetzen?</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
              Die individuelle Aufteilung auf die Programmpunkte geht dabei verloren.
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Aufteilung behalten</button>
              <button type="button" class="btn btn-primary" data-split-reset-confirm>Zurücksetzen</button>
            </div>
          </div>
        </div>
      </div>
    </fieldset>
  <?php endif ?>
