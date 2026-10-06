<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Agregasi saldo/mutasi per kelas untuk Dashboard — semua dihitung di database (SUM/GROUP BY), bukan di PHP.
 *
 * Aturan (penting agar laporan tidak menyesatkan):
 *  - Sumber: ledger savings_transactions, hanya deleted_at IS NULL (transaksi soft-delete tidak pernah dihitung).
 *  - MUTASI (masuk/keluar) dikelompokkan menurut kelas SAAT TRANSAKSI (snapshot jenjang+kelas di baris transaksi),
 *    jadi transaksi lama tidak "pindah kelas" hanya karena master santri berubah.
 *  - SALDO pada suatu tanggal dikelompokkan menurut kelas santri PADA TANGGAL ITU: peristiwa terakhir antara
 *    (a) transaksi terakhir ≤ tanggal itu (snapshot kelasnya) dan (b) kenaikan kelas terakhir ≤ tanggal itu (student_class_history).
 *  - Alumni tidak dihitung sebagai kelas aktif sejak tanggal lulus (students.graduated_at); sebelum itu mereka masih
 *    terhitung di kelasnya, sehingga laporan periode lampau tidak berubah.
 *  - Selisih antara keduanya = "Pindah kelas" (kenaikan/kelulusan): Awal + Masuk − Keluar ± Pindah = Akhir.
 */
final class ClassBalance
{
    /**
     * Saldo per kelas pada AKHIR tanggal $date.
     * @return array<string,int> kunci "JENJANG|KELAS" => saldo
     */
    public static function balancesAsOf(string $date): array
    {
        $next = (new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
        $rows = Database::fetchAll(
            "SELECT z.jenjang, z.kelas, SUM(z.saldo) AS saldo FROM (
                SELECT sal.saldo,
                       CASE WHEN lh.pdate IS NOT NULL AND lh.pdate >= lt.transaction_date THEN lh.new_jenjang ELSE lt.jenjang END AS jenjang,
                       CASE WHEN lh.pdate IS NOT NULL AND lh.pdate >= lt.transaction_date THEN lh.new_class   ELSE lt.kelas   END AS kelas
                  FROM (SELECT x.student_id,
                               SUM(CASE WHEN x.mutation_type = 'masuk' THEN x.amount ELSE -x.amount END) AS saldo,
                               (SELECT t.id FROM savings_transactions t
                                 WHERE t.student_id = x.student_id AND t.deleted_at IS NULL AND t.transaction_date <= ?
                                 ORDER BY t.transaction_date DESC, t.id DESC LIMIT 1) AS last_id
                          FROM savings_transactions x
                         WHERE x.deleted_at IS NULL AND x.transaction_date <= ?
                         GROUP BY x.student_id) sal
                  JOIN students s ON s.id = sal.student_id
                  JOIN savings_transactions lt ON lt.id = sal.last_id
                  LEFT JOIN (SELECT student_id, new_jenjang, new_class, pdate FROM (
                            SELECT h.student_id, h.new_jenjang, h.new_class, DATE(h.processed_at) AS pdate,
                                   ROW_NUMBER() OVER (PARTITION BY h.student_id ORDER BY h.processed_at DESC, h.id DESC) AS rn
                              FROM student_class_history h
                             WHERE h.processed_at < ? AND h.new_class IS NOT NULL) b
                         WHERE b.rn = 1) lh ON lh.student_id = sal.student_id
                 WHERE NOT (s.status = 'alumni' AND (s.graduated_at IS NULL OR s.graduated_at <= ?))
             ) z
             GROUP BY z.jenjang, z.kelas",
            [$date, $date, $next, $date]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[self::key($r['jenjang'], $r['kelas'])] = (int) $r['saldo'];
        }
        return $out;
    }

    /**
     * Mutasi pada rentang tanggal [from, to] per kelas SAAT TRANSAKSI. Transaksi alumni setelah tanggal lulus tidak ikut.
     * @return array<string,array{masuk:int,keluar:int,n:int}>
     */
    public static function mutations(string $from, string $to): array
    {
        $rows = Database::fetchAll(
            "SELECT t.jenjang, t.kelas,
                    SUM(CASE WHEN t.mutation_type = 'masuk'  THEN t.amount ELSE 0 END) AS masuk,
                    SUM(CASE WHEN t.mutation_type = 'keluar' THEN t.amount ELSE 0 END) AS keluar,
                    COUNT(*) AS n
               FROM savings_transactions t JOIN students s ON s.id = t.student_id
              WHERE t.deleted_at IS NULL AND t.transaction_date BETWEEN ? AND ?
                AND NOT (s.status = 'alumni' AND (s.graduated_at IS NULL OR s.graduated_at < t.transaction_date))
              GROUP BY t.jenjang, t.kelas",
            [$from, $to]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[self::key($r['jenjang'], $r['kelas'])] = ['masuk' => (int) $r['masuk'], 'keluar' => (int) $r['keluar'], 'n' => (int) $r['n']];
        }
        return $out;
    }

    /** Kelas yang dipakai santri non-alumni saat ini (agar kelas tanpa transaksi tetap tampil dengan Rp 0). @return array<int,array{0:string,1:string}> */
    public static function activeClasses(): array
    {
        $rows = Database::fetchAll(
            "SELECT DISTINCT jenjang, kelas FROM students WHERE deleted_at IS NULL AND status <> 'alumni'"
        );
        return array_map(static fn (array $r): array => [$r['jenjang'], $r['kelas']], $rows);
    }

    /** Tahun transaksi paling awal (untuk pilihan tahun), null bila belum ada transaksi. */
    public static function firstYear(): ?int
    {
        $y = Database::fetchValue('SELECT MIN(YEAR(transaction_date)) FROM savings_transactions WHERE deleted_at IS NULL');
        return $y === null ? null : (int) $y;
    }

    public static function key(string $jenjang, string $kelas): string
    {
        return $jenjang . '|' . $kelas;
    }
}
