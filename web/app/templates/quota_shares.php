<?php /* Öffentliche Belegung (SPEC §4): nur ganze Prozent aus quota_shares(), nie absolute Zahlen */ ?>
<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6 text-body-secondary fw-normal mb-2">Belegung</h2>
    <div class="quota-meter mb-2">
      <div class="progress-stacked">
        <?php foreach ([['confirmed', 'Bestätigt', 'bg-primary'], ['waitlist', 'Warteliste', 'progress-bar-striped bg-warning']] as [$key, $label, $class]): ?>
          <?php if ($shares[$key] > 0): ?>
            <div class="progress" role="progressbar" aria-label="<?= $label ?>" style="width: <?= $shares[$key] ?>%"
                 aria-valuenow="<?= $shares[$key] ?>" aria-valuemin="0" aria-valuemax="100" aria-valuetext="<?= $shares[$key] ?> %">
              <div class="progress-bar <?= $class ?>"></div>
            </div>
          <?php endif ?>
        <?php endforeach ?>
      </div>
    </div>
    <div class="d-flex flex-wrap column-gap-3 row-gap-1 small">
      <span><span class="quota-swatch bg-primary"></span> Bestätigt <?= $shares['confirmed'] ?> %</span>
      <span><span class="quota-swatch progress-bar-striped bg-warning"></span> Warteliste <?= $shares['waitlist'] ?> %</span>
      <span><span class="quota-swatch quota-swatch-free"></span> Frei <?= $shares['free'] ?> %</span>
    </div>
    <?php if ($shares['free'] <= 0): ?>
      <div class="alert alert-info small mt-3 mb-0" role="status">
        <span class="text-pre-line"><?= e(setting_text('fully_booked_hint')) ?></span>
      </div>
    <?php endif ?>
  </div>
</div>
