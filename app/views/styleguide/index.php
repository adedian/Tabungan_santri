<?php
$this->set('title', 'Panduan Komponen');
$this->set('heading', 'Panduan Komponen');
$this->set('lead', 'Katalog elemen antarmuka yang dipakai di seluruh aplikasi. Contoh di halaman ini hanya data statis.');

$swatches = [
    ['Hijau 900', '--green-900', 'Sidebar, judul'], ['Hijau 800', '--green-800', 'Aksi utama'], ['Emerald 700', '--emerald-700', 'Link, "masuk"'],
    ['Sage 100', '--sage-100', 'Permukaan lembut'], ['Sage 50', '--sage-50', 'Hover, header tabel'], ['Gold 500', '--gold-500', 'Garis dekoratif'],
    ['Beige 50', '--beige-50', 'Aksen jenjang'], ['Latar', '--bg', 'Latar halaman'], ['Permukaan', '--surface', 'Kartu'],
    ['Garis', '--line', 'Border halus'], ['Bahaya 700', '--danger-700', 'Error, "keluar"'], ['Peringatan 700', '--warning-700', 'Peringatan'],
];
$rows = [
    ['06/10/2026', 'Ahmad Rizky', 'SD', '5B', 'masuk', 50000, 'Setoran tabungan pekan ke-6', 105000],
    ['06/10/2026', 'Aisyah Zahra', 'TK', 'TK A', 'masuk', 15000, 'Setoran tabungan', 90000],
    ['05/10/2026', 'Hasan Basri', 'SD', '2A', 'keluar', 20000, 'Beli buku tulis', 45000],
];
?>

<div class="stack-lg">

<!-- ================= WARNA ================= -->
<section class="section">
    <h2 class="section-title">Warna</h2>
    <div class="swatches">
        <?php foreach ($swatches as [$name, $token, $use]): ?>
            <div class="swatch">
                <div class="swatch-chip" style="background:var(<?= e($token) ?>)"></div>
                <div class="swatch-meta"><b><?= e($name) ?></b><code><?= e($token) ?></code><br><span class="muted"><?= e($use) ?></span></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ================= TIPOGRAFI ================= -->
<section class="section">
    <h2 class="section-title">Tipografi</h2>
    <div class="card"><div class="card-body type-sample">
        <h1>Judul halaman — serif hangat</h1>
        <h2>Judul bagian — Plus Jakarta Sans</h2>
        <h3>Judul kartu</h3>
        <p>Teks isi 15px. Operator harus dapat bekerja cepat: huruf jelas, kontras tinggi, angka rata <span class="num">1.234.567</span>.</p>
        <p class="muted small">Teks sekunder 13px untuk keterangan dan bantuan.</p>
        <p class="eyebrow">Eyebrow / label kecil</p>
    </div></div>
</section>

<!-- ================= TOMBOL ================= -->
<section class="section">
    <h2 class="section-title">Tombol</h2>
    <div class="card"><div class="card-body stack">
        <div class="demo-row">
            <button class="btn btn-primary" type="button"><?= icon('check') ?>Simpan Transaksi</button>
            <button class="btn btn-secondary" type="button">Batal</button>
            <button class="btn btn-ghost" type="button">Lewati</button>
            <button class="btn btn-danger" type="button"><?= icon('trash') ?>Hapus</button>
            <button class="btn btn-primary" type="button" disabled>Nonaktif</button>
        </div>
        <div class="demo-row">
            <button class="btn btn-primary btn-sm" type="button">Kecil</button>
            <button class="btn btn-primary" type="button">Sedang</button>
            <button class="btn btn-primary btn-lg" type="button">Besar</button>
            <button class="btn btn-secondary btn-icon" type="button" aria-label="Cetak"><?= icon('printer') ?></button>
            <button class="btn btn-secondary btn-icon btn-sm" type="button" aria-label="Ubah"><?= icon('pencil') ?></button>
            <button class="btn btn-primary" type="button" data-demo-loading>Coba keadaan memuat</button>
        </div>
    </div></div>
</section>

