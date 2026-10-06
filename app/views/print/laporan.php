<?php
/**
 * Cetak Laporan Tabungan — FORMAT SEMENTARA (menunggu contoh cetakan dari pengguna).
 * Bagian yang perlu disesuaikan nanti: judul/kop, urutan blok, kolom, tanda tangan.
 *
 * @var array  $f filter, $sum ringkasan, $period, $filter (teks), $classes, $rows (rekap per santri), $by (pencetak), $query
 */
use App\Services\PrintService as P;

$this->set('title', 'Cetak Laporan Tabungan');
if (!empty($_GET['autoprint'])) { $this->set('autoprint', true); }

$p = ['from' => $f['from'], 'to' => $f['to'], 'rows' => P::DEFAULT_ROWS, 'meta' => !empty($_GET['meta'])];
$hidden = array_diff_key($query, ['from' => 1, 'to' => 1]);
$this->partial('partials/print_toolbar', [
    'back' => ['/laporan' . ($query ? '?' . http_build_query($query) : ''), 'Laporan'],
    'action' => url('/print/laporan'), 'hidden' => $hidden, 'p' => $p, 'showRows' => false,
    'summary' => count($rows) . ' santri • ' . $sum['count'] . ' transaksi',
]);
?>
<main class="print-area">
    <div class="no-print print-draft" role="note">
        <?= icon('info') ?>
        <div><b>Format sementara.</b> Tata letak cetak laporan ini belum mengikuti contoh Anda. Kirim contoh cetakan yang diinginkan agar saya sesuaikan
            (kop, kolom, tanda tangan, ukuran kertas).</div>
    </div>

    <section class="sheet">
        <div class="lap">
            <h1 class="lap-title">Laporan Tabungan Santri</h1>
            <p class="lap-sub">Periode: <b><?= e($period) ?></b></p>
            <p class="lap-sub">Filter: <?= e($filter) ?></p>

            <table class="lap-table lap-summary">
                <tbody>
                    <tr><th>Total Masuk</th><td class="num"><?= e(rupiah($sum['masuk'])) ?></td><th>Jumlah Transaksi</th><td class="num"><?= e(P::num($sum['count'])) ?></td></tr>
                    <tr><th>Total Keluar</th><td class="num"><?= e(rupiah($sum['keluar'])) ?></td><th>Santri Bertransaksi</th><td class="num"><?= e(P::num($sum['students'])) ?></td></tr>
                    <tr><th>Saldo (masuk − keluar)</th><td class="num"><b><?= e(rupiah($sum['net'])) ?></b></td><th></th><td></td></tr>
                </tbody>
            </table>

            <h2 class="lap-h2">Rekap per Kelas</h2>
            <table class="lap-table">
                <thead><tr><th>Jenjang</th><th>Kelas</th><th class="num">Santri</th><th class="num">Transaksi</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Selisih</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $c): ?>
                    <tr><td><?= e($c['jenjang']) ?></td><td><?= e($c['kelas']) ?></td><td class="num"><?= e(P::num($c['students'])) ?></td><td class="num"><?= e(P::num($c['count'])) ?></td>
                        <td class="num"><?= e(P::num($c['masuk'])) ?></td><td class="num"><?= e(P::num($c['keluar'])) ?></td><td class="num"><?= e(P::num($c['net'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$classes): ?><tr><td colspan="7" class="empty-row">Tidak ada transaksi.</td></tr><?php endif; ?>
                </tbody>
                <?php if ($classes): ?><tfoot><tr><td colspan="2">Total</td><td class="num"><?= e(P::num($sum['students'])) ?></td><td class="num"><?= e(P::num($sum['count'])) ?></td>
                    <td class="num"><?= e(P::num($sum['masuk'])) ?></td><td class="num"><?= e(P::num($sum['keluar'])) ?></td><td class="num"><?= e(P::num($sum['net'])) ?></td></tr></tfoot><?php endif; ?>
            </table>

            <h2 class="lap-h2">Rekap per Santri</h2>
            <table class="lap-table">
                <thead><tr><th>No</th><th>Nama</th><th>Kelas</th><th class="num">Transaksi</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Selisih</th><th class="num"><?= $f['to'] !== '' ? 'Saldo per ' . e(tanggal_id($f['to'], true)) : 'Saldo saat ini' ?></th></tr></thead>
                <tbody>
                <?php $tot = 0; foreach ($rows as $i => $r): $tot += $r['saldo']; ?>
                    <tr><td><?= $i + 1 ?></td><td><?= e($r['name']) ?></td><td><?= e($r['jenjang'] . ' ' . $r['kelas']) ?></td><td class="num"><?= e(P::num($r['count'])) ?></td>
                        <td class="num"><?= e(P::num($r['masuk'])) ?></td><td class="num"><?= e(P::num($r['keluar'])) ?></td><td class="num"><?= e(P::num($r['net'])) ?></td><td class="num"><?= e(P::num($r['saldo'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8" class="empty-row">Tidak ada transaksi.</td></tr><?php endif; ?>
                </tbody>
                <?php if ($rows): ?><tfoot><tr><td colspan="3">Total</td><td class="num"><?= e(P::num($sum['count'])) ?></td><td class="num"><?= e(P::num($sum['masuk'])) ?></td>
                    <td class="num"><?= e(P::num($sum['keluar'])) ?></td><td class="num"><?= e(P::num($sum['net'])) ?></td><td class="num"><?= e(P::num($tot)) ?></td></tr></tfoot><?php endif; ?>
            </table>

            <?php if ($p['meta']): ?><p class="lap-meta">Dicetak <?= e(tanggal_id(date('Y-m-d')) . ' ' . date('H:i')) ?> oleh <?= e($by) ?></p><?php endif; ?>
        </div>
    </section>
</main>
