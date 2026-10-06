<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Savings;
use App\Models\Student;

/**
 * Data cetak "Rekap Tabungan per Nama" (mengikuti contoh Excel pengguna).
 *
 * Satu santri = satu atau lebih halaman. Setiap halaman berisi tepat N baris (transaksi + baris kosong
 * untuk tulisan tangan). Halaman lanjutan diawali baris "Saldo pindahan"; bila rentang tanggal dipakai,
 * halaman pertama diawali "Saldo awal". Kolom TOTAL TABUNGAN = saldo berjalan.
 */
final class PrintService
{
    public const ROWS = [10, 12, 15, 20, 25, 30, 40];
    public const DEFAULT_ROWS = 12;   // 11 baris bernomor + 1 kosong pada contoh Excel
    public const MAX_BATCH = 80;      // batas santri per cetak massal

    /** @return array{from:string,to:string,rows:int,meta:bool} */
    public static function params(array $q): array
    {
        $date = static function (mixed $v): string {
            $v = is_string($v) ? trim($v) : '';
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : '';
        };
        $from = $date($q['from'] ?? ''); $to = $date($q['to'] ?? '');
        if ($from !== '' && $to !== '' && $from > $to) { [$from, $to] = [$to, $from]; }
        $rows = (int) ($q['rows'] ?? self::DEFAULT_ROWS);
        return ['from' => $from, 'to' => $to, 'rows' => in_array($rows, self::ROWS, true) ? $rows : self::DEFAULT_ROWS, 'meta' => !empty($q['meta'])];
    }

    /**
     * Perkiraan jumlah baris yang dipakai keterangan (maks. 3 baris; sisanya dipotong di cetakan).
     * Kolom KETERANGAN memuat ± 32 karakter per baris (Calibri 11pt). Untuk halaman sangat padat (> 20 baris, hampir tanpa ruang sisa)
     * dipakai perkiraan lebih hati-hati (26) agar tidak meluap bila font cadangan lebih lebar.
     */
    public static function lineUnits(string $desc, int $rows = self::DEFAULT_ROWS): int
    {
        $perLine = $rows <= 20 ? 32 : 26;
        return min(3, max(1, (int) ceil(mb_strlen(trim($desc)) / $perLine)));
    }

    /** Tinggi baris (mm) agar $rows baris + judul + header + total selalu muat di satu halaman A4 (area cetak 267 mm). */
    public static function rowHeight(int $rows): float
    {
        return min(7.2, floor((250 - 47.5) / max(1, $rows) * 10) / 10);
    }

    /** Judul mengikuti jenjang: TK -> "Rekap Tabungan TK / KB", SD -> "Rekap Tabungan SD". */
    public static function title(string $jenjang): string
    {
        return $jenjang === 'TK' ? 'Rekap Tabungan TK / KB' : 'Rekap Tabungan ' . $jenjang;
    }

    /** Angka gaya akuntansi: 50.000; nol pada $dashZero tampil "-". */
    public static function num(?int $n, bool $dashZero = false): string
    {
        if ($n === null) {
            return '';
        }
        if ($n === 0) {
            return $dashZero ? '-' : '0';
        }
        return ($n < 0 ? '-' : '') . number_format(abs($n), 0, ',', '.');
    }

    /**
     * @param array $student hasil Student::find()/forPrint()
     * @return array{student:array,title:string,pages:array,count:int,opening:?int}
     */
    public static function rekap(array $student, string $from, string $to, int $rows): array
    {
        $txs     = Savings::ledger((int) $student['id'], $from, $to);
        $opening = $from !== '' ? Savings::balanceBefore((int) $student['id'], $from) : 0;
        $label   = $from !== '' ? 'Saldo awal per ' . tanggal_id($from, true) : null;

        return [
            'student' => $student,
            'title'   => self::title((string) $student['jenjang']),
            'pages'   => self::paginate($txs, $opening, $label, $rows),
            'count'   => count($txs),
            'opening' => $from !== '' ? $opening : null,
        ];
    }

    /**
     * Murni (tanpa DB) agar mudah diuji.
     *
     * @param array<int,array{transaction_date:string,mutation_type:string,amount:int|string,description:string}> $txs urut tanggal
     * @return array<int,array{number:int,total:int,lines:array}>
     */
    public static function paginate(array $txs, int $opening, ?string $openingLabel, int $rows): array
    {
        $rows  = max(3, $rows);
        $pages = [];
        $bal   = $opening;
        $no    = 0;
        $cur   = [];
        $used  = 0; // satuan baris terpakai pada halaman berjalan (keterangan panjang memakan > 1)

        if ($openingLabel !== null) {
            $cur[] = ['type' => 'open', 'no' => null, 'date' => '', 'desc' => $openingLabel, 'masuk' => null, 'keluar' => null, 'saldo' => $bal, 'units' => 1];
            $used = 1;
        }

        $close = static function () use (&$pages, &$cur, &$bal): void {
            $pages[] = ['lines' => $cur, 'total' => $bal];
        };

        foreach ($txs as $t) {
            $u = self::lineUnits((string) $t['description'], $rows);
            if ($used + $u > $rows && $used > 0) {
                $close();
                $cur  = [['type' => 'carry', 'no' => null, 'date' => '', 'desc' => 'Saldo pindahan', 'masuk' => null, 'keluar' => null, 'saldo' => $bal, 'units' => 1]];
                $used = 1;
            }
            $amount = (int) $t['amount'];
            $masuk  = $t['mutation_type'] === 'masuk';
            $bal   += $masuk ? $amount : -$amount;
            $no++;
            $cur[] = [
                'type' => 'tx', 'no' => $no, 'date' => (string) $t['transaction_date'], 'desc' => (string) $t['description'],
                'masuk' => $masuk ? $amount : null, 'keluar' => $masuk ? null : $amount, 'saldo' => $bal, 'units' => $u,
            ];
            $used += $u;
        }
        $close();

        // Baris kosong (untuk tulisan tangan) sampai penuh; bernomor lanjut, kecuali baris terakhir (seperti contoh: 11 + 1).
        $last = count($pages) - 1;
        $n = $no;
        while ($used < $rows) {
            $isLast = $used === $rows - 1;
            $pages[$last]['lines'][] = ['type' => 'blank', 'no' => $isLast ? null : ++$n, 'date' => '', 'desc' => '', 'masuk' => null, 'keluar' => null, 'saldo' => null, 'units' => 1];
            $used++;
        }
        foreach ($pages as $i => &$p) {
            $p['number'] = $i + 1;
        }
        unset($p);
        return $pages;
    }

    /** Santri untuk cetak massal (satu kelas), dengan batas aman. @return array{students:array,over:bool,total:int} */
    public static function batchStudents(string $jenjang, string $kelas, bool $includeInactive): array
    {
        $list = Student::forPrint($jenjang, $kelas, $includeInactive ? 'semua' : 'aktif');
        return ['students' => array_slice($list, 0, self::MAX_BATCH), 'over' => count($list) > self::MAX_BATCH, 'total' => count($list)];
    }
}
