<?php
declare(strict_types=1);

namespace App\Services\Export;

/** CSV RFC 4180: pemisah koma, UTF-8 dengan BOM (agar Excel membaca huruf dengan benar), akhir baris CRLF. */
final class CsvWriter
{
    public const MIME = 'text/csv; charset=utf-8';
    public const EXT = 'csv';

    public static function render(TableExport $t): string
    {
        $out = "\xEF\xBB\xBF" . self::line(array_map(static fn (array $c): string => $c['label'], $t->columns));
        foreach ($t->rows as $row) {
            $out .= self::line(self::values($t, $row));
        }
        if ($t->totals !== null) {
            $out .= self::line(self::values($t, $t->totals));
        }
        return $out;
    }

    /** @return array<int,string|int> */
    private static function values(TableExport $t, array $row): array
    {
        $cells = [];
        foreach ($t->columns as $c) {
            $v = $row[$c['key']] ?? null;
            $cells[] = ($c['type'] === 'int' || $c['type'] === 'money') && $v !== null && $v !== '' ? (int) $v : (string) ($v ?? '');
        }
        return $cells;
    }

    /** @param array<int,string|int> $cells */
    private static function line(array $cells): string
    {
        return implode(',', array_map([self::class, 'cell'], $cells)) . "\r\n";
    }

    private static function cell(string|int $v): string
    {
        if (is_int($v)) {
            return (string) $v; // angka sungguhan (boleh negatif): tidak disanitasi
        }
        // Cegah "CSV/formula injection": teks yang diawali = + - @ tab/CR dianggap rumus oleh Excel.
        if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
            $v = "'" . $v;
        }
        return preg_match('/[",\r\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
    }
}
