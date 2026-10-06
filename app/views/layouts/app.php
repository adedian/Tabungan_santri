<?php
/** @var string $content */
$user    = auth_user();
$groups  = nav_groups();
$heading = $this->get('heading');
$lead    = $this->get('lead');
$school  = \App\Models\Setting::get('school_name', '');

$flash = [];
foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'success'] as $key => $type) {
    if (($msg = flash($key)) !== null && $msg !== '') {
        $flash[] = ['type' => $type, 'message' => (string) $msg];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php $this->partial('partials/head'); ?>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>

<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
    <a class="brand" href="<?= e(url('/dashboard')) ?>">
        <?php $this->partial('partials/brand'); ?>
        <span class="brand-name">Tabungan Santri<?php if ($school !== ''): ?><span class="brand-sub"><?= e($school) ?></span><?php endif; ?></span>
    </a>

    <nav class="sidebar-scroll">
        <?php foreach ($groups as $group): ?>
            <?php if ($group['label'] !== null): ?><div class="nav-label"><?= e($group['label']) ?></div><?php endif; ?>
            <?php foreach ($group['items'] as $item): ?>
                <?php if ($item['ready']): ?>
                    <a class="nav-link" href="<?= e(url($item['path'])) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>>
                        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
                    </a>
                <?php else: ?>
                    <span class="nav-link" aria-disabled="true" title="Belum tersedia">
                        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span><span class="nav-soon">Segera</span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="sidebar-overlay" data-sidebar-toggle></div>

<div class="main">
    <header class="topbar">
        <a class="topbar-brand" href="<?= e(url('/dashboard')) ?>" aria-label="Tabungan Santri — ke Dashboard">
            <?php $this->partial('partials/brand'); ?><span class="topbar-brand-name">Tabungan Santri</span>
        </a>
        <button type="button" class="btn btn-ghost btn-icon menu-btn" data-sidebar-toggle aria-expanded="false" aria-controls="sidebar" aria-label="Buka menu">
            <?= icon('menu', 'icon-lg') ?>
        </button>
        <span class="topbar-date"><?= e(hari_id() . ', ' . tanggal_id(date('Y-m-d'))) ?></span>
        <span class="topbar-spacer"></span>
        <?= $this->yieldSection('topbar') ?>

        <div class="dropdown" data-dropdown>
            <button type="button" class="user-btn" data-dropdown-toggle aria-haspopup="menu" aria-expanded="false">
                <span class="avatar" aria-hidden="true"><?= e(initials((string) $user['name'])) ?></span>
                <span class="user-meta">
                    <span class="user-name truncate"><?= e($user['name']) ?></span>
                    <span class="user-role"><?= e(\App\Core\Auth::roleLabel()) ?></span>
                </span>
                <?= icon('chevron-down') ?>
            </button>
            <div class="dropdown-menu" data-dropdown-menu role="menu" hidden>
                <div class="dropdown-head"><b><?= e($user['name']) ?></b><span><?= e($user['email'] ?? $user['username']) ?></span></div>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item is-danger" role="menuitem"><?= icon('log-out') ?>Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <main class="content page" id="main" tabindex="-1">
        <?php if ($back = $this->get('back', back_target())): ?>
            <a class="back-link" href="<?= e(url($back['url'])) ?>" data-back title="Kembali ke <?= e($back['label']) ?>"><?= icon('arrow-left') ?>Kembali</a>
        <?php endif; ?>
        <?php if ($heading): ?>
            <div class="page-head">
                <div>
                    <h1 class="page-title"><?= e($heading) ?></h1>
                    <?php if ($lead): ?><p class="page-lead"><?= e($lead) ?></p><?php endif; ?>
                </div>
                <?php if (($actions = $this->yieldSection('actions')) !== ''): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>

<div class="toast-region" id="toast-region" aria-live="polite"></div>

<dialog class="modal" id="confirm-dialog" aria-labelledby="confirm-title">
    <form method="dialog">
        <div class="modal-head">
            <span id="confirm-icon" class="modal-icon"></span>
            <h2 id="confirm-title">Konfirmasi</h2>
        </div>
        <div class="modal-body">
            <p id="confirm-message"></p>
            <dl class="summary" id="confirm-details" hidden></dl>
        </div>
        <div class="modal-foot">
            <button type="submit" value="cancel" class="btn btn-secondary" id="confirm-cancel">Batal</button>
            <button type="submit" value="ok" class="btn btn-primary" id="confirm-ok">Ya, lanjutkan</button>
        </div>
    </form>
</dialog>

<script type="application/json" id="flash-data"><?= json_for_script($flash) ?></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?= $this->yieldSection('scripts') ?>
</body>
</html>
