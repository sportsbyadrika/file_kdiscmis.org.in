<?php
/**
 * Authenticated application layout.
 *
 * Expects: $content (string), optional $pageTitle, $active.
 */
$pageTitle = $pageTitle ?? config('app.name', 'File Repository');
$active    = $active ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(\App\Csrf::token()) ?>">
  <title><?= e($pageTitle) ?> &middot; <?= e(config('app.name', 'File Repository')) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(base_url('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
  <?php require ROOT_PATH . '/views/partials/navbar.php'; ?>

  <main class="app-main">
    <?= $content ?>
  </main>

  <?php require ROOT_PATH . '/views/partials/toasts.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
          integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="<?= e(base_url('/assets/js/app.js')) ?>"></script>
</body>
</html>
