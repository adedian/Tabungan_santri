<?php
/** @var array $initial  hasil SavingsService::listing() + can_edit, can_delete, months, today */
$this->set('title', 'Riwayat Tabungan');
$this->set('heading', 'Riwayat Tabungan');
$this->set('lead', 'Seluruh transaksi tabungan. Saldo pada tiap baris adalah saldo santri setelah transaksi tersebut.');

$canEdit = $initial['can_edit']; $canDelete = $initial['can_delete'];
$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection();

$this->section('actions');
if (can('savings.create')): ?>
    <a class="btn btn-primary" href="<?= e(url('/tabungan/tambah')) ?>"><?= icon('plus') ?>Tambah Tabungan</a>
<?php endif;
$this->endSection(); ?>

<form class="toolbar" id="filters" role="search" autocomplete="off">
    <div class="field field-grow">
        <label class="label" for="f-q">Cari</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="f-q" placeholder="Nama, keterangan, atau nomor transaksi" maxlength="100">
        </div>
    </div>
    <div class="field field-grow">
        <label class="label" for="f-student">Santri</label>
        <input class="input" type="text" id="f-student" placeholder="Semua santri">
    </div>
    <button type="button" class="btn btn-secondary toolbar-toggle" id="f-toggle" aria-expanded="false" aria-controls="f-more">
        <?= icon('filter') ?><span>Filter lainnya</span><span class="badge badge-gold" id="f-count" hidden></span>
    </button>
    <div class="toolbar-more" id="f-more">
    <div class="field">
        <label class="label" for="f-jenjang">Jenjang</label>
        <select class="select" id="f-jenjang"><option value="">Semua</option><option value="TK">TK</option><option value="SD">SD</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-kelas">Kelas</label>
        <select class="select" id="f-kelas"><option value="">Semua</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-month">Bulan</label>
        <select class="select" id="f-month">
            <option value="">Semua</option>
            <?php foreach ($initial['months'] as $n => $b): ?><option value="<?= $n ?>"><?= e($b) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="f-year">Tahun</label>
        <select class="select" id="f-year"><option value="">Semua</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-mutation">Mutasi</label>
        <select class="select" id="f-mutation"><option value="">Semua</option><option value="masuk">Masuk</option><option value="keluar">Keluar</option></select>
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
    <div class="stat stat--compact"><div class="stat-label"><?= icon('history') ?>Transaksi</div><div class="stat-value" id="s-count">0</div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-down-left') ?>Total Masuk</div><div class="stat-value is-masuk" id="s-masuk"></div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-up-right') ?>Total Keluar</div><div class="stat-value is-keluar" id="s-keluar"></div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('wallet') ?>Selisih (masuk − keluar)</div><div class="stat-value" id="s-net"></div></div>
</div>

<section class="card card-flush mt-4" aria-label="Daftar transaksi">
    <div class="table-wrap" id="table-wrap">
        <table class="table" style="min-width:1080px">
            <thead id="thead"><tr>
                <th data-sort="date">Tanggal</th>
                <th data-sort="name">Nama</th>
                <th data-sort="jenjang">Jenjang</th>
                <th data-sort="kelas">Kelas</th>
                <th data-sort="month">Bulan</th>
                <th data-sort="mutation">Mutasi</th>
                <th data-sort="amount" class="num">Nominal</th>
                <th>Keterangan</th>
                <th class="num">Saldo</th>
                <?php if ($canEdit || $canDelete): ?><th class="col-actions"><span class="sr-only">Aksi</span></th><?php endif; ?>
            </tr></thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>
    <div class="empty" id="empty" hidden>
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <h3 id="empty-title">Belum ada transaksi</h3>
        <p id="empty-text">Belum terdapat data transaksi tabungan pada periode ini.</p>
    </div>
    <div class="pagination" id="pager"></div>
</section>

<?php if ($canEdit) { $this->partial('partials/savings_edit_dialog', ['months' => $initial['months'], 'today' => $initial['today']]); } ?>

<script type="application/json" id="history-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/combobox.js')) ?>" defer></script>
<script src="<?= e(asset('js/savings-actions.js')) ?>" defer></script>
<script src="<?= e(asset('js/savings-history.js')) ?>" defer></script>
<?php $this->endSection(); ?>
