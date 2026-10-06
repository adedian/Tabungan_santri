<?php /** Dialog ubah transaksi (dipakai Riwayat & Detail Santri). Butuh $months (daftar bulan) dan $today (Y-m-d). */ ?>
<dialog class="modal modal-lg" id="edit-dialog" aria-labelledby="ed-title">
    <form id="edit-form" novalidate data-mutation="masuk">
        <div class="modal-head">
            <span class="modal-icon" aria-hidden="true"><?= icon('pencil') ?></span>
            <div><h2 id="ed-title">Ubah Transaksi</h2><div class="muted small" id="ed-sub"></div></div>
        </div>
        <div class="modal-body">
            <div class="alert alert-danger" id="ed-error" role="alert" hidden><?= icon('circle-alert') ?><div></div></div>
            <div class="form-grid">
                <div class="field" data-field="transaction_date">
                    <label class="label" for="ed-date">Tanggal <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" type="date" id="ed-date" name="transaction_date" max="<?= e($today) ?>" min="2000-01-01" required>
                    <span class="field-error" id="err-transaction_date"></span>
                </div>
                <div class="field" data-field="period_month">
                    <label class="label" for="ed-month">Bulan <span class="req" aria-hidden="true">*</span></label>
                    <select class="select" id="ed-month" name="period_month" required>
                        <?php foreach ($months as $n => $b): ?><option value="<?= $n ?>"><?= e($b) ?></option><?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-period_month"></span>
                </div>
                <div class="field" data-field="jenjang">
                    <label class="label" for="ed-jenjang">Jenjang <span class="req" aria-hidden="true">*</span></label>
                    <select class="select" id="ed-jenjang" name="jenjang" required><option value="TK">TK</option><option value="SD">SD</option></select>
                    <span class="field-error" id="err-jenjang"></span>
                </div>
                <div class="field" data-field="kelas">
                    <label class="label" for="ed-kelas">Kelas <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" id="ed-kelas" name="kelas" maxlength="20" required>
                    <span class="field-error" id="err-kelas"></span>
                </div>
                <div class="field" data-field="mutation_type">
                    <label class="label" for="ed-mutation">Mutasi <span class="req" aria-hidden="true">*</span></label>
                    <select class="select" id="ed-mutation" name="mutation_type" required
                            data-bind-label="#ed-desc-label" data-labels='{"masuk":"Keterangan Masuk","keluar":"Keterangan Keluar"}'>
                        <option value="masuk">Masuk</option><option value="keluar">Keluar</option>
                    </select>
                    <span class="field-error" id="err-mutation_type"></span>
                </div>
                <div class="field" data-field="amount">
                    <label class="label" for="ed-amount">Nominal <span class="req" aria-hidden="true">*</span></label>
                    <div class="input-group"><span class="affix" aria-hidden="true">Rp</span><input class="input input--money" id="ed-amount" name="amount" data-money required></div>
                    <span class="field-error" id="err-amount"></span>
                </div>
                <div class="field span-2" data-field="description">
                    <label class="label" id="ed-desc-label" for="ed-desc">Keterangan Masuk <span class="req" aria-hidden="true">*</span></label>
                    <input class="input" id="ed-desc" name="description" maxlength="255" required>
                    <span class="field-error" id="err-description"></span>
                </div>
            </div>
            <p class="hint mt-4">Santri tidak dapat dipindahkan. Perubahan ditolak bila membuat saldo santri negatif pada tanggal mana pun.</p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" id="ed-cancel">Batal</button>
            <button type="submit" class="btn btn-primary" id="ed-save">Simpan Perubahan</button>
        </div>
    </form>
</dialog>
