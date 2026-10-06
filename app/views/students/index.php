<?php
/** @var array $initial  hasil StudentService::listing() + can_manage */
$this->set('title', 'Data Santri');
$this->set('heading', 'Data Santri');
$this->set('lead', 'Sumber data santri untuk form tabungan. Santri nonaktif tidak muncul pada transaksi baru, riwayatnya tetap tersimpan.');

$manage = can('students.manage');
$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection();

$this->section('actions'); ?>
<a class="btn btn-secondary" id="btn-print-class" href="#" hidden title="Cetak Rekap Tabungan seluruh santri pada filter jenjang/kelas ini"><?= icon('printer') ?>Cetak Rekap Kelas</a>
<?php
if ($manage): ?>
    <button type="button" class="btn btn-primary" id="btn-add"><?= icon('user-plus') ?>Tambah Santri</button>
<?php endif;
$this->endSection(); ?>

<form class="toolbar" id="filters" role="search" autocomplete="off">
    <div class="field field-grow">
        <label class="label" for="f-q">Cari santri</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="f-q" placeholder="Nama, kelas, atau ID" maxlength="100" value="<?= e($initial['filters']['q']) ?>">
        </div>
    </div>
    <div class="field">
        <label class="label" for="f-jenjang">Jenjang</label>
        <select class="select" id="f-jenjang"><option value="">Semua</option><option value="TK">TK</option><option value="SD">SD</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-kelas">Kelas</label>
        <select class="select" id="f-kelas"><option value="">Semua</option></select>
    </div>
    <div class="field">
        <label class="label" for="f-status">Status</label>
        <select class="select" id="f-status"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option><option value="semua">Semua</option></select>
    </div>
    <div class="toolbar-actions"><button type="button" class="btn btn-ghost" id="f-reset">Atur ulang</button></div>
</form>

<section class="card card-flush" aria-label="Daftar santri">
    <?php if ($manage): ?><div id="bulk-bar"></div><?php endif; ?>
    <div class="table-wrap" id="table-wrap">
        <table class="table table-stack">
            <thead id="thead"><tr>
                <th data-sort="code">ID</th>
                <th data-sort="name" data-stack="title">Nama</th>
                <th data-sort="kelas">Jenjang / Kelas</th>
                <th data-sort="urut" class="num">No. Urut</th>
                <th>Dawis / Blok</th>
                <th data-sort="saldo" class="num">Saldo</th>
                <th>Status</th>
                <?php if ($manage): ?><th class="col-actions" data-stack="actions"><span class="sr-only">Aksi</span></th><?php endif; ?>
            </tr></thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>
    <div class="empty" id="empty" hidden>
        <div class="empty-icon"><?= icon('users') ?></div>
        <h3 id="empty-title">Belum ada santri</h3>
        <p id="empty-text">Belum terdapat data santri.</p>
        <?php if ($manage): ?><button type="button" class="btn btn-primary" id="empty-add"><?= icon('user-plus') ?>Tambah Santri</button><?php endif; ?>
    </div>
    <div class="pagination" id="pager"></div>
</section>

<?php if ($manage): ?>
<dialog class="modal modal-lg" id="student-dialog" aria-labelledby="sd-title">
    <form id="student-form" novalidate>
        <div class="modal-head">
            <span class="modal-icon" aria-hidden="true"><?= icon('user-plus') ?></span>
            <h2 id="sd-title">Tambah Santri</h2>
        </div>
        <div class="modal-body">
            <div class="alert alert-danger" id="sd-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>
            <div class="form-grid" id="sd-grid">
                <div class="field span-2" data-field="name">
                    <label class="label" for="sd-name">Nama Santri <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" id="sd-name" name="name" maxlength="100" required autocomplete="off">
                    <span class="field-error" id="err-name"></span>
                </div>
                <div class="field" data-field="jenjang">
                    <label class="label" for="sd-jenjang">Jenjang <span class="req" aria-hidden="true">*</span></label>
                    <select class="select" id="sd-jenjang" name="jenjang" required><option value="">Pilih jenjang</option><option value="TK">TK</option><option value="SD">SD</option></select>
                    <span class="field-error" id="err-jenjang"></span>
                </div>
                <div class="field" data-field="kelas">
                    <label class="label" for="sd-kelas">Kelas <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" id="sd-kelas" name="kelas" list="sd-kelas-list" maxlength="20" required autocomplete="off" placeholder="mis. 4A atau TK B">
                    <datalist id="sd-kelas-list"></datalist>
                    <span class="field-error" id="err-kelas"></span>
                </div>
                <div class="field" data-field="student_code">
                    <label class="label" for="sd-code">ID Santri</label>
                    <input class="input" id="sd-code" name="student_code" maxlength="20" autocomplete="off">
                    <span class="hint" id="sd-code-hint">Kosongkan untuk dibuat otomatis.</span>
                    <span class="field-error" id="err-student_code"></span>
                </div>
                <div class="field" data-field="nis">
                    <label class="label" for="sd-nis">NIS / NISN</label>
                    <input class="input" id="sd-nis" name="nis" maxlength="30" autocomplete="off">
                    <span class="field-error" id="err-nis"></span>
                </div>
                <div class="field" data-field="no_urut">
                    <label class="label" for="sd-urut">No. Urut</label>
                    <input class="input" id="sd-urut" name="no_urut" inputmode="numeric" maxlength="4" autocomplete="off">
                    <span class="hint">Dipakai pada cetakan rekap.</span>
                    <span class="field-error" id="err-no_urut"></span>
                </div>
                <div class="field" data-field="dawis_blok">
                    <label class="label" for="sd-dawis">Dawis / Blok</label>
                    <input class="input" id="sd-dawis" name="dawis_blok" maxlength="100" autocomplete="off">
                    <span class="hint">Dipakai pada cetakan rekap.</span>
                    <span class="field-error" id="err-dawis_blok"></span>
                </div>
                <div class="field span-2" data-field="status" id="sd-status-field" hidden>
                    <label class="label" for="sd-status">Status</label>
                    <select class="select" id="sd-status" name="status"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select>
                    <span class="hint">Mengubah jenjang atau kelas tidak mengubah riwayat transaksi yang sudah ada.</span>
                    <span class="field-error" id="err-status"></span>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" id="sd-cancel">Batal</button>
            <button type="submit" class="btn btn-primary" id="sd-save">Simpan</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script type="application/json" id="students-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/bulk.js')) ?>" defer></script>
<script src="<?= e(asset('js/students.js')) ?>" defer></script>
<?php $this->endSection(); ?>
