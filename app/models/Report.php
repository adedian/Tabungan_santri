<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Query laporan. Semua memakai Savings::listWhere() sehingga filter di Riwayat, Laporan, dan Ekspor
 * PASTI sama (alias: t = savings_transactions, s = students; soft delete sudah termasuk).
 */
final class Report
{
    private const SALDO = "CASE WHEN t.mutation_type = 'masuk' THEN t.amount ELSE -t.amount END";
    private const MASUK = "COALESCE(SUM(CASE WHEN t.mutation_type = 'masuk'  THEN t.amount END), 0)";
    private const KELUAR = "COALESCE(SUM(CASE WHEN t.mutation_type = 'keluar' THEN t.amount END), 0)";

    private const STUDENT_SORTS = [
        'name'   => 's.name {dir}',
        'kelas'  => 's.jenjang {dir}, CAST(s.kelas AS UNSIGNED) {dir}, s.kelas {dir}',
        'count'  => 'n {dir}',
        'masuk'  => 'masuk {dir}',
        'keluar' => 'keluar {dir}',
        'net'    => 'net {dir}',
        'saldo'  => 'saldo {dir}',
    ];

    /** @return array{count:int,students:int,masuk:int,keluar:int,net:int,first_date:?string,last_date:?string} */
    public static function summary(array $f): array
    {
        [$where, $p] = Savings::listWhere($f);
        $r = Database::fetchOne(
            'SELECT COUNT(*) AS n, COUNT(DISTINCT t.student_id) AS santri, ' . self::MASUK . ' AS masuk, ' . self::KELUAR . ' AS keluar,
                    MIN(t.transaction_date) AS first_date, MAX(t.transaction_date) AS last_date
               FROM savings_transactions t JOIN students s ON s.id = t.student_id ' . $where,
            $p
        ) ?? [];
        $masuk = (int) ($r['masuk'] ?? 0); $keluar = (int) ($r['keluar'] ?? 0);
        return [
            'count' => (int) ($r['n'] ?? 0), 'students' => (int) ($r['santri'] ?? 0),
            'masuk' => $masuk, 'keluar' => $keluar, 'net' => $masuk - $keluar,
            'first_date' => $r['first_date'] ?? null, 'last_date' => $r['last_date'] ?? null,
        ];
    }

    /** Rekap per jenjang/kelas (snapshot pada transaksi, jadi perpindahan kelas tercatat benar). */
    public static function byClass(array $f): array
    {
        [$where, $p] = Savings::listWhere($f);
        $rows = Database::fetchAll(
            'SELECT t.jenjang, t.kelas, COUNT(DISTINCT t.student_id) AS santri, COUNT(*) AS n, ' . self::MASUK . ' AS masuk, ' . self::KELUAR . ' AS keluar
               FROM savings_transactions t JOIN students s ON s.id = t.student_id ' . $where . '
              GROUP BY t.jenjang, t.kelas
              ORDER BY t.jenjang, CAST(t.kelas AS UNSIGNED), t.kelas',
            $p
        );
        return array_map(static fn (array $r): array => [
            'jenjang' => $r['jenjang'], 'kelas' => $r['kelas'], 'students' => (int) $r['santri'], 'count' => (int) $r['n'],
            'masuk' => (int) $r['masuk'], 'keluar' => (int) $r['keluar'], 'net' => (int) $r['masuk'] - (int) $r['keluar'],
        ], $rows);
    }

