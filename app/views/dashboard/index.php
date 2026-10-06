<?php
/** @var array $initial  {summary, activity} */
$user  = auth_user();
$first = strtok((string) $user['name'], ' ') ?: (string) $user['name'];
$sum   = $initial['summary'];
$t     = $sum['totals'];
$tk    = $sum['jenjang'][0] ?? ['santri' => 0];
$sd    = $sum['jenjang'][1] ?? ['santri' => 0];

$this->set('title', 'Dashboard');
$this->set('heading', salam() . ', ' . $first);
$this->set('lead', 'Ringkasan tabungan seluruh santri. Data diperbarui otomatis.');

$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection();

$this->section('actions');
if (can('savings.create') && nav_ready('/tabungan/tambah')): ?>
    <a class="btn btn-primary" href="<?= e(url('/tabungan/tambah')) ?>"><?= icon('plus') ?>Tambah Tabungan</a>
<?php endif;
$this->endSection(); ?>

<div class="dash-stats">
    <div class="stat stat--hero" data-stat-card="saldo">
        <div class="stat-label"><?= icon('wallet') ?>Total Saldo</div>
        <div class="stat-value" data-stat="saldo"><?= rupiah_html($t['saldo']) ?></div>
        <div class="stat-note" data-note="saldo"><?= e(number_format($sum['santri'], 0, ',', '.')) ?> santri aktif</div>
    </div>
    <div class="stat" data-stat-card="masuk">
        <div class="stat-label"><?= icon('arrow-down-left') ?>Total Masuk</div>
        <div class="stat-value is-masuk" data-stat="masuk"><?= rupiah_html($t['masuk']) ?></div>
        <div class="stat-note">Semua periode</div>
    </div>
    <div class="stat" data-stat-card="keluar">
        <div class="stat-label"><?= icon('arrow-up-right') ?>Total Keluar</div>
        <div class="stat-value is-keluar" data-stat="keluar"><?= rupiah_html($t['keluar']) ?></div>
        <div class="stat-note"><span data-note="transaksi"><?= e(number_format($t['transaksi'], 0, ',', '.')) ?></span> transaksi tercatat</div>
    </div>
    <div class="stat" data-stat-card="santri">
        <div class="stat-label"><?= icon('users') ?>Jumlah Santri</div>
        <div class="stat-value" data-stat="santri"><?= e(number_format($sum['santri'], 0, ',', '.')) ?></div>
        <div class="stat-note" data-note="jenjang">TK <?= e($tk['santri']) ?> • SD <?= e($sd['santri']) ?></div>
    </div>
</div>

<div class="dash-row">
    <section class="card" aria-labelledby="activity-title">
        <div class="card-head">
            <div>
                <h2 id="activity-title">Aktivitas Tabungan</h2>
                <div class="sub" id="activity-sub" aria-live="polite"></div>
            </div>
            <div class="cluster">
                <div class="segmented" role="group" aria-label="Rentang waktu" id="range-switch">
                    <button type="button" data-range="day" aria-pressed="false">Hari</button>
                    <button type="button" data-range="week" aria-pressed="true">Minggu</button>
                    <button type="button" data-range="month" aria-pressed="false">Bulan</button>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="view-toggle" aria-pressed="false">
                    <?= icon('table') ?><span>Tabel</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="chart-legend"></div>
            <div class="chart-frame mt-4" id="chart-frame">
                <div id="chart"></div>
                <div id="chart-table" hidden></div>
            </div>
        </div>
    </section>

    <section class="card" aria-labelledby="jenjang-title">
        <div class="card-head"><div><h2 id="jenjang-title">Saldo per Jenjang</h2><div class="sub">Porsi dari total saldo</div></div></div>
        <div class="card-body" id="jenjang-list"></div>
    </section>
</div>

<section class="card card-flush dash-block" aria-labelledby="recent-title">
    <div class="card-head">
        <div><h2 id="recent-title">Transaksi Terbaru</h2><div class="sub">10 transaksi terakhir yang dicatat</div></div>
        <?php if (nav_ready('/tabungan')): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/tabungan')) ?>">Lihat semua<?= icon('chevron-right') ?></a>
        <?php endif; ?>
    </div>
    <div class="table-wrap" id="recent-wrap">
        <table class="table">
            <thead><tr>
                <th>Tanggal</th><th>Santri</th><th>Kelas</th><th>Mutasi</th><th class="num">Nominal</th><th>Keterangan</th>
            </tr></thead>
            <tbody id="recent-body"></tbody>
        </table>
    </div>
    <div class="empty" id="recent-empty" hidden>
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <h3>Belum ada transaksi</h3>
        <p>Belum terdapat data transaksi tabungan.</p>
    </div>
</section>

<script type="application/json" id="dash-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/chart.js')) ?>" defer></script>
<script src="<?= e(asset('js/dashboard.js')) ?>" defer></script>
<?php $this->endSection(); ?>
