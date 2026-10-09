<?php
/* Farbschema-Auswahl für den Footer der öffentlichen Seiten: Optionen
   nebeneinander statt Dropdown; ohne JS ausgeblendet (theme.js blendet sie ein). */
require __DIR__ . '/theme_icons.php';
?>
<div data-theme-toggle hidden>
  <div class="btn-group btn-group-sm" role="group" aria-label="Farbschema">
    <?php foreach ($themeLabels as $theme => $label): ?>
      <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
              data-theme-value="<?= $theme ?>" aria-pressed="false">
        <?= $themeIcon($theme) ?> <?= $label ?>
      </button>
    <?php endforeach ?>
  </div>
</div>
