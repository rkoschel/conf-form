<?php
/*
 * Einstellungen (SPEC §7.5, §7.8) in Tabs. Alle Textfelder gehören per
 * form="settings-form" zu einem Formular (Speichern speichert alle Tabs);
 * der Team-Zugang hat eigene Formulare. Leere Textfelder zeigen den
 * Standardtext grau (placeholder).
 */
$sections = [];
foreach (SETTING_TEXTS as $key => $text) {
    $sections[$text['section']][$key] = $text;
}
$tabId = fn (string $label): string => trim((string) preg_replace(
    '/[^a-z0-9]+/',
    '-',
    strtr(mb_strtolower($label), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'])
), '-');
$tabs = [];
foreach (array_keys($sections) as $section) {
    $tabs[$tabId($section)] = $section;
}
$tabs['team'] = 'Team-Zugang';
$firstTab = array_key_first($tabs);

// Höhe nach dem längeren von Standardtext und eigenem Text (ca. 70 Zeichen je Zeile)
$rows = function (string $key, string $default) use ($values): int {
    $lines = 0;
    foreach (explode("\n", strlen($values[$key]) > strlen($default) ? $values[$key] : $default) as $line) {
        $lines += max(1, (int) ceil(mb_strlen($line) / 70));
    }
    return max(2, $lines);
};
?>
<h1 class="h3 mb-3">Einstellungen</h1>

<form method="post" id="settings-form">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="<?= e($firstTab) ?>" data-settings-tab>
</form>

<ul class="nav nav-pills flex-wrap gap-1 border-bottom pb-3 mb-4" role="tablist" data-settings-tabs>
  <?php foreach ($tabs as $id => $label): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link<?= $id === $firstTab ? ' active' : '' ?>" id="tab-<?= e($id) ?>" type="button" role="tab"
              data-bs-toggle="tab" data-bs-target="#pane-<?= e($id) ?>" aria-controls="pane-<?= e($id) ?>"
              aria-selected="<?= $id === $firstTab ? 'true' : 'false' ?>">
        <?= e($label) ?>
        <?php if ($id === 'team' && $teamUrl !== null): ?>
          <span class="badge text-bg-success ms-1">aktiv</span>
        <?php endif ?>
      </button>
    </li>
  <?php endforeach ?>
</ul>

<div class="tab-content form-narrow">
  <?php foreach ($sections as $section => $texts): ?>
    <?php $id = $tabId($section) ?>
    <div class="tab-pane<?= $id === $firstTab ? ' active' : '' ?>" id="pane-<?= e($id) ?>"
         role="tabpanel" aria-labelledby="tab-<?= e($id) ?>" tabindex="0">
      <div class="vstack gap-4">
        <?php if ($id === $firstTab): ?>
          <div>
            <label for="preferred_places" class="form-label">Bevorzugte Orte</label>
            <textarea id="preferred_places" name="preferred_places" form="settings-form" rows="6"
                      class="form-control"><?= e($places) ?></textarea>
            <div class="form-text">
              Ein Ort pro Zeile, gilt für alle Veranstaltungen. Anmeldungen aus diesen Orten werden automatisch
              bestätigt, solange das Kontingent reicht; die Orte erscheinen als Vorschläge im Anmeldeformular.
              Änderungen wirken nur auf neue Anmeldungen.
            </div>
          </div>
        <?php endif ?>

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
              <input type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" form="settings-form" class="form-control"
                     value="<?= e($values[$key]) ?>" placeholder="<?= e($text['default']) ?>">
            <?php else: ?>
              <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" form="settings-form" class="form-control"
                        rows="<?= $rows($key, $text['default']) ?>"
                        placeholder="<?= e($text['default']) ?>"><?= e($values[$key]) ?></textarea>
            <?php endif ?>
            <?php if (isset($text['help'])): ?>
              <div class="form-text"><?= e($text['help']) ?></div>
            <?php endif ?>
          </div>
        <?php endforeach ?>

        <div class="d-flex flex-wrap align-items-center gap-3 border-top pt-3">
          <button type="submit" form="settings-form" class="btn btn-primary">Speichern</button>
          <span class="form-text m-0">
            Speichert alle Tabs. Leeres Feld = Standardtext (grau angezeigt); Klartext, Zeilenumbrüche bleiben erhalten.
          </span>
        </div>
      </div>
    </div>
  <?php endforeach ?>

  <div class="tab-pane" id="pane-team" role="tabpanel" aria-labelledby="tab-team" tabindex="0">
    <p class="mt-0">
      Mitarbeiter sehen über diesen Link die Auswertung der aktiven Veranstaltung – ohne Login, nur lesend.
      Wer den Link hat, sieht die Zahlen. Wurde er versehentlich weitergegeben, einfach einen neuen erzeugen.
    </p>
    <?php if ($teamUrl === null): ?>
      <form method="post">
        <?= csrf_field() ?>
        <button type="submit" name="action" value="team_generate" class="btn btn-outline-primary">Team-Link erzeugen</button>
      </form>
    <?php else: ?>
      <div class="input-group mb-2">
        <input type="text" class="form-control" id="team-url" value="<?= e($teamUrl) ?>" readonly aria-label="Team-Link">
        <button type="button" class="btn btn-outline-secondary" data-copy="#team-url">Kopieren</button>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <form method="post" data-confirm="Neuen Link erzeugen? Der bisherige Link funktioniert dann nicht mehr.">
          <?= csrf_field() ?>
          <button type="submit" name="action" value="team_generate" class="btn btn-sm btn-outline-secondary">Neuen Link erzeugen</button>
        </form>
        <form method="post" data-confirm="Team-Zugang deaktivieren? Der Link funktioniert dann nicht mehr.">
          <?= csrf_field() ?>
          <button type="submit" name="action" value="team_disable" class="btn btn-sm btn-outline-danger">Deaktivieren</button>
        </form>
      </div>
    <?php endif ?>
  </div>
</div>
