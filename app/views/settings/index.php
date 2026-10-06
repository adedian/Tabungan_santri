<?php
/** @var array $initial  hasil SettingsService::current() */
$this->set('title', 'Pengaturan');
$this->set('heading', 'Pengaturan');
$this->set('lead', 'Identitas lembaga yang tampil di sidebar dan halaman login.');
?>
<form class="card" id="settings-form" novalidate autocomplete="off">
    <div class="card-head">
        <div><h2>Identitas Lembaga</h2><div class="sub">Nama dan logo</div></div>
    </div>
    <div class="card-body stack">
        <div class="alert alert-danger" id="st-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>

        <div class="field" data-field="school_name">
            <label class="label" for="st-name">Nama Lembaga <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="st-name" name="school_name" maxlength="100" required value="<?= e($initial['school_name']) ?>">
            <span class="field-error" id="err-school_name"></span>
        </div>

        <div class="field" data-field="logo">
            <span class="label" id="st-logo-label">Logo</span>
            <div class="cluster">
                <span class="brand-mark st-logo-preview" id="st-logo-box" aria-hidden="true">
                    <img id="st-logo-img" alt="" width="34" height="34" hidden>
                    <svg id="st-logo-ph" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 25V15a9 9 0 0 1 18 0v10"/><path d="M4.5 25h23"/><path d="M12.5 25v-6.5a3.5 3.5 0 0 1 7 0V25"/>
                    </svg>
                </span>
                <input type="file" id="st-logo-file" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden aria-labelledby="st-logo-label">
                <button type="button" class="btn btn-secondary" id="st-logo-pick"><?= icon('download') ?>Pilih Logo</button>
                <button type="button" class="btn btn-ghost" id="st-logo-remove" hidden><?= icon('trash') ?>Hapus Logo</button>
            </div>
            <span class="hint" id="st-logo-hint">PNG, JPG, WEBP, atau SVG. Otomatis diperkecil (maks. 256 px) dan diubah menjadi PNG.</span>
            <span class="field-error" id="err-logo"></span>
        </div>
    </div>
    <div class="card-foot cluster-between">
        <span class="muted" id="st-dirty" hidden>Ada perubahan yang belum disimpan.</span>
        <button type="submit" class="btn btn-primary" id="st-save"><?= icon('check') ?>Simpan</button>
    </div>
</form>

<script type="application/json" id="settings-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/settings.js')) ?>" defer></script>
<?php $this->endSection(); ?>
