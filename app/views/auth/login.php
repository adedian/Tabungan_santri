<?php /** @var bool $expired */
$this->set('title', 'Masuk');
$error = flash('error');
?>
<h1>Masuk</h1>
<p class="muted">Gunakan akun yang diberikan oleh administrator.</p>

<div class="stack mt-6">
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?= icon('circle-alert') ?><div><?= e($error) ?></div></div>
<?php elseif (!empty($expired)): ?>
    <div class="alert alert-warning" role="status"><?= icon('triangle-alert') ?><div>Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.</div></div>
<?php elseif ($ok = flash('success')): ?>
    <div class="alert alert-success" role="status"><?= icon('circle-check') ?><div><?= e($ok) ?></div></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" data-loading-text="Memeriksa..." novalidate>
    <?= csrf_field() ?>
    <div class="field<?= $error ? ' has-error' : '' ?>">
        <label class="label" for="identifier">Username atau email</label>
        <input class="input" type="text" id="identifier" name="identifier" value="<?= e(old('identifier')) ?>"
               maxlength="150" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
    </div>
    <div class="field<?= $error ? ' has-error' : '' ?>">
        <label class="label" for="password">Kata sandi</label>
        <input class="input" type="password" id="password" name="password" maxlength="255" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-lg btn-block mt-6">Masuk</button>
</form>
</div>
