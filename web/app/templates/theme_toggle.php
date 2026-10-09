<?php
/* Umschalter Farbschema als Dropdown (Admin-Navigation); ohne JS ausgeblendet
   (theme.js blendet ihn ein). */
require __DIR__ . '/theme_icons.php';
?>
<div class="dropdown" data-theme-toggle hidden>
  <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle d-inline-flex align-items-center gap-1"
          data-bs-toggle="dropdown" aria-expanded="false" aria-label="Farbschema" title="Farbschema" data-theme-current>
    <?php foreach ($themeLabels as $theme => $label): ?>
      <?= $themeIcon($theme, 'data-theme-icon="' . $theme . '"' . ($theme === 'auto' ? '' : ' hidden')) ?>
    <?php endforeach ?>
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    <?php foreach ($themeLabels as $theme => $label): ?>
      <li>
        <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-theme-value="<?= $theme ?>">
          <?= $themeIcon($theme) ?> <?= $label ?>
        </button>
      </li>
    <?php endforeach ?>
  </ul>
</div>
