<?php
declare(strict_types=1);

namespace App\Services\Export;

/**
 * Penulis .pdf minimal tanpa pustaka (PDF 1.4, font standar Helvetica — tidak perlu ditanam).
 * A4 lanskap, judul + keterangan filter di halaman pertama, header tabel diulang di tiap halaman,
 * teks panjang dibungkus (maks. 3 baris), angka rata kanan, baris total, nomor halaman "x / y".
 *
 * Kolom dengan 'pdf' => false dilewati (mis. kolom sempit yang tidak muat di kertas). Lebar kolom mengikuti 'width' relatif.
 */
final class PdfWriter
{
    public const MIME = 'application/pdf';
    /** Batas baris PDF (lebih dari ini gunakan Excel): menjaga memori & ukuran berkas. */
    public const MAX_ROWS = 10000;

    private const PW = 842.0, PH = 595.0;          // A4 lanskap (pt)
    private const MX = 28.0, MTOP = 30.0, MBOT = 34.0;
    private const FS = 7.0, LH = 9.0, PAD = 3.5, MAX_LINES = 3;

    /** Lebar glyph Helvetica / Helvetica-Bold (per 1000 em) untuk ASCII 32–126. */
    private const W_REG = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const W_BLD = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
    /** Glyph WinAnsi di atas 126 yang umum: en/em dash, bullet, kutip, elipsis, spasi keras, titik tengah. */
    private const W_EXTRA = [0x96 => 556, 0x97 => 1000, 0x95 => 350, 0x91 => 222, 0x92 => 222, 0x93 => 333, 0x94 => 333, 0x85 => 1000, 0xA0 => 278, 0xB7 => 278];

    /** @var string[] isi (content stream) tiap halaman */
    private array $pages = [];
    private string $cur = '';
    private float $y = 0.0;

    public static function render(TableExport $t, string $footer = 'Tabungan Santri'): string
    {
        $w = new self();
        return $w->build($t, $footer);
    }

    private function build(TableExport $t, string $footer): string
    {
        $cols = array_values(array_filter($t->columns, static fn (array $c): bool => ($c['pdf'] ?? true) !== false));
        $usable = self::PW - 2 * self::MX;
        $sum = max(1, array_sum(array_map(static fn (array $c): int => (int) ($c['width'] ?? 10), $cols)));
        $x = self::MX;
        $layout = [];
        foreach ($cols as $c) {
            $cw = $usable * ((int) ($c['width'] ?? 10)) / $sum;
            $layout[] = ['c' => $c, 'x' => $x, 'w' => $cw, 'right' => in_array($c['type'], ['int', 'money'], true)];
            $x += $cw;
        }

        // Halaman pertama: judul + keterangan
        $this->startPage();
        $this->text(self::MX, $this->y - 14, $t->title, 15, true, [0.08, 0.33, 0.18]);
        $this->y -= 24;
        foreach ($t->meta as $line) {
            $this->text(self::MX, $this->y - 8, $line, 8.2, false, [0.35, 0.40, 0.37]);
            $this->y -= 11.5;
        }
        $this->y -= 6;
        $this->header($layout);

        $n = 0;
        foreach ($t->rows as $row) {
            [$cells, $lines] = $this->prepare($layout, $row);
            $h = $lines * self::LH + 2 * self::PAD;
            if ($this->y - $h < self::MBOT) {
                $this->startPage();
                $this->header($layout);
            }
            if ($n % 2 === 1) {
                $this->rect(self::MX, $this->y - $h, $usable, $h, [0.955, 0.97, 0.958]);
            }
            $this->drawRow($layout, $cells, $this->y, false);
            $this->hline($this->y - $h, [0.86, 0.89, 0.87]);
            $this->y -= $h;
            $n++;
        }

        if ($t->totals !== null) {
            [$cells, $lines] = $this->prepare($layout, $t->totals, true);
            $h = $lines * self::LH + 2 * self::PAD + 2;
            if ($this->y - $h < self::MBOT) {
                $this->startPage();
                $this->header($layout);
            }
            $this->rect(self::MX, $this->y - $h, $usable, $h, [0.98, 0.953, 0.878]);
            $this->hline($this->y, [0.55, 0.60, 0.56], 0.8);
            $this->drawRow($layout, $cells, $this->y - 1, true);
            $this->y -= $h;
        }
        if ($n === 0) {
            $this->text(self::MX + self::PAD, $this->y - 14, 'Tidak ada data pada filter ini.', 8.5, false, [0.35, 0.40, 0.37]);
        }
        $this->endPage();

        return $this->assemble($t->title, $footer);
    }

