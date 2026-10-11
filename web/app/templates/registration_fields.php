<?php
/*
 * Felder einer Anmeldung: Kontakt, Anzahl Teilnehmer, Anwesenheit/Aufteilung
 * (SPEC §5.1). Genutzt vom Anmeldeformular und vom Admin-Bearbeiten (§7.4).
 * Erwartet $form, $errors, $event (mit 'slots'). Zeigt nur die
 * Personengruppen der Veranstaltung. Die ids und data-Attribute nutzt
 * assets/form.js.
 */
$groups = event_groups($event);
$checked = fn (string $name): string => !empty($form[$name]) ? 'checked' : '';
$attend = is_array($form['attend'] ?? null) ? $form['attend'] : [];
$split = is_array($form['split'] ?? null) ? $form['split'] : [];
$noEmail = !empty($form['no_email']);
// Individuelle Aufteilung nur, wenn die Veranstaltung sie erlaubt
// ($allowSplit kann die Seite vorgeben, z. B. Admin bei bestehender Aufteilung)
$allowSplit ??= (bool) ($event['allow_split'] ?? true);
$customSplit = $allowSplit && !empty($form['custom_split']);
$childcareSlots = array_values(array_filter($event['slots'], fn ($slot) => !empty($slot['childcare'])));
$hasCount = fn (string $group): bool => (int) ($form[$group] ?? 0) > 0;
// Programmpunkte mit Zielgruppe (SPEC §5.1); [] = für alle
$targetOf = fn (array $slot): array => array_values(array_intersect($slot['target_groups'] ?? [], array_keys($groups)));
$targetSlots = array_values(array_filter($event['slots'], fn ($slot) => $targetOf($slot) !== []));
// Hinweis „nur Personen der Zielgruppe“: nur wenn mindestens eine Person der Zielgruppe
// eingetragen ist (sonst ist der Programmpunkt ausgegraut) und außerdem Personen
// eingetragen sind, die dort nicht zur Zielgruppe gehören
$targetAffected = fn (array $slot): bool => (bool) array_filter($targetOf($slot), $hasCount)
    && array_filter(
        array_keys($groups),
        fn ($group) => $hasCount($group) && !in_array($group, $targetOf($slot), true)
    );
