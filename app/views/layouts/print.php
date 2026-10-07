<?php
/** @var string $content  Layout cetak: tanpa sidebar/topbar. Bilah alat hanya tampil di layar (disembunyikan saat dicetak). */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="<?= e(config('app.author')) ?>">
    <title><?= e($this->get('title', 'Cetak') . ' — ' . config('app.name')) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(url('')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body class="print-body" data-log-ref="<?= e($logRef ?? '') ?>"<?= $this->get('autoprint') ? ' data-autoprint="1"' : '' ?>>
<?= $content ?>
<script src="<?= e(asset('js/print.js')) ?>" defer></script>
</body>
</html>