    /** @return array{0:string,1:array} SQL dalam (tanpa ORDER/LIMIT) & parameternya */
    private static function studentSql(array $f): array
    {
        [$where, $p] = Savings::listWhere($f);
        $to = (string) ($f['to'] ?? '');
        // Saldo akhir = saldo ledger santri sampai tanggal "Sampai" (atau saat ini), tidak terpengaruh filter mutasi/bulan.
        $saldo = "(SELECT COALESCE(SUM(CASE WHEN x.mutation_type = 'masuk' THEN x.amount ELSE -x.amount END), 0)
                     FROM savings_transactions x WHERE x.student_id = s.id AND x.deleted_at IS NULL"
               . ($to !== '' ? ' AND x.transaction_date <= ?' : '') . ')';
        $sql = 'SELECT s.id, s.student_code, s.name, s.jenjang, s.kelas, COUNT(*) AS n, ' . self::MASUK . ' AS masuk, ' . self::KELUAR . ' AS keluar,
                       (' . self::MASUK . ' - ' . self::KELUAR . ") AS net, {$saldo} AS saldo
                  FROM savings_transactions t JOIN students s ON s.id = t.student_id {$where}
                 GROUP BY s.id, s.student_code, s.name, s.jenjang, s.kelas";
        return [$sql, array_merge($to !== '' ? [$to] : [], $p)];
    }

    private static function presentStudent(array $r): array
    {
        return [
            'id' => (int) $r['id'], 'code' => $r['student_code'], 'name' => $r['name'], 'jenjang' => $r['jenjang'], 'kelas' => $r['kelas'],
            'count' => (int) $r['n'], 'masuk' => (int) $r['masuk'], 'keluar' => (int) $r['keluar'], 'net' => (int) $r['net'], 'saldo' => (int) $r['saldo'],
        ];
    }

    /** @return array{items:array,total:int,total_saldo:int} */
    public static function byStudent(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$sql, $p] = self::studentSql($f);
        $order = str_replace('{dir}', strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC', self::STUDENT_SORTS[$sort] ?? self::STUDENT_SORTS['name']);
        $agg = Database::fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(saldo), 0) AS total_saldo FROM ({$sql}) y", $p) ?? ['total' => 0, 'total_saldo' => 0];
        $rows = Database::fetchAll("{$sql} ORDER BY {$order}, s.id ASC LIMIT ? OFFSET ?", array_merge($p, [$perPage, max(0, ($page - 1) * $perPage)]));
        return ['items' => array_map([self::class, 'presentStudent'], $rows), 'total' => (int) $agg['total'], 'total_saldo' => (int) $agg['total_saldo']];
    }

    /** Semua baris rekap per santri (untuk ekspor), urut nama. */
    public static function allStudents(array $f): array
    {
        [$sql, $p] = self::studentSql($f);
        return array_map([self::class, 'presentStudent'], Database::fetchAll("{$sql} ORDER BY s.name ASC, s.id ASC", $p));
    }

    /**
     * Total masuk/keluar per periode (bucket) sesuai filter.
     * $bucketExpr dari whitelist di ReportService — BUKAN input pengguna.
     * @param array<int,string> $bucketKeys kunci tiap periode (Y-m-d awal periode), berurutan
     * @return array<string, array{masuk:int,keluar:int}>
     */
    public static function activity(array $f, string $bucketExpr, array $bucketKeys): array
    {
        [$where, $p] = Savings::listWhere($f);
        $rows = Database::fetchAll(
            "SELECT {$bucketExpr} AS bucket, " . self::MASUK . ' AS masuk, ' . self::KELUAR . ' AS keluar
               FROM savings_transactions t JOIN students s ON s.id = t.student_id ' . $where . ' GROUP BY bucket',
            $p
        );
        $out = [];
        foreach ($bucketKeys as $k) { $out[$k] = ['masuk' => 0, 'keluar' => 0]; }
        foreach ($rows as $r) {
            if (isset($out[$r['bucket']])) { $out[$r['bucket']] = ['masuk' => (int) $r['masuk'], 'keluar' => (int) $r['keluar']]; }
        }
        return $out;
    }

    /**
     * Rincian transaksi untuk ekspor, urut tanggal naik, lengkap dengan saldo santri setelah tiap transaksi
     * (saldo berjalan dihitung atas SELURUH ledger, bukan hanya baris terfilter).
     */
    public static function transactions(array $f, int $limit): array
    {
        [$where, $p] = Savings::listWhere($f);
        $rows = Database::fetchAll(
            "SELECT t.id, t.transaction_code, t.transaction_date, s.name, s.student_code, t.jenjang, t.kelas, t.period_month, t.period_year,
                    t.mutation_type, t.amount, t.description, b.rb
               FROM savings_transactions t
               JOIN students s ON s.id = t.student_id
               JOIN (SELECT id, SUM(CASE WHEN mutation_type = 'masuk' THEN amount ELSE -amount END)
                                OVER (PARTITION BY student_id ORDER BY transaction_date, id) AS rb
                       FROM savings_transactions WHERE deleted_at IS NULL) b ON b.id = t.id
               {$where}
              ORDER BY t.transaction_date ASC, t.id ASC
              LIMIT ?",
            array_merge($p, [$limit])
        );
        return $rows;
    }
}