    /* ---------- Baris & sel ---------- */

    private function header(array $layout): void
    {
        $cells = [];
        $max = 1;
        foreach ($layout as $L) {
            $lines = $this->wrap($this->enc($L['c']['label']), $L['w'] - 2 * self::PAD, true);
            $cells[] = $lines;
            $max = max($max, count($lines));
        }
        $h = $max * self::LH + 2 * self::PAD;
        $this->rect(self::MX, $this->y - $h, self::PW - 2 * self::MX, $h, [0.08, 0.33, 0.18]);
        foreach ($layout as $i => $L) {
            $ty = $this->y - self::PAD - self::FS;
            foreach ($cells[$i] as $line) {
                $tx = $L['right'] ? $L['x'] + $L['w'] - self::PAD - $this->width($line, true) : $L['x'] + self::PAD;
                $this->rawText($tx, $ty, $line, self::FS, true, [1, 1, 1]);
                $ty -= self::LH;
            }
        }
        $this->y -= $h;
    }

    /** @return array{0:array<int,string[]>,1:int} sel → baris teks; jumlah baris terbanyak */
    private function prepare(array $layout, array $row, bool $isTotal = false): array
    {
        $cells = [];
        $max = 1;
        foreach ($layout as $i => $L) {
            $v = $row[$L['c']['key']] ?? null;
            $s = $this->format($v, $L['c']['type']);
            $lines = $s === '' ? [] : ($L['right'] ? [$this->enc($s)] : $this->wrap($this->enc($s), $L['w'] - 2 * self::PAD, $isTotal));
            $cells[$i] = $lines;
            $max = max($max, count($lines));
        }
        return [$cells, $max];
    }

    private function drawRow(array $layout, array $cells, float $top, bool $bold): void
    {
        foreach ($layout as $i => $L) {
            $ty = $top - self::PAD - self::FS;
            foreach ($cells[$i] as $line) {
                $tx = $L['right'] ? $L['x'] + $L['w'] - self::PAD - $this->width($line, $bold) : $L['x'] + self::PAD;
                $this->rawText($tx, $ty, $line, self::FS, $bold, [0.12, 0.16, 0.22]);
                $ty -= self::LH;
            }
        }
    }

