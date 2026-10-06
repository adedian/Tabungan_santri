<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php $this->partial('partials/head'); ?>
</head>
<body>
<div class="auth">
    <aside class="auth-aside">
        <div class="brand">
            <?php $this->partial('partials/brand'); ?>
            <span class="brand-name">Tabungan Santri<?php if (($school = \App\Models\Setting::get('school_name', '')) !== ''): ?><span class="brand-sub"><?= e($school) ?></span><?php endif; ?></span>
        </div>
        <div class="auth-copy">
            <p class="auth-headline">Tabungan santri, tercatat rapi dan tepercaya.</p>
            <p class="auth-sub">Catat setoran dan penarikan, pantau saldo setiap santri, dan cetak rekap kapan pun dibutuhkan.</p>
        </div>
        <p class="auth-foot">Sistem administrasi tabungan sekolah</p>
    </aside>
    <main class="auth-main" id="main">
        <div class="auth-card page"><?= $content ?></div>
    </main>
</div>
<div class="toast-region" id="toast-region" aria-live="polite"></div>
<script type="application/json" id="flash-data">[]</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
