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
<body class="public-page">
  <?php $narrow = empty($wide) ? ' page-narrow' : '' /* $wide: volle Breite, z. B. Team-Auswertung */ ?>
  <main class="container pt-4 pb-4<?= $narrow ?>">
    <?= $content ?>
  </main>
  <footer class="container<?= $narrow ?> pb-4 d-flex justify-content-center">
    <?php require __DIR__ . '/theme_switch.php' ?>
  </footer>
  <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
  <?php endforeach ?>
</body>
</html>
