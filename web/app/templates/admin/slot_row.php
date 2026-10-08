<?php
/**
 * Eine Zeile im Ablauf; erwartet $row = [index, time, label, childcare (bool),
 * groups (Liste)] sowie $selectedGroups und $groupNames aus event_form.php.
 * Zur Kinderbetreuung stehen nur Kindergruppen; nicht gewählte sind
 * ausgeblendet (admin.js gleicht Auswahl und Namen beim Ändern ab).
 */
?>
<div class="row g-2 mb-2 slot-row">
  <div class="col-4 col-sm-3 col-lg-2">
    <label class="visually-hidden" for="slot-<?= e($row['index']) ?>-time">Uhrzeit</label>
    <input type="text" class="form-control" placeholder="HH:MM" autocomplete="off" id="slot-<?= e($row['index']) ?>-time"
           name="slots[<?= e($row['index']) ?>][time]" value="<?= e($row['time']) ?>">
  </div>
  <div class="col">
    <label class="visually-hidden" for="slot-<?= e($row['index']) ?>-label">Bezeichnung</label>
    <input type="text" class="form-control" id="slot-<?= e($row['index']) ?>-label" placeholder="Bezeichnung"
           name="slots[<?= e($row['index']) ?>][label]" value="<?= e($row['label']) ?>">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-outline-danger" data-remove-slot aria-label="Programmpunkt entfernen">×</button>
  </div>
  <div class="col-12">
    <div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1 small ps-1">
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" role="switch" value="1" data-childcare-toggle
               id="slot-<?= e($row['index']) ?>-childcare" name="slots[<?= e($row['index']) ?>][childcare]"
               <?= $row['childcare'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="slot-<?= e($row['index']) ?>-childcare">Kinderbetreuung</label>
      </div>
      <?php /* hidden am Wrapper: d-flex würde das hidden-Attribut überschreiben */ ?>
      <div data-childcare-groups<?= $row['childcare'] ? '' : ' hidden' ?>><div class="d-flex flex-wrap column-gap-3">
        <?php foreach (kids_groups(array_keys(PERSON_GROUPS)) as $group): ?>
          <div class="form-check mb-0" data-childcare-option="<?= e($group) ?>"<?= in_array($group, $selectedGroups, true) ? '' : ' hidden' ?>>
            <input class="form-check-input" type="checkbox" value="<?= e($group) ?>"
                   id="slot-<?= e($row['index']) ?>-<?= e($group) ?>" name="slots[<?= e($row['index']) ?>][childcare_groups][]"
                   <?= in_array($group, $row['groups'], true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="slot-<?= e($row['index']) ?>-<?= e($group) ?>"><span data-childcare-label="<?= e($group) ?>"><?= e($groupNames[$group]) ?></span></label>
          </div>
        <?php endforeach ?>
      </div></div>
    </div>
  </div>
</div>
