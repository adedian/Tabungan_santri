<?php
/** @var array $initial  hasil AuditService::listing() + today */
$this->set('title', 'Audit Log');
$this->set('heading', 'Audit Log');
$this->set('lead', 'Jejak aktivitas pengguna: login, transaksi, perubahan data santri, ekspor, dan cetak. Catatan hanya dapat dibaca.');

$this->section('actions'); ?>
    <button type="button" class="btn btn-secondary" id="a-refresh"><?= icon('refresh') ?>Muat ulang</button>
<?php $this->endSection(); ?>

<form class="toolbar" id="filters" role="search" autocomplete="off">
    <div class="field field-grow">
        <label class="label" for="f-q">Cari</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="f-q" placeholder="Pengguna, aksi, keterangan, atau IP" maxlength="100">
        </div>
    </div>
    <button type="button" class="btn btn-secondary toolbar-toggle" id="f-toggle" aria-expanded="false" aria-controls="f-more">
        <?= icon('filter') ?><span>Filter lainnya</span><span class="badge badge-gold" id="f-count" hidden></span>
    </button>
    <div class="toolbar-more" id="f-more">
    <div class="field">
        <label class="label" for="f-module">Modul</label>
        <select class="select" id="f-module"><option value="">Semua</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-user">Pengguna</label>
        <select class="select" id="f-user"><option value="">Semua</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-from">Dari</label>
        <input class="input" type="date" id="f-from" max="<?= e($initial['today']) ?>">
    </div>
    <div class="field">
        <label class="label" for="f-to">Sampai</label>
        <input class="input" type="date" id="f-to" max="<?= e($initial['today']) ?>">
    </div>
    <div class="toolbar-actions">
        <div class="chips" role="group" aria-label="Rentang tanggal cepat">
            <button type="button" class="chip" data-range="today">Hari ini</button>
            <button type="button" class="chip" data-range="week">7 hari</button>
            <button type="button" class="chip" data-range="month">Bulan ini</button>
        </div>
        <button type="button" class="btn btn-ghost" id="f-reset">Atur ulang</button>
    </div>
    </div>
</form>

<div class="grid grid-4 hist-stats" aria-label="Ringkasan sesuai filter">
    <div class="stat stat--compact"><div class="stat-label"><?= icon('scroll-text') ?>Catatan</div><div class="stat-value" id="s-count">0</div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('users') ?>Pengguna</div><div class="stat-value" id="s-users">0</div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('table') ?>Modul</div><div class="stat-value" id="s-modules">0</div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('history') ?>Aktivitas Terakhir</div><div class="stat-value" id="s-latest">—</div></div>
</div>

<section class="card card-flush mt-4" aria-label="Daftar aktivitas">
    <div class="table-wrap" id="table-wrap">
        <table class="table table-stack">
            <thead id="thead"><tr>
                <th data-sort="time">Waktu</th>
                <th data-sort="user">Pengguna</th>
                <th data-sort="module">Modul</th>
                <th data-sort="action">Aksi</th>
                <th>Referensi</th>
                <th data-stack="full">Keterangan</th>
                <th>IP</th>
            </tr></thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>
    <div class="empty" id="empty" hidden>
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <h3 id="empty-title">Belum ada catatan</h3>
        <p id="empty-text">Aktivitas pengguna akan tercatat di sini.</p>
    </div>
    <div class="pagination" id="pager"></div>
</section>

<script type="application/json" id="audit-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/audit.js')) ?>" defer></script>
<?php $this->endSection(); ?>
