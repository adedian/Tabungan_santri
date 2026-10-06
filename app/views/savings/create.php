<?php
/** @var array $initial  {rev, today, month, student, classes, recent} */
$this->set('title', 'Tambah Tabungan');
$this->set('heading', 'Tambah Tabungan');
$this->set('lead', 'Catat setoran (masuk) atau penarikan (keluar) tabungan santri.');

$this->section('topbar'); ?>
<span class="sync" data-sync><span class="sync-dot"></span><span class="sync-label">Terhubung</span></span>
<?php $this->endSection(); ?>

<div class="form-layout">
    <form class="card" id="savings-form" novalidate data-mutation="masuk" autocomplete="off">
        <div class="card-body">
            <div class="alert alert-danger" id="form-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>

            <div class="form-section form-section--stack">
                <div class="form-section-head"><h3>Informasi Transaksi</h3><p>Siapa dan untuk periode apa tabungan ini dicatat.</p></div>
                <div class="form-grid-3">
                    <div class="field" data-field="transaction_date">
                        <label class="label" for="f-date">Tanggal <span class="req" aria-hidden="true">*</span></label>
                        <input class="input" type="date" id="f-date" name="transaction_date" value="<?= e($initial['today']) ?>" max="<?= e($initial['today']) ?>" min="2000-01-01" required>
                        <span class="field-error" id="err-transaction_date"></span>
                    </div>
                    <div class="field span-2" data-field="student_id">
                        <label class="label" for="f-student">Nama Santri <span class="req" aria-hidden="true">*</span></label>
                        <input class="input" type="text" id="f-student" placeholder="Cari nama, kelas, atau ID santri…" aria-describedby="err-student_id" autofocus>
                        <input type="hidden" id="f-student-id" name="student_id">
                        <span class="field-error" id="err-student_id"></span>
                    </div>

                    <div class="student-chip span-3" id="student-chip" hidden aria-live="polite">
                        <span class="avatar" id="chip-avatar" aria-hidden="true"></span>
                        <div><div class="who" id="chip-name"></div><div class="meta" id="chip-meta"></div></div>
                        <div class="bal"><span class="meta">Saldo saat ini</span><b id="chip-saldo"></b></div>
                    </div>

                    <div class="field" data-field="jenjang">
                        <label class="label" for="f-jenjang">Jenjang <span class="req" aria-hidden="true">*</span></label>
                        <select class="select" id="f-jenjang" name="jenjang" required>
                            <option value="">Pilih jenjang</option><option value="TK">TK</option><option value="SD">SD</option>
                        </select>
                        <span class="field-error" id="err-jenjang"></span>
                    </div>
                    <div class="field" data-field="kelas">
                        <label class="label" for="f-kelas">Kelas <span class="req" aria-hidden="true">*</span></label>
                        <input class="input" id="f-kelas" name="kelas" list="f-kelas-list" maxlength="20" required placeholder="mis. 4A atau TK B">
                        <datalist id="f-kelas-list"></datalist>
                        <span class="hint" id="class-hint" hidden></span>
                        <span class="field-error" id="err-kelas"></span>
                    </div>
                    <div class="field" data-field="period_month">
                        <label class="label" for="f-month">Bulan <span class="req" aria-hidden="true">*</span></label>
                        <select class="select" id="f-month" name="period_month" required>
                            <?php foreach (bulan_list() as $n => $b): ?>
                                <option value="<?= $n ?>"<?= $n === $initial['month'] ? ' selected' : '' ?>><?= e($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Periode tabungan.</span>
                        <span class="field-error" id="err-period_month"></span>
                    </div>
                </div>
            </div>

            <div class="form-section form-section--stack">
                <div class="form-section-head"><h3>Detail Mutasi</h3><p>Jenis transaksi menentukan label keterangan.</p></div>
                <div class="form-grid-3">
                    <div class="field" data-field="mutation_type">
                        <label class="label" for="f-mutation">Mutasi <span class="req" aria-hidden="true">*</span></label>
                        <select class="select" id="f-mutation" name="mutation_type" required
                                data-bind-label="#f-desc-label"
                                data-labels='{"masuk":"Keterangan Masuk","keluar":"Keterangan Keluar"}'
                                data-placeholders='{"masuk":"Contoh: setoran tabungan pekan ke-6","keluar":"Contoh: beli buku tulis"}'>
                            <option value="masuk">Masuk</option><option value="keluar">Keluar</option>
                        </select>
                        <span class="field-error" id="err-mutation_type"></span>
                    </div>
                    <div class="field span-2" data-field="amount">
                        <label class="label" for="f-amount">Nominal <span class="req" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <span class="affix" aria-hidden="true">Rp</span>
                            <input class="input input--money" id="f-amount" name="amount" data-money placeholder="0" aria-describedby="err-amount" required>
                        </div>
                        <div class="chips" id="quick-amounts" role="group" aria-label="Pilihan nominal cepat">
                            <?php foreach ([5000, 10000, 20000, 50000, 100000] as $q): ?>
                                <button type="button" class="chip" data-amount="<?= $q ?>"><?= e(number_format($q, 0, ',', '.')) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <span class="field-error" id="err-amount"></span>
                    </div>
                    <div class="span-3" id="preview-wrap" hidden>
                        <div class="preview" id="preview" role="status" aria-live="polite"><span id="preview-label">Saldo setelah transaksi</span><b id="preview-value"></b></div>
                    </div>
                    <div class="field span-3" data-field="description">
                        <label class="label" id="f-desc-label" for="f-desc">Keterangan Masuk <span class="req" aria-hidden="true">*</span></label>
                        <input class="input" id="f-desc" name="description" maxlength="255" required placeholder="Contoh: setoran tabungan pekan ke-6">
                        <span class="field-error" id="err-description"></span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="btn-reset">Reset</button>
                <button type="submit" class="btn btn-primary btn-lg" id="btn-save"><?= icon('check') ?>Simpan Transaksi</button>
            </div>
        </div>
    </form>

    <aside class="card card-flush" aria-labelledby="recent-title">
        <div class="card-head"><div><h2 id="recent-title">Baru dicatat</h2><div class="sub">Diperbarui otomatis</div></div></div>
        <ul class="recent-list" id="recent-list"></ul>
        <div class="empty" id="recent-empty" hidden>
            <div class="empty-icon"><?= icon('inbox') ?></div>
            <h3>Belum ada transaksi</h3>
            <p>Transaksi yang baru dicatat akan tampil di sini.</p>
        </div>
    </aside>
</div>

<script type="application/json" id="savings-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/combobox.js')) ?>" defer></script>
<script src="<?= e(asset('js/savings-form.js')) ?>" defer></script>
<?php $this->endSection(); ?>
