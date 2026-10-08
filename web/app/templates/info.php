<?php /* Infoseite der aktiven Veranstaltung (SPEC §4) als „Einladungskarte“; Belegung nur in Prozent, nie absolute Zahlen */ ?>
<article class="invitation">
  <header class="invitation-header">
    <h1 class="invitation-title"><?= e($event['title']) ?></h1>
    <p class="invitation-meta mb-1">
      <span class="text-nowrap"><?= e(format_date_long($event['date'])) ?></span>
      <span aria-hidden="true">·</span>
      <span><?= e($event['location']) ?></span>
    </p>
    <p class="invitation-meta small mb-0">
      Anmeldung bis <?= e(format_local_datetime($event['registration_deadline'])) ?>
      <?php if ($event['timezone'] !== 'Europe/Berlin'): ?>
        (<?= e($event['timezone']) ?>)
      <?php endif ?>
    </p>
  </header>

  <?php if ($event['description'] !== ''): ?>
    <div class="invitation-description text-pre-line"><?= e($event['description']) ?></div>
  <?php endif ?>

  <?php if ($event['slots']): ?>
    <section class="invitation-section">
      <h2 class="invitation-heading">Ablauf</h2>
      <ol class="invitation-schedule">
        <?php foreach ($event['slots'] as $slot): ?>
          <li>
            <span class="invitation-time"><?= e($slot['time']) ?> Uhr</span>
            <span>
              <?= e($slot['label']) ?>
              <?php if ($slot['childcare']): ?>
                <span class="d-block small text-body-secondary"><?= e(childcare_notice($slot['childcare'], event_groups($event))) ?></span>
              <?php endif ?>
            </span>
          </li>
        <?php endforeach ?>
      </ol>
    </section>
  <?php endif ?>

  <footer class="invitation-footer">
    <?php if ($shares !== null): ?>
      <?php require __DIR__ . '/quota_shares.php' ?>
    <?php endif ?>

    <?php if (!$registrationOpen): ?>
      <div class="alert alert-secondary" role="status">Der Anmeldezeitraum ist abgelaufen.</div>
    <?php endif ?>

    <?php if ($registrationOpen): ?>
      <div class="text-center">
        <a href="<?= e(url('register/')) ?>" class="btn btn-primary btn-lg px-5">Anmelden</a>
      </div>
    <?php endif ?>
  </footer>
</article>

<p class="text-center mt-4 mb-0">
  <a href="<?= e(config('info_url')) ?>" class="link-secondary">Weitere Informationen</a>
</p>
