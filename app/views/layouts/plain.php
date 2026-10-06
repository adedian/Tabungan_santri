<?php /** @var string $content — layout minimal untuk halaman error (tanpa sesi/DB) */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php $this->partial('partials/head'); ?>
</head>
<body>
<main class="error-page"><div class="error-card"><?= $content ?></div></main>
</body>
</html>
