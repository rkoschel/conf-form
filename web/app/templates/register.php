<?php
/* Anmeldeformular (SPEC §5.1) */
?>
<h1 class="h3 mb-1">Anmeldung</h1>
<p class="lead mb-4"><?= e($event['title']) ?>, <?= e(format_date_long($event['date'])) ?></p>

<?php if ($notice !== null): ?>
  <div class="alert alert-warning" role="alert"><?= e($notice) ?></div>
<?php elseif ($errors): ?>
  <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
<?php endif ?>

<form method="post" action="<?= e(url('register/')) ?>" class="vstack gap-4" id="register-form" novalidate>
  <?= csrf_field() ?>
  <?= spam_fields() ?>

  <?php require __DIR__ . '/registration_fields.php' ?>

  <div>
    <button type="submit" class="btn btn-primary btn-lg">Teilnahme anfragen</button>
    <p class="form-text mt-2">
      Mit dem Absenden werden deine Angaben zur Organisation der Veranstaltung gespeichert.
      Details in der <a href="<?= e(config('privacy_url')) ?>">Datenschutzerklärung</a>.
    </p>
  </div>
</form>
