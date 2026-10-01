<?php /* Anmeldung nicht möglich: keine aktive Veranstaltung oder Frist abgelaufen */ ?>
<h1 class="h3 mb-4">Anmeldung</h1>
<div class="alert alert-secondary" role="status">
  <?= $event === null ? 'Derzeit ist keine Anmeldung möglich.' : 'Der Anmeldezeitraum ist abgelaufen.' ?>
</div>
<p><a href="<?= e(url('')) ?>">Zur Startseite</a></p>
