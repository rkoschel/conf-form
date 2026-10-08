<?php
/* Anmeldeformular (SPEC §5.1) */
?>
<article class="invitation">
  <?php $eyebrow = 'Anmeldung' ?>
  <?php require __DIR__ . '/invitation_header.php' ?>

  <div>
    <?php if ($notice !== null): ?>
      <div class="alert alert-warning" role="alert"><?= e($notice) ?></div>
    <?php elseif ($errors): ?>
      <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
    <?php endif ?>

    <form method="post" action="<?= e(url('register/')) ?>" class="vstack gap-4" id="register-form" novalidate>
      <?= csrf_field() ?>
      <?= spam_fields() ?>

      <?php require __DIR__ . '/registration_fields.php' ?>

      <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg px-5">Teilnahme anfragen</button>
      </div>
    </form>
  </div>
</article>

<p class="form-text text-center mt-3 mb-0">
  Mit dem Absenden werden deine Angaben zur Organisation der Veranstaltung gespeichert.
  Details in der <a href="<?= e(config('privacy_url')) ?>">Datenschutzerklärung</a>.
</p>