    private function format(mixed $v, string $type): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        switch ($type) {
            case 'money':
                $n = (int) $v;
                return ($n < 0 ? '-' : '') . number_format(abs($n), 0, ',', '.');
            case 'int':
                return (string) (int) $v;
            case 'date':
                return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $v, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $v;
            default:
                return (string) $v;
        }
    }

    /* ---------- Teks ---------- */

    /** UTF-8 → WinAnsi (CP1252); karakter di luar WinAnsi menjadi '?'. Karakter kontrol dibuang. */
    private function enc(string $s): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
        return (string) mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    private function charW(string $ch, bool $bold): int
    {
        $o = ord($ch);
        if ($o >= 32 && $o <= 126) {
            return ($bold ? self::W_BLD : self::W_REG)[$o - 32];
        }
        return self::W_EXTRA[$o] ?? 556;
    }

    private function width(string $s, bool $bold, float $size = self::FS): float
    {
        $w = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $w += $this->charW($s[$i], $bold);
        }
        return $w * $size / 1000;
    }

    /** Bungkus kata ke lebar $max (pt); kata terlalu panjang dipecah per huruf; lebih dari MAX_LINES dipotong dengan "...". @return string[] */
    private function wrap(string $s, float $max, bool $bold): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/ +/', trim($s)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->width($try, $bold) <= $max) {
                $line = $try;
                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            while ($this->width($word, $bold) > $max && strlen($word) > 1) {
                $cut = strlen($word) - 1;
                while ($cut > 1 && $this->width(substr($word, 0, $cut), $bold) > $max) {
                    $cut--;
                }
                $lines[] = substr($word, 0, $cut);
                $word = substr($word, $cut);
            }
            $line = $word;
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > self::MAX_LINES) {
            $lines = array_slice($lines, 0, self::MAX_LINES);
            $last = rtrim($lines[self::MAX_LINES - 1]);
            while ($last !== '' && $this->width($last . '...', $bold) > $max) {
                $last = substr($last, 0, -1);
            }
            $lines[self::MAX_LINES - 1] = $last . '...';
        }
        return $lines;
    }

    /* ---------- Primitif gambar ---------- */

    private function startPage(): void
    {
        if ($this->cur !== '') {
            $this->endPage();
        }
        $this->cur = '';
        $this->y = self::PH - self::MTOP;
    }

    private function endPage(): void
    {
        $this->pages[] = $this->cur;
        $this->cur = '';
    }

    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->cur .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }

    private function hline(float $y, array $rgb, float $lw = 0.4): void
    {
        $this->cur .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $rgb[0], $rgb[1], $rgb[2], $lw, self::MX, $y, self::PW - self::MX, $y);
    }

    /** Teks UTF-8 (dikonversi). $y = garis dasar. */
    private function text(float $x, float $y, string $s, float $size, bool $bold, array $rgb): void
    {
        $this->rawText($x, $y, $this->enc($s), $size, $bold, $rgb);
    }

    /** Teks yang sudah WinAnsi. */
    private function rawText(float $x, float $y, string $s, float $size, bool $bold, array $rgb): void
    {
        $esc = strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $this->cur .= sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, $esc);
    }

    /* ---------- Struktur berkas ---------- */

    private function assemble(string $title, string $footer): string
    {
        $count = count($this->pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $count; $i++) {
            $kids[] = (6 + 2 * $i) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $info = 5 + 2 * $count;
        $objs[5] = '<< /Title (' . $this->pdfString($title) . ') /Creator (Tabungan Santri) /Producer (Tabungan Santri) /CreationDate (D:' . date('YmdHis') . ') >>';

        foreach ($this->pages as $i => $content) {
            $this->cur = '';
            $label = 'Halaman ' . ($i + 1) . ' / ' . $count;
            $this->hline(self::MBOT - 8, [0.80, 0.84, 0.81], 0.4);
            $this->text(self::MX, self::MBOT - 20, $footer . ' — ' . $title, 7, false, [0.45, 0.50, 0.47]);
            $this->rawText(self::PW - self::MX - $this->width($this->enc($label), false), self::MBOT - 20, $this->enc($label), 7, false, [0.45, 0.50, 0.47]);
            $stream = gzcompress($content . $this->cur, 6);
            $cObj = 6 + 2 * $i + 1;
            $pObj = 6 + 2 * $i;
            $objs[$pObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $cObj . ' 0 R >>';
            $objs[$cObj] = "<< /Length " . strlen((string) $stream) . " /Filter /FlateDecode >>\nstream\n" . $stream . "\nendstream";
        }
        unset($info);

        // Urutan objek: 1..5 lalu (page, content) berpasangan sesuai nomor
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $max = max(array_keys($objs));
        $xref = strlen($out);
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $max; $n++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$n] ?? 0);
        }
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $out;
    }

    private function pdfString(string $s): string
    {
        return strtr($this->enc($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}
