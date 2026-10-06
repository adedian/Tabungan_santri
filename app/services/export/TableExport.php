<?php
declare(strict_types=1);

namespace App\Services\Export;

/**
 * Data tabel yang netral format. Penulis (CsvWriter, XlsxWriter, kelak PDF) cukup membaca objek ini.
 *
 * Tipe kolom: 'text' | 'int' | 'money' (rupiah, bilangan bulat) | 'date' (Y-m-d)
 */
final class TableExport
{
    /**
     * @param string                 $title    judul laporan
     * @param string[]               $meta     baris keterangan (periode, filter, waktu ekspor) — hanya untuk format yang mendukung
     * @param array<int,array{key:string,label:string,type:string,width?:int}> $columns
     * @param array<int,array<string,mixed>> $rows  tiap baris: key kolom => nilai
     * @param array<string,mixed>|null $totals baris total (key kolom => nilai), opsional
     */
    public function __construct(
        public string $title,
        public array $meta,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public string $sheetName = 'Laporan'
    ) {
    }
}
