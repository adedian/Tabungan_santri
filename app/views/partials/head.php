<?php /** Dipakai semua layout: meta, CSS, konfigurasi untuk JS. */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#14532D">
<title><?= e($this->get('title') ? $this->get('title') . ' — ' . config('app.name') : config('app.name')) ?></title>
<?php if (session_status() === PHP_SESSION_ACTIVE): ?><meta name="csrf-token" content="<?= e(csrf_token()) ?>"><?php endif; ?>
<meta name="base-url" content="<?= e(url('')) ?>">
<meta name="icon-sprite" content="<?= e(asset('icons/sprite.svg')) ?>">
<meta name="sync-interval" content="<?= e(config('app.sync_interval', 5000)) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/fraunces-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
