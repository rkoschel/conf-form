<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Konferenz') ?></title>
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
  <script src="<?= e(asset('theme.js')) ?>"></script>
</head>
<body>
  <?php $narrow = empty($wide) ? ' page-narrow' : '' /* $wide: volle Breite, z. B. Team-Auswertung */ ?>
  <div class="container<?= $narrow ?> pt-3 d-flex justify-content-end">
    <?php require __DIR__ . '/theme_toggle.php' ?>
  </div>
  <main class="container pb-4<?= $narrow ?>">
    <?= $content ?>
  </main>
  <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
  <?php endforeach ?>
</body>
</html>
