<?php /** Eine Zeile im Ablauf; erwartet $row = [index, time, label] */ ?>
<div class="row g-2 mb-2 slot-row">
  <div class="col-4 col-sm-3 col-lg-2">
    <label class="visually-hidden" for="slot-<?= e($row['index']) ?>-time">Uhrzeit</label>
    <input type="time" class="form-control" id="slot-<?= e($row['index']) ?>-time"
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
</div>
