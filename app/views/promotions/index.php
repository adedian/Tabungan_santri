<?php
/** @var array $initial  hasil PromotionService::options() */
$this->set('title', 'Kenaikan Kelas');
$this->set('heading', 'Kenaikan Kelas');
$this->set('lead', 'Proses per kelas: tinjau daftar santri, tentukan Naik atau Tidak Naik, konfirmasi, lalu proses. Transaksi tabungan tidak berubah.');
?>
<section class="card" aria-labelledby="pick-title">
    <div class="card-head"><h2 id="pick-title">1. Pilih kelas</h2></div>
    <div class="card-body">
        <form id="pick-form" class="form-grid promo-pick" autocomplete="off" novalidate>
            <div class="field">
                <label class="label" for="p-year">Tahun Ajaran Asal</label>
                <select class="select" id="p-year"></select>
            </div>
            <div class="field">
                <label class="label" for="p-toyear">Tahun Ajaran Tujuan</label>
                <input class="input" id="p-toyear" readonly>
            </div>
            <div class="field">
                <label class="label" for="p-jenjang">Jenjang</label>
                <select class="select" id="p-jenjang"><option value="">Pilih jenjang</option><option value="TK">TK</option><option value="SD">SD</option></select>
            </div>
            <div class="field">
                <label class="label" for="p-kelas">Kelas Asal</label>
                <select class="select" id="p-kelas" disabled><option value="">Pilih kelas</option></select>
            </div>
            <div class="field span-2">
                <label class="label" for="p-target">Kelas Tujuan</label>
                <input class="input" id="p-target" maxlength="20" disabled>
                <span class="hint" id="p-target-hint">Terisi otomatis menurut tangga kelas setelah kelas asal dipilih.</span>
            </div>
        </form>
    </div>
</section>

<section class="card card-flush mt-4" id="review-card" aria-labelledby="review-title" hidden>
    <div class="card-head">
        <div><h2 id="review-title">2. Tinjau &amp; tentukan status</h2><div class="sub" id="review-sub" aria-live="polite"></div></div>
    </div>

    <div class="alert alert-info promo-note" id="done-note" role="status" hidden>
        <?= icon('info') ?><div></div>
    </div>
    <div class="alert alert-warning promo-note" id="grad-note" role="note" hidden>
        <?= icon('graduation-cap') ?><div><b>Kelas 6 SD:</b> memilih <b>Naik</b> berarti santri <b>lulus</b> dan menjadi <b>alumni</b> (tidak ada kelas 7). Saldo dan seluruh riwayat transaksinya tetap tersimpan di menu Tabungan Alumni.</div>
    </div>

    <div id="review-body">
        <div class="bulk-bar promo-bar">
            <label class="bulk-all" for="r-all"><input class="bulk-check" type="checkbox" id="r-all"><span>Pilih Semua</span></label>
            <span class="bulk-count muted small" id="r-count" aria-live="polite"></span>
            <button type="button" class="btn btn-secondary" id="r-mark-naik" disabled><?= icon('trending-up') ?><span>Tandai Naik</span></button>
            <button type="button" class="btn btn-secondary" id="r-mark-stay" disabled>Tandai Tidak Naik</button>
        </div>

        <div class="table-wrap" id="table-wrap">
            <table class="table table-stack is-selecting" id="promo-table">
                <thead><tr>
                    <th class="col-check" data-stack="check"><span class="sr-only">Pilih</span></th>
                    <th data-stack="title">Nama</th>
                    <th>Kelas Sekarang</th>
                    <th class="num">Saldo</th>
                    <th data-stack="full">Status</th>
                </tr></thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>

        <div class="empty" id="empty" hidden>
            <div class="empty-icon"><?= icon('users') ?></div>
            <h3 id="empty-title">Tidak ada santri</h3>
            <p id="empty-text"></p>
        </div>

        <div class="promo-foot" id="promo-foot">
            <div class="promo-tally" aria-live="polite">
                <span class="badge badge-success" id="t-naik">Naik 0</span>
                <span class="badge badge-muted" id="t-stay">Tidak naik 0</span>
                <span class="badge badge-outline" id="t-none">Belum diproses 0</span>
            </div>
            <button type="button" class="btn btn-primary" id="r-process" disabled><?= icon('check') ?>Proses Kenaikan</button>
        </div>
    </div>
</section>

<section class="card card-flush mt-4" aria-labelledby="recent-title">
    <div class="card-head"><div><h2 id="recent-title">Riwayat Proses</h2><div class="sub">10 proses kenaikan terakhir</div></div></div>
    <div class="table-wrap">
        <table class="table table-stack" id="recent-table">
            <thead><tr>
                <th data-stack="title">Tahun Ajaran</th><th>Kelas</th><th class="num">Naik</th><th class="num">Lulus</th><th class="num">Tidak Naik</th><th>Diproses</th>
            </tr></thead>
            <tbody id="recent-body"></tbody>
        </table>
    </div>
    <div class="empty" id="recent-empty" hidden>
        <div class="empty-icon"><?= icon('inbox') ?></div>
        <h3>Belum ada proses</h3>
        <p>Proses kenaikan kelas yang sudah dijalankan akan tercatat di sini.</p>
    </div>
</section>

<dialog class="modal modal-lg" id="promo-dialog" aria-labelledby="pd-title">
    <form method="dialog" id="promo-form">
        <div class="modal-head">
            <span class="modal-icon" aria-hidden="true"><?= icon('trending-up') ?></span>
            <h2 id="pd-title">Konfirmasi Kenaikan Kelas</h2>
        </div>
        <div class="modal-body">
            <p id="pd-lead"></p>
            <dl class="summary" id="pd-summary"></dl>
            <div class="promo-names" id="pd-names"></div>
            <div class="alert alert-danger" id="pd-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>
            <p class="muted small">Proses ini dijalankan sebagai satu kesatuan: bila terjadi galat, tidak ada santri yang berubah. Tahun ajaran yang sama tidak dapat diproses dua kali untuk kelas ini.</p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" id="pd-back">Kembali</button>
            <button type="submit" class="btn btn-primary" id="pd-ok">Proses Kenaikan</button>
        </div>
    </form>
</dialog>

<script type="application/json" id="promo-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/promotions.js')) ?>" defer></script>
<?php $this->endSection(); ?>