// Sichtbarkeit der Hinweise ohne JS; form.js aktualisiert sie beim Tippen
$childcareVisible = fn (array $slot): bool => (bool) array_filter($slot['childcare'], $hasCount);
$targetHintVisible = (bool) array_filter($targetSlots, $targetAffected);
$childcareHintVisible = (bool) array_filter($childcareSlots, $childcareVisible);
?>
  <fieldset class="row g-3">
    <legend class="h5">Kontakt</legend>
    <div class="col-sm-6"><?= input_field($form, $errors, 'first_name', 'Vorname', 'text', 'required autocomplete="given-name"') ?></div>
    <div class="col-sm-6"><?= input_field($form, $errors, 'last_name', 'Nachname', 'text', 'required autocomplete="family-name"') ?></div>
    <?php /* Vorschläge = bevorzugte Orte (SPEC §5.1): form.js zeigt sie ab zwei Zeichen als eigene Liste
             (gleich auf Mobil und Desktop); ohne JS bleibt die datalist des Browsers */ ?>
    <div class="col-12 position-relative" data-places="<?= e((string) json_encode(preferred_places(), JSON_UNESCAPED_UNICODE)) ?>">
      <?= input_field($form, $errors, 'congregation', setting_text('congregation_label'), 'text', 'required list="places" autocomplete="off"') ?>
      <datalist id="places">
        <?php foreach (preferred_places() as $place): ?>
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
    <?php /* Eine Zeile je Personengruppe: Name links, Feld rechts – so bleibt die Zuordnung
             auch bei langen, umbrechenden Namen eindeutig. − / + blendet form.js ein. */ ?>
    <div class="col-12">
      <div class="count-list">
        <?php foreach ($groups as $group => $label): ?>
          <?php $id = 'f-' . $group ?>
          <div class="count-field" data-count-field>
            <div class="count-row">
              <label for="<?= e($id) ?>" class="count-label"><?= e($label) ?></label>
              <div class="input-group count-input">
                <button type="button" class="btn btn-outline-secondary" data-step="-1"
                        aria-label="<?= e($label) ?>: eine Person weniger" hidden>−</button>
                <input type="number" id="<?= e($id) ?>" name="<?= e($group) ?>" value="<?= e($form[$group] ?? '0') ?>"
                       class="form-control text-center<?= invalid_class($errors, $group) ?>"
                       min="0" step="1" inputmode="numeric" data-count="<?= e($group) ?>">
                <button type="button" class="btn btn-outline-secondary" data-step="1"
                        aria-label="<?= e($label) ?>: eine Person mehr" hidden>+</button>
              </div>
            </div>
            <?php if (($errors[$group] ?? '') !== ''): ?>
              <div class="invalid-feedback d-block"><?= e($errors[$group]) ?></div>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php if (isset($errors['persons'])): ?>
      <div class="col-12 text-danger small"><?= e($errors['persons']) ?></div>
    <?php endif ?>
    <?php if ($targetSlots || $childcareSlots): ?>
      <?php /* gemeinsame Infobox für Kinderbetreuung und Zielgruppe; form.js blendet Teile und Box ein/aus */ ?>
      <div class="col-12" data-info-box<?= $targetHintVisible || $childcareHintVisible ? '' : ' hidden' ?>>
        <div class="alert alert-info small mb-0 vstack gap-2">
          <?php if ($childcareSlots): ?>
            <div data-childcare-hint<?= $childcareHintVisible ? '' : ' hidden' ?>>
              <ul class="mb-0">
                <li>
                  Kinder werden bei diesen Programmpunkten automatisch für die Kinderbetreuung berücksichtigt:
                  <ul class="mb-0">
                    <?php foreach ($childcareSlots as $slot): ?>
                      <li data-childcare-groups="<?= e(implode(' ', $slot['childcare'])) ?>"<?= $childcareVisible($slot) ? '' : ' hidden' ?>>
                        <?= e($slot['time']) ?> Uhr <?= e($slot['label']) ?>: <?= e(childcare_names($slot['childcare'], $groups)) ?>
                      </li>
                    <?php endforeach ?>
                  </ul>
                </li>
              </ul>
            </div>
          <?php endif ?>
          <?php if ($targetSlots): ?>
            <div data-target-hint<?= $targetHintVisible ? '' : ' hidden' ?>>
              <ul class="mb-0">
                <?php foreach ($targetSlots as $slot): ?>
                  <li data-target-groups="<?= e(implode(' ', $targetOf($slot))) ?>"<?= $targetAffected($slot) ? '' : ' hidden' ?>>
                    <?= e($slot['label']) ?> wurde für <?= e(group_names_join($targetOf($slot), $groups)) ?> aktiviert.
                  </li>
                <?php endforeach ?>
              </ul>
            </div>
          <?php endif ?>
        </div>
      </div>
    <?php endif ?>
  </fieldset>

  <?php if ($event['slots']): ?>
    <fieldset>
      <legend class="h5 mb-1">Voraussichtliche Anwesenheit</legend>
      <p class="form-text mt-0 mb-3">
        <span class="text-pre-line"><?= e(setting_text('attendance_hint')) ?></span>
      </p>
      <?php if ($allowSplit): ?>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="f-custom_split" name="custom_split" value="1"
                 <?= $customSplit ? 'checked' : '' ?>>
          <label class="form-check-label" for="f-custom_split">Anzahl individuell aufteilen</label>
        </div>
      <?php endif ?>
      <?php if (isset($errors['split'])): ?>
        <div class="text-danger small mb-2"><?= e($errors['split']) ?></div>
      <?php endif ?>
      <div class="text-danger small mb-2" data-attend-error<?= isset($errors['attend']) ? '' : ' hidden' ?>>
        Bitte mindestens einen Programmpunkt auswählen.
      </div>

      <div class="vstack gap-2">
        <?php foreach ($event['slots'] as $slot): ?>
          <?php $slotId = (int) $slot['id'] ?>
          <div class="border rounded p-3" data-childcare-slot="<?= e(implode(' ', $slot['childcare'] ?? [])) ?>"
               data-target-slot="<?= e(implode(' ', $targetOf($slot))) ?>">
            <div class="form-check" data-attend<?= $customSplit ? ' hidden' : '' ?>>
              <input class="form-check-input" type="checkbox" id="attend-<?= $slotId ?>"
                     name="attend[<?= $slotId ?>]" value="1" <?= !empty($attend[$slotId]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="attend-<?= $slotId ?>">
                <span class="fw-semibold"><?= e($slot['time']) ?> Uhr</span> <?= e($slot['label']) ?>
                <?php if (!empty($slot['childcare'])): ?>
                  <span class="d-block small text-body-secondary"><?= e(childcare_notice($slot['childcare'], $groups)) ?></span>
                <?php endif ?>
                <?php if ($targetOf($slot)): ?>
                  <span class="d-block small text-body-secondary"><?= e(target_notice($targetOf($slot), $groups)) ?></span>
                <?php endif ?>
              </label>
            </div>
            <?php if ($allowSplit): ?>
            <div data-split<?= $customSplit ? '' : ' hidden' ?>>
              <div class="mb-2">
                <span class="fw-semibold"><?= e($slot['time']) ?> Uhr</span> <?= e($slot['label']) ?>
                <?php if (!empty($slot['childcare'])): ?>
                  <span class="d-block small text-body-secondary"><?= e(childcare_notice($slot['childcare'], $groups)) ?></span>
                <?php endif ?>
                <?php if ($targetOf($slot)): ?>
                  <span class="d-block small text-body-secondary"><?= e(target_notice($targetOf($slot), $groups)) ?></span>
                <?php endif ?>
              </div>
              <div class="row g-2">
                <?php foreach ($groups as $group => $label): ?>
                  <?php if ($targetOf($slot) && !in_array($group, $targetOf($slot), true)) { continue; } /* nur Felder der Zielgruppe */ ?>
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
                  Eingetragene Kinder (<?= e(childcare_names($slot['childcare'], $groups)) ?>) nehmen teil,
                  die übrigen sind in der Kinderbetreuung eingeplant.
                </div>
              <?php endif ?>
            </div>
            <?php endif ?>
            <?php if ($targetOf($slot)): ?>
              <div class="small text-body-secondary mt-2" data-target-none hidden>
                Niemand aus deiner Anmeldung gehört zur Zielgruppe.
              </div>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>

      <?php if ($allowSplit): ?>
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
      <?php endif ?>
    </fieldset>
  <?php endif ?>

  <fieldset>
    <legend class="h5">Nachricht an uns <span class="fs-6 fw-normal text-body-secondary">(optional)</span></legend>
    <label for="f-message" class="visually-hidden">Nachricht an uns</label>
    <textarea id="f-message" name="message" rows="3" maxlength="<?= REGISTRATION_MESSAGE_MAX ?>"
              class="form-control<?= invalid_class($errors, 'message') ?>"
              placeholder="<?= e(setting_text('message_placeholder')) ?>"><?= e(is_string($form['message'] ?? null) ? $form['message'] : '') ?></textarea>
    <?= field_error($errors, 'message') ?>
  </fieldset>
