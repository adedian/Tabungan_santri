<?php
/** @var array $initial  hasil UserService::listing() */
$this->set('title', 'Pengguna');
$this->set('heading', 'Pengguna');
$this->set('lead', 'Kelola akun yang dapat masuk ke sistem. Pengguna nonaktif tidak dapat login; riwayat aktivitasnya tetap tersimpan di Audit Log.');

$this->section('actions'); ?>
    <button type="button" class="btn btn-primary" id="btn-add"><?= icon('user-plus') ?>Tambah Pengguna</button>
<?php $this->endSection(); ?>

<form class="toolbar" id="filters" role="search" autocomplete="off">
    <div class="field field-grow">
        <label class="label" for="f-q">Cari pengguna</label>
        <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="f-q" placeholder="Nama, username, atau email" maxlength="100">
        </div>
    </div>
    <div class="field">
        <label class="label" for="f-role">Peran</label>
        <select class="select" id="f-role">
            <option value="">Semua</option>
            <?php foreach ($initial['roles'] as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="f-status">Status</label>
        <select class="select" id="f-status"><option value="">Semua</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select>
    </div>
    <div class="toolbar-actions"><button type="button" class="btn btn-ghost" id="f-reset">Atur ulang</button></div>
</form>

<section class="card card-flush" aria-label="Daftar pengguna">
    <div class="table-wrap" id="table-wrap">
        <table class="table table-stack">
            <thead id="thead"><tr>
                <th data-sort="name" data-stack="title">Nama</th>
                <th data-sort="username">Username</th>
                <th data-sort="role">Peran</th>
                <th data-sort="last_login">Login Terakhir</th>
                <th data-sort="status">Status</th>
                <th class="col-actions" data-stack="actions"><span class="sr-only">Aksi</span></th>
            </tr></thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>
    <div class="empty" id="empty" hidden>
        <div class="empty-icon"><?= icon('users') ?></div>
        <h3 id="empty-title">Tidak ada pengguna</h3>
        <p id="empty-text">Coba ubah kata kunci atau filter pencarian.</p>
    </div>
    <div class="pagination" id="pager"></div>
</section>

<dialog class="modal modal-lg" id="user-dialog" aria-labelledby="ud-title">
    <form id="user-form" novalidate>
        <div class="modal-head">
            <span class="modal-icon" aria-hidden="true"><?= icon('user-plus') ?></span>
            <h2 id="ud-title">Tambah Pengguna</h2>
        </div>
        <div class="modal-body">
            <div class="alert alert-danger" id="ud-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>
            <div class="form-grid">
                <div class="field span-2" data-field="name">
                    <label class="label" for="ud-name">Nama Lengkap <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" id="ud-name" name="name" maxlength="100" required autocomplete="off">
                    <span class="field-error" id="err-name"></span>
                </div>
                <div class="field" data-field="username">
                    <label class="label" for="ud-username">Username <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" id="ud-username" name="username" maxlength="50" required autocomplete="off" autocapitalize="none" spellcheck="false">
                    <span class="hint">Huruf kecil, angka, titik, _ atau -.</span>
                    <span class="field-error" id="err-username"></span>
                </div>
                <div class="field" data-field="role">
                    <label class="label" for="ud-role">Peran <span class="req" aria-hidden="true">*</span></label>
                    <select class="select" id="ud-role" name="role" required>
                        <option value="">Pilih peran</option>
                        <?php foreach ($initial['roles'] as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                    <span class="hint" id="ud-role-hint"></span>
                    <span class="field-error" id="err-role"></span>
                </div>
                <div class="field span-2" data-field="email">
                    <label class="label" for="ud-email">Email</label>
                    <input class="input" type="email" id="ud-email" name="email" maxlength="150" autocomplete="off">
                    <span class="hint">Opsional. Dapat dipakai untuk login.</span>
                    <span class="field-error" id="err-email"></span>
                </div>
                <div class="field span-2" data-field="password" id="ud-pw-field">
                    <label class="label" for="ud-password">Kata Sandi <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" type="password" id="ud-password" name="password" maxlength="72" autocomplete="new-password">
                    <span class="hint">Minimal 8 karakter. Sampaikan kepada pengguna secara langsung.</span>
                    <span class="field-error" id="err-password"></span>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" id="ud-cancel">Batal</button>
            <button type="submit" class="btn btn-primary" id="ud-save">Simpan</button>
        </div>
    </form>
</dialog>

<dialog class="modal modal-lg" id="pw-dialog" aria-labelledby="pw-title">
    <form id="pw-form" novalidate>
        <div class="modal-head">
            <span class="modal-icon" aria-hidden="true"><?= icon('lock') ?></span>
            <h2 id="pw-title">Atur Ulang Kata Sandi</h2>
        </div>
        <div class="modal-body">
            <div class="alert alert-danger" id="pw-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>
            <p class="muted" id="pw-who"></p>
            <div class="field" data-field="password">
                <label class="label" for="pw-password">Kata Sandi Baru <span class="req" aria-hidden="true">*</span></label>
                <input class="input" type="password" id="pw-password" name="password" maxlength="72" autocomplete="new-password" required>
                <span class="hint">Minimal 8 karakter. Sesi yang sedang berjalan tidak otomatis keluar.</span>
                <span class="field-error" id="pw-err-password"></span>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" id="pw-cancel">Batal</button>
            <button type="submit" class="btn btn-primary" id="pw-save">Atur Ulang</button>
        </div>
    </form>
</dialog>

<script type="application/json" id="users-data"><?= json_for_script($initial) ?></script>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/table.js')) ?>" defer></script>
<script src="<?= e(asset('js/users.js')) ?>" defer></script>
<?php $this->endSection(); ?>