<!-- ================= FORM ================= -->
<section class="section">
    <h2 class="section-title">Form transaksi</h2>
    <form class="card" action="#" data-mutation="masuk" data-demo-form>
        <div class="card-body">
            <div class="form-section">
                <div class="form-section-head"><h3>Informasi Transaksi</h3><p>Siapa dan untuk periode apa tabungan ini dicatat.</p></div>
                <div class="stack">
                    <div class="form-grid">
                        <div class="field"><label class="label" for="g-tgl">Tanggal</label><input class="input" id="g-tgl" type="date" value="<?= e(date('Y-m-d')) ?>"></div>
                        <div class="field"><label class="label" for="g-bulan">Bulan</label>
                            <select class="select" id="g-bulan"><?php foreach (bulan_list() as $n => $b): ?><option value="<?= $n ?>"<?= $n === (int) date('n') ? ' selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?></select></div>
                        <div class="field span-2"><label class="label" for="g-santri">Nama Santri</label>
                            <input class="input" id="g-santri" type="text" placeholder="Cari nama, kelas, atau ID…" autocomplete="off"><span class="hint">Pencarian santri aktif di Phase 8.</span></div>
                    </div>
                    <div class="student-chip">
                        <span class="avatar" aria-hidden="true">AR</span>
                        <div><div class="who">Ahmad Rizky</div><div class="meta">SD • Kelas 5B</div></div>
                        <div class="bal"><span class="meta">Saldo saat ini</span><b>Rp 105.000</b></div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-section-head"><h3>Detail Mutasi</h3><p>Jenis transaksi menentukan label keterangan.</p></div>
                <div class="form-grid">
                    <div class="field"><label class="label" for="g-mutasi">Mutasi</label>
                        <select class="select" id="g-mutasi" data-bind-label="#g-ket-label" data-labels='{"masuk":"Keterangan Masuk","keluar":"Keterangan Keluar"}'>
                            <option value="masuk">Masuk</option><option value="keluar">Keluar</option></select></div>
                    <div class="field"><label class="label" for="g-nominal">Nominal</label>
                        <div class="input-group"><span class="affix">Rp</span><input class="input input--money" id="g-nominal" type="text" data-money placeholder="0" value="50000"></div></div>
                    <div class="field span-2"><label class="label" id="g-ket-label" for="g-ket">Keterangan Masuk</label>
                        <input class="input" id="g-ket" type="text" placeholder="Contoh: setoran tabungan pekan ke-6"></div>
                    <div class="field span-2 has-error"><label class="label" for="g-err">Contoh keadaan error</label>
                        <input class="input" id="g-err" type="text" value="abc" aria-invalid="true" aria-describedby="g-err-msg">
                        <span class="field-error" id="g-err-msg"><?= icon('circle-alert') ?>Nominal harus lebih dari 0.</span></div>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-secondary" type="button">Reset</button>
                <button class="btn btn-primary" type="button" data-demo-confirm><?= icon('check') ?>Simpan Transaksi</button>
            </div>
        </div>
    </form>
</section>

<!-- ================= ANGKA RINGKASAN ================= -->
<section class="section">
    <h2 class="section-title">Angka ringkasan</h2>
    <div class="grid grid-4">
        <div class="stat stat--hero"><div class="stat-label"><?= icon('wallet') ?>Total Saldo</div><div class="stat-value num"><?= rupiah_html(24500000) ?></div><div class="stat-note">245 santri aktif</div></div>
        <div class="stat"><div class="stat-label"><?= icon('arrow-down-left') ?>Total Masuk</div><div class="stat-value num is-masuk"><?= rupiah_html(32000000) ?></div><div class="stat-note">Semua periode</div></div>
        <div class="stat"><div class="stat-label"><?= icon('arrow-up-right') ?>Total Keluar</div><div class="stat-value num is-keluar"><?= rupiah_html(7500000) ?></div><div class="stat-note">Semua periode</div></div>
        <div class="stat"><div class="stat-label"><?= icon('users') ?>Jumlah Santri</div><div class="stat-value num">245</div><div class="stat-note">TK 82 • SD 163</div></div>
    </div>
</section>

