<?php
$nav = [
    'admin/' => 'Anfragen',
    'admin/events.php' => 'Veranstaltungen',
    'admin/stats.php' => 'Auswertung',
    'admin/settings.php' => 'Einstellungen',
];
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Admin') ?> – Konferenz-Admin</title>
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
  <script src="<?= e(asset('theme.js')) ?>"></script>
</head>
<body>
  <nav class="navbar navbar-expand-md bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand" href="<?= e(url('admin/')) ?>">Konferenz-Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#admin-nav"
              aria-controls="admin-nav" aria-expanded="false" aria-label="Navigation umschalten">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="admin-nav">
        <ul class="navbar-nav">
          <?php foreach ($nav as $path => $label): ?>
            <li class="nav-item">
              <a class="nav-link<?= ($active ?? '') === $path ? ' active' : '' ?>"
                 <?= ($active ?? '') === $path ? 'aria-current="page"' : '' ?>
                 href="<?= e(url($path)) ?>"><?= e($label) ?></a>
            </li>
          <?php endforeach ?>
        </ul>
        <div class="ms-md-auto py-2 py-md-0">
          <?php require __DIR__ . '/theme_toggle.php' ?>
        </div>
      </div>
    </div>
  </nav>
  <main class="container py-4">
    <?php foreach ($flashes ?? [] as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
      </div>
    <?php endforeach ?>
    <?= $content ?>
  </main>
  <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(asset('admin.js')) ?>"></script>
</body>
</html>
