<?php
/**
 * Rekap Tabungan per Nama — mengikuti contoh Excel pengguna:
 *   judul tebal bergaris bawah (TK → "TK / KB"), blok NO. URUT / NAMA / KELAS,
 *   tabel NO | TGL | KETERANGAN | MASUK | KELUAR | TOTAL TABUNGAN (header peach), baris "Total Tabungan".
 *
 * @var array  $docs   hasil PrintService::rekap() per santri
 * @var array  $p      from,to,rows,meta
 * @var string $mode   single|batch
 */
use App\Services\PrintService as P;

$pageCount = 0;
foreach ($docs as $d) { $pageCount += count($d['pages']); }
$names = count($docs) === 1 ? $docs[0]['student']['name'] : count($docs) . ' santri';
$this->set('title', 'Cetak Rekap Tabungan — ' . $names);
if (!empty($_GET['autoprint'])) { $this->set('autoprint', true); }

$summary = $pageCount . ' halaman' . ($mode === 'batch' ? ' • ' . count($docs) . ' santri' : '');
$extra = '';
if ($mode === 'batch') {
    $extra = '<label class="check"><input type="checkbox" name="skip_empty" value="1"' . (!empty($skipEmpty) ? ' checked' : '') . '> Lewati santri tanpa transaksi</label>'
           . '<label class="check"><input type="checkbox" name="inactive" value="1"' . (!empty($inactive) ? ' checked' : '') . '> Sertakan santri nonaktif</label>';
}
$this->partial('partials/print_toolbar', [
    'back' => $back, 'action' => $action, 'hidden' => $hidden ?? [], 'p' => $p, 'showRows' => true, 'summary' => $summary, 'extra' => $extra,
]);
$stamp = tanggal_id(date('Y-m-d')) . ' ' . date('H:i') . ' • ' . (string) (auth_user()['name'] ?? '');
?>
<main class="print-area">
<?php if (!$docs): ?>
    <div class="print-empty no-print"><h2>Tidak ada santri untuk dicetak</h2><p>Ubah pilihan di atas (mis. matikan “Lewati santri tanpa transaksi”).</p></div>
<?php endif; ?>

<?php foreach ($docs as $d):
    $s = $d['student']; $total = count($d['pages']); ?>
    <?php foreach ($d['pages'] as $pg): ?>
    <section class="sheet" aria-label="<?= e($d['title'] . ' — ' . $s['name'] . ' — halaman ' . $pg['number']) ?>">
        <div class="rekap-form<?= $p['rows'] > 30 ? ' dense' : '' ?>" style="--rh: <?= e(P::rowHeight($p['rows'])) ?>mm">
            <h1 class="rekap-title"><span><?= e($d['title']) ?></span></h1>
            <dl class="rekap-ident">
                <div><dt>NO. URUT</dt><dd>:</dd><dd class="v"><?= e($s['no_urut'] ?? '') ?></dd></div>
                <div><dt>NAMA</dt><dd>:</dd><dd class="v"><?= e($s['name']) ?></dd></div>
                <div><dt>KELAS</dt><dd>:</dd><dd class="v"><?= e($s['kelas'] ?? '') ?></dd></div>
            </dl>
            <table class="rekap-table">
                <colgroup><col class="c-no"><col class="c-tgl"><col class="c-ket"><col class="c-masuk"><col class="c-keluar"><col class="c-total"></colgroup>
                <thead><tr><th>NO</th><th>TGL</th><th>KETERANGAN</th><th>MASUK</th><th>KELUAR</th><th>TOTAL TABUNGAN</th></tr></thead>
                <tbody>
                <?php foreach ($pg['lines'] as $ln):
                    $isNote = in_array($ln['type'], ['open', 'carry'], true);
                    $saldoCell = $ln['type'] === 'blank' ? ($pg['total'] === 0 ? '-' : '') : P::num($ln['saldo'], true); ?>
                    <tr class="<?= e($ln['type']) ?>">
                        <td class="no"><?= e($ln['no'] ?? '') ?></td>
                        <td class="tgl"><?= $ln['date'] !== '' ? e(tanggal_id($ln['date'], true)) : '' ?></td>
                        <td class="ket<?= $isNote ? ' note' : '' ?>"><div class="clamp"><?= e($ln['desc']) ?></div></td>
                        <td class="num"><?= e(P::num($ln['masuk'])) ?></td>
                        <td class="num"><?= e(P::num($ln['keluar'])) ?></td>
                        <td class="num"><?= e($saldoCell) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="5">Total Tabungan</td><td class="num"><?= e(P::num($pg['total'], true)) ?></td></tr></tfoot>
            </table>
        </div>
        <?php if ($total > 1 || $p['meta']): ?>
        <div class="sheet-foot">
            <span><?= $p['meta'] ? 'Dicetak ' . e($stamp) : '' ?></span>
            <span><?= $total > 1 ? 'Halaman ' . $pg['number'] . ' / ' . $total : '' ?></span>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
<?php endforeach; ?>
</main>
