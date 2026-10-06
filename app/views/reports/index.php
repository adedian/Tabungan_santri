<?php
/** @var array $initial hasil ReportService::build() + students, can_export, months, today, datasets */
$this->set('title', 'Laporan Tabungan');
$this->set('heading', 'Laporan Tabungan');
$this->set('lead', 'Ringkasan, rekap per kelas dan per santri, serta ekspor data untuk periode yang dipilih.');
$canExport = $initial['can_export'];

$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection();

$this->section('actions'); ?>
<a class="btn btn-secondary" id="link-detail" href="<?= e(url('/tabungan')) ?>"><?= icon('history') ?>Lihat rincian transaksi</a>
<a class="btn btn-secondary" id="link-print" href="<?= e(url('/print/laporan')) ?>"><?= icon('printer') ?>Cetak</a>
<?php if ($canExport): ?>
<div class="dropdown" data-dropdown>
    <button type="button" class="btn btn-primary" data-dropdown-toggle aria-haspopup="menu" aria-expanded="false"><?= icon('download') ?>Export<?= icon('chevron-down') ?></button>
    <div class="dropdown-menu" data-dropdown-menu role="menu" hidden>
        <div class="dropdown-head"><b>Ekspor sesuai filter</b><span>Berkas mengikuti filter di halaman ini</span></div>
        <a class="dropdown-item" role="menuitem" data-export="transaksi:xlsx" href="#"><?= icon('file-chart') ?>Rincian transaksi — Excel (.xlsx)</a>
        <a class="dropdown-item" role="menuitem" data-export="transaksi:csv" href="#"><?= icon('download') ?>Rincian transaksi — CSV</a>
        <a class="dropdown-item" role="menuitem" data-export="santri:xlsx" href="#"><?= icon('file-chart') ?>Rekap per santri — Excel (.xlsx)</a>
        <a class="dropdown-item" role="menuitem" data-export="santri:csv" href="#"><?= icon('download') ?>Rekap per santri — CSV</a>
    </div>
</div>
<?php endif;
$this->endSection(); ?>

<form class="toolbar" id="filters" autocomplete="off">
    <div class="field half">
        <label class="label" for="f-from">Dari</label>
        <input class="input" type="date" id="f-from" max="<?= e($initial['today']) ?>">
    </div>
    <div class="field half">
        <label class="label" for="f-to">Sampai</label>
        <input class="input" type="date" id="f-to" max="<?= e($initial['today']) ?>">
    </div>
    <div class="field field-chips">
        <span class="label" id="chips-label">Periode cepat</span>
        <div class="chips" role="group" aria-labelledby="chips-label">
            <button type="button" class="chip" data-range="month">Bulan ini</button>
            <button type="button" class="chip" data-range="lastmonth">Bulan lalu</button>
            <button type="button" class="chip" data-range="year">Tahun ini</button>
            <button type="button" class="chip" data-range="all">Semua</button>
        </div>
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
            <select class="select" id="f-month"><option value="">Semua</option>
                <?php foreach ($initial['months'] as $n => $b): ?><option value="<?= $n ?>"><?= e($b) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
            <label class="label" for="f-year">Tahun</label>
            <select class="select" id="f-year"><option value="">Semua</option></select>
        </div>
        <div class="field">
            <label class="label" for="f-mutation">Mutasi</label>
            <select class="select" id="f-mutation"><option value="">Semua</option><option value="masuk">Masuk</option><option value="keluar">Keluar</option></select>
        </div>
        <div class="field field-grow">
            <label class="label" for="f-student">Santri</label>
            <input class="input" type="text" id="f-student" placeholder="Semua santri">
        </div>
        <div class="toolbar-actions"><button type="button" class="btn btn-ghost" id="f-reset">Atur ulang</button></div>
    </div>
</form>

<p class="report-period" id="period" aria-live="polite"></p>

<div class="grid grid-4 hist-stats" aria-label="Ringkasan laporan">
    <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-down-left') ?>Total Masuk</div><div class="stat-value is-masuk" id="s-masuk"></div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-up-right') ?>Total Keluar</div><div class="stat-value is-keluar" id="s-keluar"></div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('wallet') ?>Saldo (masuk − keluar)</div><div class="stat-value" id="s-net"></div></div>
    <div class="stat stat--compact"><div class="stat-label"><?= icon('history') ?>Jumlah Transaksi</div><div class="stat-value" id="s-count"></div><div class="stat-note" id="s-students"></div></div>
</div>

<section class="card mt-4" aria-labelledby="act-title">
    <div class="card-head">
        <div><h2 id="act-title">Aktivitas Tabungan</h2><div class="sub" id="act-sub" aria-live="polite"></div></div>
        <button type="button" class="btn btn-ghost btn-sm" id="view-toggle" aria-pressed="false"><?= icon('table') ?><span>Tabel</span></button>
    </div>
    <div class="card-body">
        <div id="chart-legend"></div>
        <div class="chart-frame mt-4" id="chart-frame"><div id="chart"></div><div id="chart-table" hidden></div></div>
    </div>
</section>

<section class="card card-flush mt-4" aria-labelledby="rekap-title">
    <div class="card-head">
        <div><h2 id="rekap-title">Rekap</h2><div class="sub" id="rekap-sub"></div></div>
        <div class="segmented" role="group" aria-label="Jenis rekap" id="tab-switch">
            <button type="button" data-tab="kelas" aria-pressed="true">Per Kelas</button>
            <button type="button" data-tab="santri" aria-pressed="false">Per Santri</button>
        </div>
    </div>

    <div id="pane-kelas">
        <div class="table-wrap" id="class-wrap">
            <table class="table table-stack">
                <thead><tr><th data-stack="title">Jenjang</th><th>Kelas</th><th class="num">Santri</th><th class="num">Transaksi</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Selisih</th></tr></thead>
                <tbody id="class-body"></tbody>
                <tfoot id="class-foot"></tfoot>
            </table>
        </div>
        <div class="empty" id="class-empty" hidden>
            <div class="empty-icon"><?= icon('inbox') ?></div><h3>Tidak ada data</h3><p>Tidak ada transaksi pada periode dan filter ini.</p>
        </div>
    </div>

    <div id="pane-santri" hidden>
        <div class="table-wrap" id="student-wrap">
            <table class="table table-stack">
                <thead id="thead"><tr>
                    <th data-sort="name" data-stack="title">Nama</th><th data-sort="kelas">Kelas</th>
                    <th data-sort="count" class="num">Transaksi</th><th data-sort="masuk" class="num">Masuk</th><th data-sort="keluar" class="num">Keluar</th>
                    <th data-sort="net" class="num">Selisih</th><th data-sort="saldo" class="num" id="th-saldo">Saldo</th>
                </tr></thead>
                <tbody id="student-body"></tbody>
                <tfoot id="student-foot"></tfoot>
            </table>
        </div>
        <div class="empty" id="student-empty" hidden>
            <div class="empty-icon"><?= icon('inbox') ?></div><h3>Tidak ada data</h3><p>Tidak ada transaksi pada periode dan filter ini.</p>
        </div>
        <div class="pagination" id="pager"></div>
    </div>
</section>

<script type="application/json" id="report-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/combobox.js')) ?>" defer></script>
<script src="<?= e(asset('js/chart.js')) ?>" defer></script>
<script src="<?= e(asset('js/reports.js')) ?>" defer></script>
<?php $this->endSection(); ?>
