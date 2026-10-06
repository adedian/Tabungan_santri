<?php /** @var int $code @var string $title @var string $message @var ?string $detail */
$this->set('title', $title); ?>
<div class="error-code"><?= e($code) ?></div>
<h1><?= e($title) ?></h1>
<p><?= e($message) ?></p>
<?php if (!empty($detail)): ?><pre class="error-detail"><?= e($detail) ?></pre><?php endif; ?>
<a class="btn btn-primary" href="<?= e(url('/')) ?>">Kembali ke beranda</a>
