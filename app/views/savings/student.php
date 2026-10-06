<?php
/** @var array $initial  {profile:{rev,student,summary}, list, can_edit, can_delete, can_create, months, today} */
$p   = $initial['profile'];
$s   = $p['student'];
$sum = $p['summary'];
$active = $s['status'] === 'aktif';
$isAlumni = !empty($initial['is_alumni']);
$history = $initial['history'] ?? [];
$statusLabel = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'alumni' => 'Alumni'][$s['status']] ?? ucfirst($s['status']);
$statusClass = ['aktif' => 'badge-success', 'alumni' => 'badge-info'][$s['status']] ?? 'badge-muted';
$histLabel = ['naik' => 'Naik', 'tidak_naik' => 'Tidak naik', 'lulus' => 'Lulus'];
$histClass = ['naik' => 'badge-success', 'tidak_naik' => 'badge-muted', 'lulus' => 'badge-info'];
$canEdit = $initial['can_edit']; $canDelete = $initial['can_delete'];

$this->set('title', ($isAlumni ? 'Detail Alumni — ' : 'Detail Tabungan — ') . $s['name']);
$this->set('heading', $s['name']);
$this->set('lead', $isAlumni
    ? 'Alumni • ' . $s['jenjang'] . ' — Kelas terakhir ' . $s['kelas'] . ' • ID ' . $s['code']
    : $s['jenjang'] . ' — Kelas ' . $s['kelas'] . ' • ID ' . $s['code']);

$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection();

$this->section('actions'); ?>
<a class="btn btn-secondary" id="btn-print-rekap" href="<?= e(url('/print/rekap/' . $s['id'])) ?>"><?= icon('printer') ?>Cetak Rekap</a>
<?php
if ($initial['can_create'] && nav_ready('/tabungan/tambah') && $active): ?>
    <a class="btn btn-primary" id="btn-add" href="<?= e(url('/tabungan/tambah?student_id=' . $s['id'])) ?>"><?= icon('plus') ?>Tambah Tabungan</a>
<?php endif;
$this->endSection();

$dash = static fn ($v): string => ($v === null || $v === '') ? '–' : (string) $v;
?>

<div class="alert alert-warning mb-4" id="inactive-note" role="status" <?= $s['status'] === 'nonaktif' ? '' : 'hidden' ?>>
    <?= icon('triangle-alert') ?><div>Santri ini <b>nonaktif</b>. Riwayat dan saldo tetap tersimpan, tetapi transaksi baru tidak dapat ditambahkan.</div>
</div>
<?php if ($isAlumni): ?>
<div class="alert alert-info mb-4" role="status">
    <?= icon('info') ?><div>Santri ini sudah <b>lulus</b> dan tercatat sebagai alumni. Seluruh riwayat transaksi dan saldo tetap tersimpan; transaksi baru tidak dapat ditambahkan.</div>
</div>
<?php endif; ?>

<div class="detail-top">
    <section class="card" aria-labelledby="profile-title">
        <div class="card-head"><h2 id="profile-title">Profil Santri</h2>
            <span id="p-status" class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></div>
        <div class="card-body">
            <dl class="profile-list">
                <div><dt>Nama</dt><dd data-f="name"><?= e($s['name']) ?></dd></div>
                <div><dt>Jenjang</dt><dd data-f="jenjang"><?= e($s['jenjang']) ?></dd></div>
                <div><dt><?= $isAlumni ? 'Kelas Terakhir' : 'Kelas' ?></dt><dd data-f="kelas"><?= e($s['kelas']) ?></dd></div>
                <div><dt>ID Santri</dt><dd data-f="code"><?= e($s['code']) ?></dd></div>
                <div><dt>NIS / NISN</dt><dd data-f="nis"><?= e($dash($s['nis'])) ?></dd></div>
                <div><dt>No. Urut</dt><dd data-f="no_urut"><?= e($dash($s['no_urut'])) ?></dd></div>
                <div class="span-2"><dt>Dawis / Blok</dt><dd data-f="dawis_blok"><?= e($dash($s['dawis_blok'])) ?></dd></div>
                <?php if ($isAlumni): ?>
                <div><dt>Tahun Lulus</dt><dd><?= e($dash($s['graduation_year'])) ?></dd></div>
                <div><dt>Tanggal Lulus</dt><dd><?= $s['graduated_at'] ? e(tanggal_id($s['graduated_at'], true)) : '–' ?></dd></div>
                <div class="span-2"><dt>Tahun Ajaran Terakhir</dt><dd><?= e($dash($s['graduation_academic_year'])) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>
    </section>

    <div class="detail-side">
        <div class="stat stat--hero">
            <div class="stat-label"><?= icon('wallet') ?>Saldo Saat Ini</div>
            <div class="stat-value" data-stat="saldo"><?= rupiah_html($sum['saldo']) ?></div>
            <div class="stat-note" id="p-last"><?= $sum['last_date'] ? 'Transaksi terakhir ' . e(tanggal_id($sum['last_date'], true)) : 'Belum ada transaksi' ?></div>
        </div>
        <div class="grid grid-2">
            <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-down-left') ?>Total Masuk</div><div class="stat-value is-masuk" data-stat="masuk"><?= rupiah_html($sum['masuk']) ?></div></div>
            <div class="stat stat--compact"><div class="stat-label"><?= icon('arrow-up-right') ?>Total Keluar</div><div class="stat-value is-keluar" data-stat="keluar"><?= rupiah_html($sum['keluar']) ?></div></div>
        </div>
    </div>
