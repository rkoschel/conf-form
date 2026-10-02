<?php
/* Einstellungen (SPEC §7.5). Leere Textfelder zeigen den Standardtext grau (placeholder). */
$sections = [];
foreach (SETTING_TEXTS as $key => $text) {
    $sections[$text['section']][$key] = $text;
}
// Höhe nach dem längeren von Standardtext und eigenem Text (ca. 70 Zeichen je Zeile)
$rows = function (string $key, string $default) use ($values): int {
    $lines = 0;
    foreach (explode("\n", strlen($values[$key]) > strlen($default) ? $values[$key] : $default) as $line) {
        $lines += max(1, (int) ceil(mb_strlen($line) / 70));
    }
    return max(2, $lines);
};
?>
<h1 class="h3 mb-4">Einstellungen</h1>

<form method="post" class="vstack gap-4 form-narrow">
  <?= csrf_field() ?>

  <fieldset>
    <legend class="h5">Bevorzugte Orte</legend>
    <label for="preferred_places" class="form-label visually-hidden">Bevorzugte Orte</label>
    <textarea id="preferred_places" name="preferred_places" rows="6" class="form-control"><?= e($places) ?></textarea>
    <div class="form-text">
      Ein Ort pro Zeile, gilt für alle Veranstaltungen. Anmeldungen aus diesen Orten werden automatisch
      bestätigt, solange das Kontingent reicht; die Orte erscheinen als Vorschläge im Anmeldeformular.
      Änderungen wirken nur auf neue Anmeldungen.
    </div>
  </fieldset>

  <p class="text-body-secondary mb-0">
    Bei den Texten gilt: Leeres Feld = Standardtext (grau angezeigt). Klartext, Zeilenumbrüche bleiben erhalten.
  </p>

  <?php foreach ($sections as $section => $texts): ?>
    <fieldset class="vstack gap-3">
      <legend class="h5 mb-0"><?= e($section) ?></legend>
      <?php if ($section === 'E-Mails'): ?>
        <div class="form-text mt-0">
          Platzhalter:
          <?php foreach (MAIL_PLACEHOLDERS as $placeholder => $meaning): ?>
            <code><?= e($placeholder) ?></code> <?= e($meaning) ?><?= $placeholder !== array_key_last(MAIL_PLACEHOLDERS) ? ',' : '' ?>
          <?php endforeach ?>
        </div>
      <?php endif ?>
      <?php foreach ($texts as $key => $text): ?>
        <div>
          <label for="<?= e($key) ?>" class="form-label"><?= e($text['label']) ?></label>
          <?php if (!empty($text['line'])): ?>
            <input type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" class="form-control"
                   value="<?= e($values[$key]) ?>" placeholder="<?= e($text['default']) ?>">
          <?php else: ?>
            <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" class="form-control"
                      rows="<?= $rows($key, $text['default']) ?>"
                      placeholder="<?= e($text['default']) ?>"><?= e($values[$key]) ?></textarea>
          <?php endif ?>
          <?php if (isset($text['help'])): ?>
            <div class="form-text"><?= e($text['help']) ?></div>
          <?php endif ?>
        </div>
      <?php endforeach ?>
    </fieldset>
  <?php endforeach ?>

  <div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </div>
</form>