<!-- ================= TABEL ================= -->
<section class="section">
    <h2 class="section-title">Tabel, badge, dan paginasi</h2>
    <div class="card card-flush">
        <div class="card-head"><div><h2>Transaksi Terbaru</h2><div class="sub">Contoh data</div></div>
            <span class="sync"><span class="sync-dot"></span>Terhubung</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Santri</th><th>Kelas</th><th>Mutasi</th><th class="num">Nominal</th><th>Keterangan</th><th class="num">Saldo</th><th class="col-actions"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => [$tgl, $nama, $jenjang, $kelas, $mut, $nom, $ket, $saldo]): ?>
                    <tr<?= $i === 0 ? ' class="is-new"' : '' ?>>
                        <td class="nowrap tabular"><?= e($tgl) ?></td>
                        <td><div class="cell-main"><?= e($nama) ?></div></td>
                        <td><span class="badge badge-outline"><?= e($jenjang) ?></span> <span class="cell-sub"><?= e($kelas) ?></span></td>
                        <td><?php if ($mut === 'masuk'): ?><span class="badge badge-masuk"><?= icon('arrow-down-left') ?>Masuk</span><?php else: ?><span class="badge badge-keluar"><?= icon('arrow-up-right') ?>Keluar</span><?php endif; ?></td>
                        <td class="num"><span class="amt is-<?= e($mut) ?>"><?= $mut === 'masuk' ? '+' : '−' ?><?= e(rupiah($nom)) ?></span></td>
                        <td class="cell-sub"><?= e($ket) ?></td>
                        <td class="num amt"><?= e(rupiah($saldo)) ?></td>
                        <td class="col-actions"><button class="btn btn-ghost btn-icon btn-sm" type="button" aria-label="Ubah"><?= icon('pencil') ?></button><button class="btn btn-ghost btn-icon btn-sm" type="button" aria-label="Hapus"><?= icon('trash') ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <span>Menampilkan 1–3 dari 73 transaksi</span>
            <nav class="pager" aria-label="Halaman">
                <a class="pager-btn" aria-disabled="true" aria-label="Sebelumnya"><?= icon('chevron-left') ?></a>
                <a class="pager-btn" href="#" aria-current="page">1</a><a class="pager-btn" href="#">2</a><a class="pager-btn" href="#">3</a>
                <a class="pager-btn" href="#" aria-label="Berikutnya"><?= icon('chevron-right') ?></a>
            </nav>
        </div>
    </div>

    <div class="grid grid-2 mt-6">
        <div class="card"><div class="empty">
            <div class="empty-icon"><?= icon('inbox') ?></div>
            <h3>Belum ada transaksi</h3>
            <p>Belum terdapat data transaksi tabungan pada periode ini.</p>
            <a class="btn btn-primary" href="#"><?= icon('plus') ?>Tambah Tabungan</a>
        </div></div>
        <div class="card"><div class="card-body" aria-busy="true" aria-label="Memuat">
            <div class="skeleton skeleton-line" style="width:40%"></div><div class="skeleton skeleton-line"></div>
            <div class="skeleton skeleton-line" style="width:85%"></div><div class="skeleton skeleton-line" style="width:60%"></div>
        </div></div>
    </div>
</section>

<!-- ================= GRAFIK ================= -->
<section class="section">
    <h2 class="section-title">Grafik, kontrol segmen, dan meter</h2>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head">
                <div><h2>Aktivitas Tabungan</h2><div class="sub">Kolom berkelompok • contoh data</div></div>
                <div class="segmented" role="group" aria-label="Contoh kontrol segmen">
                    <button type="button" aria-pressed="false">Hari</button><button type="button" aria-pressed="true">Minggu</button><button type="button" aria-pressed="false">Bulan</button>
                </div>
            </div>
            <div class="card-body"><div id="sg-legend"></div><div class="mt-4" id="sg-chart"></div></div>
        </div>
        <div class="card">
            <div class="card-head"><div><h2>Meter porsi</h2><div class="sub">Lintasan = langkah lebih terang dari isi</div></div></div>
            <div class="card-body stack">
                <div><div class="cluster-between small"><span>TK</span><b>35%</b></div><div class="meter mt-2"><span style="width:35%"></span></div></div>
                <div><div class="cluster-between small"><span>SD</span><b>65%</b></div><div class="meter mt-2"><span style="width:65%"></span></div></div>
                <p class="hint">Warna grafik (<code>--chart-1</code> hijau, <code>--chart-2</code> amber) sudah divalidasi untuk buta warna. Setiap batang memiliki tooltip (hover/fokus keyboard) dan tabel pendamping.</p>
            </div>
        </div>
    </div>
</section>

<!-- ================= UMPAN BALIK ================= -->
<section class="section">
    <h2 class="section-title">Alert, toast, dan konfirmasi</h2>
    <div class="grid grid-2">
        <div class="stack">
            <div class="alert alert-success"><?= icon('circle-check') ?><div>Transaksi berhasil disimpan.</div></div>
            <div class="alert alert-danger"><?= icon('circle-alert') ?><div>Saldo santri tidak mencukupi.</div></div>
            <div class="alert alert-warning"><?= icon('triangle-alert') ?><div>Sesi akan berakhir dalam 5 menit.</div></div>
            <div class="alert alert-info"><?= icon('info') ?><div>Data diperbarui otomatis setiap beberapa detik.</div></div>
        </div>
        <div class="card"><div class="card-body stack">
            <div class="demo-row">
                <button class="btn btn-secondary" type="button" data-demo-toast="success">Toast sukses</button>
                <button class="btn btn-secondary" type="button" data-demo-toast="error">Toast error</button>
                <button class="btn btn-secondary" type="button" data-demo-toast="warning">Toast peringatan</button>
            </div>
            <div class="demo-row">
                <button class="btn btn-secondary" type="button" data-demo-confirm>Konfirmasi transaksi keluar</button>
                <button class="btn btn-danger" type="button" data-demo-delete><?= icon('trash') ?>Hapus transaksi</button>
            </div>
        </div></div>
    </div>
</section>

</div>

<?php $this->section('scripts'); ?>
<script src="<?= e(asset('js/chart.js')) ?>" defer></script>
<script src="<?= e(asset('js/styleguide.js')) ?>" defer></script>
<?php $this->endSection(); ?>
