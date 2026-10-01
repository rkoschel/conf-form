<h1 class="h3 mb-4">Einstellungen</h1>

<form method="post" class="vstack gap-3 form-narrow">
  <?= csrf_field() ?>
  <div>
    <label for="inactive_text" class="form-label">Text, wenn keine Veranstaltung aktiv ist</label>
    <textarea id="inactive_text" name="inactive_text" rows="5"
              class="form-control<?= invalid_class($errors, 'inactive_text') ?>"><?= e($text) ?></textarea>
    <?= field_error($errors, 'inactive_text') ?>
    <div class="form-text">Wird auf der Infoseite angezeigt. Klartext, Zeilenumbrüche bleiben erhalten.</div>
  </div>
  <div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </div>
</form>