</div>

<?php if ($history): ?>
<section class="card mt-4" aria-labelledby="kelas-title">
    <div class="card-head"><h2 id="kelas-title">Riwayat Kelas</h2></div>
    <div class="card-body">
        <ol class="timeline">
            <?php foreach ($history as $h): ?>
            <li>
                <div class="timeline-main">
                    <b>TA <?= e($h['from_year']) ?></b>
                    <span><?= e($h['previous']) ?></span>
                    <?= icon('arrow-right') ?>
                    <span><?= $h['new'] === null ? 'Lulus' : e($h['new']) ?></span>
                    <span class="badge <?= e($histClass[$h['status']] ?? 'badge-muted') ?>"><?= e($histLabel[$h['status']] ?? $h['status']) ?></span>
                </div>
                <div class="muted small">Menuju TA <?= e($h['to_year']) ?> • diproses <?= e(tanggal_id(substr($h['at'], 0, 10), true)) ?> oleh <?= e($h['by']) ?></div>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<section class="card card-flush mt-4" aria-labelledby="hist-title">
    <div class="card-head">
        <div><h2 id="hist-title">Riwayat Transaksi</h2><div class="sub" id="hist-sub" aria-live="polite"></div></div>
    </div>
    <form class="toolbar toolbar--flat" id="filters" role="search" autocomplete="off">
        <div class="field field-grow">
            <label class="label" for="f-q">Cari keterangan</label>
            <div class="input-icon"><?= icon('search') ?><input class="input" type="search" id="f-q" placeholder="Cari keterangan atau nomor transaksi" maxlength="100"></div>
        </div>
        <div class="field"><label class="label" for="f-mutation">Mutasi</label>
            <select class="select" id="f-mutation"><option value="">Semua</option><option value="masuk">Masuk</option><option value="keluar">Keluar</option></select></div>
        <div class="field"><label class="label" for="f-month">Bulan</label>
            <select class="select" id="f-month"><option value="">Semua</option>
                <?php foreach ($initial['months'] as $n => $b): ?><option value="<?= $n ?>"><?= e($b) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="label" for="f-year">Tahun</label>
            <select class="select" id="f-year"><option value="">Semua</option></select></div>
        <div class="field"><label class="label" for="f-from">Dari</label><input class="input" type="date" id="f-from" max="<?= e($initial['today']) ?>"></div>
        <div class="field"><label class="label" for="f-to">Sampai</label><input class="input" type="date" id="f-to" max="<?= e($initial['today']) ?>"></div>
        <div class="toolbar-actions"><button type="button" class="btn btn-ghost" id="f-reset">Atur ulang</button></div>
    </form>

    <?php if ($canDelete): ?><div id="bulk-bar"></div><?php endif; ?>
    <div class="table-wrap" id="table-wrap">
        <table class="table table-stack">
            <thead id="thead"><tr>
                <th data-sort="date" data-stack="title">Tanggal</th>
                <th data-sort="month">Bulan</th>
                <th data-sort="mutation">Mutasi</th>
                <th data-sort="amount" class="num">Nominal</th>
                <th data-stack="full">Keterangan</th>
                <th class="num">Saldo</th>
                <?php if ($canEdit || $canDelete): ?><th class="col-actions" data-stack="actions"><span class="sr-only">Aksi</span></th><?php endif; ?>
            </tr></thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>
    <div class="empty" id="empty" hidden>
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <h3 id="empty-title">Belum ada transaksi</h3>
        <p id="empty-text">Belum terdapat data transaksi tabungan untuk santri ini.</p>
    </div>
    <div class="pagination" id="pager"></div>
</section>

<?php if ($canEdit) { $this->partial('partials/savings_edit_dialog', ['months' => $initial['months'], 'today' => $initial['today']]); } ?>

<script type="application/json" id="detail-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/savings-actions.js')) ?>" defer></script>
<script src="<?= e(asset('js/bulk.js')) ?>" defer></script>
<script src="<?= e(asset('js/savings-detail.js')) ?>" defer></script>
<?php $this->endSection(); ?>
