<?php
/** @var array $initial  hasil AlumniService::listing() + page_mode */
$rekapPage = ($initial['page_mode'] ?? '') === 'rekap';
$this->set('title', $rekapPage ? 'Rekap Alumni' : 'Tabungan Alumni');
$this->set('heading', $rekapPage ? 'Rekap Alumni' : 'Tabungan Alumni');
$this->set('lead', 'Tabungan santri yang sudah lulus. Saldo dan seluruh riwayat transaksi tetap tersimpan; alumni tidak menerima transaksi baru.');

$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection(); ?>

<section class="card" aria-labelledby="pull-title">
    <div class="card-head"><h2 id="pull-title">Tarik Data</h2></div>
    <div class="card-body">
        <form id="pull-form" class="stack" autocomplete="off">
            <fieldset class="radio-row">
                <legend class="label">Jenis data</legend>
                <label class="radio-card"><input type="radio" name="mode" value="detail"><span><b>Detail</b><small>Data setiap alumni</small></span></label>
                <label class="radio-card"><input type="radio" name="mode" value="rekap"><span><b>Rekap</b><small>Ringkasan / agregat</small></span></label>
            </fieldset>
            <div class="form-grid pull-grid">
                <div class="field">
                    <label class="label" for="a-year">Tahun Lulus</label>
                    <select class="select" id="a-year"><option value="">Semua tahun</option></select>
                </div>
                <div class="field">
                    <label class="label" for="a-ay">Tahun Ajaran</label>
                    <select class="select" id="a-ay"><option value="">Semua tahun ajaran</option></select>
                </div>
                <div class="field span-2" id="a-q-field">
                    <label class="label" for="a-q">Cari Alumni</label>
                    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" id="a-q" placeholder="Nama, ID, NIS, atau kelas" maxlength="100"></div>
                </div>
            </div>
            <div class="form-actions form-actions--inline">
                <button type="submit" class="btn btn-primary" id="a-submit"><?= icon('eye') ?>Tampilkan Data</button>
            </div>
        </form>
    </div>
</section>

<section class="mt-4" aria-labelledby="sum-title">
    <h2 class="sr-only" id="sum-title">Ringkasan</h2>
    <div class="grid grid-4 hist-stats">
        <div class="stat stat--compact"><div class="stat-label"><?= icon('graduation-cap') ?>Jumlah Alumni</div><div class="stat-value" id="s-count">0</div></div>
        <div class="stat stat--compact"><div class="stat-label"><?= icon('wallet') ?>Total Saldo</div><div class="stat-value" id="s-saldo"></div></div>
        <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-down-left') ?>Total Tabungan Masuk</div><div class="stat-value is-masuk" id="s-masuk"></div></div>
        <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-up-right') ?>Total Pengeluaran</div><div class="stat-value is-keluar" id="s-keluar"></div></div>
    </div>
</section>

<section class="card card-flush mt-4" id="pane-detail" aria-label="Detail alumni" hidden>
    <div class="card-head"><div><h2>Detail Alumni</h2><div class="sub" id="detail-sub"></div></div></div>
    <div class="table-wrap" id="detail-wrap">
        <table class="table table-stack" id="detail-table">
            <thead id="thead"><tr>
                <th>No</th>
                <th data-sort="name" data-stack="title">Nama</th>
                <th data-sort="kelas">Kelas Terakhir</th>
                <th data-sort="year">Tahun Lulus</th>
                <th data-sort="masuk" class="num">Total Masuk</th>
                <th data-sort="keluar" class="num">Total Keluar</th>
                <th data-sort="saldo" class="num">Saldo</th>
                <th class="col-actions" data-stack="actions"><span class="sr-only">Aksi</span></th>
            </tr></thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>
    <div class="empty" id="empty" hidden>
        <div class="empty-icon"><?= icon('graduation-cap') ?></div>
        <h3 id="empty-title">Belum ada alumni</h3>
        <p id="empty-text">Santri yang lulus lewat menu Kenaikan Kelas (SD kelas 6 + Naik) akan muncul di sini.</p>
    </div>
    <div class="pagination" id="pager"></div>
</section>

<section class="card card-flush mt-4" id="pane-rekap" aria-label="Rekap alumni" hidden>
    <div class="card-head"><div><h2 id="rekap-title">Rekap Tabungan Alumni</h2><div class="sub" id="rekap-sub"></div></div></div>
    <div class="table-wrap">
        <table class="table table-stack" id="recap-table">
            <thead><tr>
                <th data-stack="title" id="recap-th">Tahun Lulus</th><th class="num">Jumlah Alumni</th><th class="num">Total Masuk</th><th class="num">Total Keluar</th><th class="num">Saldo</th>
            </tr></thead>
            <tbody id="recap-body"></tbody>
            <tfoot id="recap-foot"></tfoot>
        </table>
    </div>
    <div class="empty" id="recap-empty" hidden>
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <h3>Tidak ada data</h3>
        <p>Tidak ada alumni pada filter ini.</p>
    </div>
</section>

<script type="application/json" id="alumni-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/alumni.js')) ?>" defer></script>
<?php $this->endSection(); ?>
