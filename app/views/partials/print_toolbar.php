<?php
/**
 * Bilah alat pratinjau (hanya layar). Variabel: $back [url,label], $action (URL form GET), $hidden (field tetap),
 * $p (from,to,rows,meta), $showRows (bool), $summary (teks ringkas), $extra (HTML opsional, sudah di-escape).
 */
$hidden = $hidden ?? [];
?>
<div class="print-toolbar no-print">
    <div class="print-toolbar-inner">
        <a class="btn btn-ghost btn-sm" href="<?= e(url($back[0])) ?>" data-back title="Kembali ke <?= e($back[1]) ?>"><?= icon('arrow-left') ?>Kembali</a>
        <strong class="print-toolbar-title">Pratinjau cetak</strong>
        <span class="muted small print-summary"><?= e($summary ?? '') ?></span>
        <span class="topbar-spacer"></span>
        <button type="button" class="btn btn-primary" id="btn-print"><?= icon('printer') ?>Cetak</button>
    </div>
    <form class="print-options" method="get" action="<?= e($action) ?>" id="print-options">
        <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <label>Dari <input class="input" type="date" name="from" value="<?= e($p['from']) ?>"></label>
        <label>Sampai <input class="input" type="date" name="to" value="<?= e($p['to']) ?>"></label>
        <?php if (!empty($showRows)): ?>
        <label>Baris per halaman
            <select class="select" name="rows">
                <?php foreach (\App\Services\PrintService::ROWS as $n): ?><option value="<?= $n ?>"<?= $n === $p['rows'] ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="meta" value="1"<?= $p['meta'] ? ' checked' : '' ?>> Sertakan waktu cetak</label>
        <?= $extra ?? '' ?>
        <button type="submit" class="btn btn-secondary btn-sm">Terapkan</button>
    </form>
    <p class="print-tip">Kertas <b>A4 potret</b>. Di dialog cetak pilih skala <b>100%</b> dan matikan <b>“Header dan footer”</b> (Opsi lainnya) agar hasil sama dengan pratinjau.</p>
</div>
