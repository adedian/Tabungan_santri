<?php
declare(strict_types=1);

namespace App\Services\Export;

use RuntimeException;
use ZipArchive;

/**
 * Penulis .xlsx minimal tanpa pustaka (Office Open XML). Satu lembar, teks inline, angka & tanggal asli,
 * judul + keterangan, header berwarna dengan filter & baris beku, baris total.
 */
final class XlsxWriter
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    public const EXT = 'xlsx';

    // Indeks gaya (cellXfs) di styles.xml
    private const S_DEFAULT = 0, S_HEADER = 1, S_MONEY = 2, S_DATE = 3, S_TITLE = 4, S_META = 5, S_TOTAL_TEXT = 6, S_TOTAL_MONEY = 7, S_INT = 8;

    public static function render(TableExport $t): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat berkas ekspor.');
        }
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($t));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($t));
        $zip->close();

        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    private static function sheet(TableExport $t): string
    {
        $cols = count($t->columns);
        $rows = '';
        $r = 1;

        $rows .= self::row($r++, [self::textCell(1, 1, $t->title, self::S_TITLE)]);
        foreach ($t->meta as $line) {
            $rows .= self::row($r++, [self::textCell(1, $r - 1, $line, self::S_META)]);
        }
        $r++; // baris kosong pemisah

        $headerRow = $r;
        $cells = [];
        foreach ($t->columns as $i => $c) {
            $cells[] = self::textCell($i + 1, $r, $c['label'], self::S_HEADER);
        }
        $rows .= self::row($r++, $cells, 30);

        $first = $r;
        foreach ($t->rows as $row) {
            $cells = [];
            foreach ($t->columns as $i => $c) {
                $cells[] = self::valueCell($i + 1, $r, $c['type'], $row[$c['key']] ?? null, false);
            }
            $rows .= self::row($r++, $cells);
        }
        $last = $r - 1;

        if ($t->totals !== null) {
            $cells = [];
            foreach ($t->columns as $i => $c) {
                $cells[] = self::valueCell($i + 1, $r, $c['type'], $t->totals[$c['key']] ?? null, true);
            }
            $rows .= self::row($r++, $cells);
        }

        $colDefs = '';
        foreach ($t->columns as $i => $c) {
            $w = (int) ($c['width'] ?? 16);
            $colDefs .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $lastCol = self::colLetter($cols);
        $filter = $last >= $first ? '<autoFilter ref="A' . $headerRow . ':' . $lastCol . $last . '"/>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $colDefs . '</cols>'
            . '<sheetData>' . $rows . '</sheetData>'
            . $filter
            . '<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape" paperSize="9" fitToHeight="0"/>'
            . '</worksheet>';
    }

    /** @param string[] $cells */
    private static function row(int $r, array $cells, ?int $height = null): string
    {
        return '<row r="' . $r . '"' . ($height ? ' ht="' . $height . '" customHeight="1"' : '') . '>' . implode('', $cells) . '</row>';
    }

    private static function textCell(int $col, int $row, string $text, int $style): string
    {
        return '<c r="' . self::colLetter($col) . $row . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc($text) . '</t></is></c>';
    }

    private static function valueCell(int $col, int $row, string $type, mixed $v, bool $total): string
    {
        $ref = self::colLetter($col) . $row;
        if ($v === null || $v === '') {
            return '<c r="' . $ref . '" s="' . ($total ? self::S_TOTAL_TEXT : self::S_DEFAULT) . '"/>';
        }
        switch ($type) {
            case 'money':
                return '<c r="' . $ref . '" s="' . ($total ? self::S_TOTAL_MONEY : self::S_MONEY) . '"><v>' . (int) $v . '</v></c>';
            case 'int':
                return '<c r="' . $ref . '" s="' . ($total ? self::S_TOTAL_MONEY : self::S_INT) . '"><v>' . (int) $v . '</v></c>';
            case 'date':
                $serial = (int) floor(strtotime((string) $v . ' 00:00:00 UTC') / 86400) + 25569; // 1970-01-01 = 25569
                return '<c r="' . $ref . '" s="' . self::S_DATE . '"><v>' . $serial . '</v></c>';
            default:
                return self::textCell($col, $row, (string) $v, $total ? self::S_TOTAL_TEXT : self::S_DEFAULT);
        }
    }

    private static function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    /** Escape XML + buang karakter kontrol yang tidak sah di XML 1.0. */
    private static function esc(string $s): string
    {
        $s = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(TableExport $t): string
    {
        $name = mb_substr((string) preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $t->sheetName), 0, 31) ?: 'Laporan';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc($name) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        // fonts: 0 normal, 1 tebal, 2 tebal besar, 3 abu-abu
        // fills: 0 none, 1 gray125 (wajib), 2 hijau muda (header), 3 krem (total)
        // borders: 0 none, 1 garis bawah tipis
        // cellXfs urutan = konstanta S_* di atas
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF5B6862"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE3EEE6"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFAF3E0"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FF14532D"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                                             // 0 default
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 1 header
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                                                       // 2 uang
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left"/></xf>'                // 3 tanggal
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                               // 4 judul
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                               // 5 keterangan
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                                                                 // 6 total (teks)
            . '<xf numFmtId="3" fontId="1" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/>'                                           // 7 total (angka)
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                                                       // 8 bilangan bulat
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
