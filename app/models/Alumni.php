<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Alumni = santri berstatus 'alumni'. Tidak ada tabel transaksi terpisah: transaksi tetap di ledger yang sama
 * (savings_transactions), saldo dari Database::STUDENT_BALANCES — jadi tidak mungkin terjadi hitung ganda.
 */
final class Alumni
{
    private const SORTS = [
        'name'   => 's.name {dir}',
        'year'   => 's.graduation_year {dir}, s.graduated_at {dir}, s.name ASC',
        'kelas'  => 's.jenjang {dir}, CAST(s.kelas AS UNSIGNED) {dir}, s.kelas {dir}',
        'masuk'  => 'b.total_masuk {dir}',
        'keluar' => 'b.total_keluar {dir}',
        'saldo'  => 'b.saldo {dir}',
    ];

    private const FROM = "FROM students s JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id";

    /** @return array{0:string,1:array} */
    private static function where(array $f): array
    {
        $sql = ["s.status = 'alumni'", 's.deleted_at IS NULL'];
        $p   = [];
        foreach (array_slice(preg_split('/\s+/u', trim((string) ($f['q'] ?? ''))) ?: [], 0, 5) as $tok) {
            if ($tok === '') {
                continue;
            }
            $l = Student::like($tok);
            $sql[] = '(s.name LIKE ? OR s.student_code LIKE ? OR s.nis LIKE ? OR s.kelas LIKE ?)';
            array_push($p, "%{$l}%", "{$l}%", "{$l}%", "{$l}%");
        }
        if (!empty($f['year'])) {
            $sql[] = 's.graduation_year = ?';
            $p[]   = (int) $f['year'];
        }
        if (!empty($f['academic_year'])) {
            $sql[] = 's.graduation_academic_year = ?';
            $p[]   = (string) $f['academic_year'];
        }
        return ['WHERE ' . implode(' AND ', $sql), $p];
    }

    private static function present(array $r): array
    {
        return [
            'id' => (int) $r['id'], 'code' => $r['student_code'], 'nis' => $r['nis'], 'name' => $r['name'],
            'jenjang' => $r['jenjang'], 'kelas' => $r['kelas'],
            'graduation_year' => $r['graduation_year'] === null ? null : (int) $r['graduation_year'],
            'graduated_at' => $r['graduated_at'], 'academic_year' => $r['graduation_academic_year'],
            'masuk' => (int) $r['total_masuk'], 'keluar' => (int) $r['total_keluar'], 'saldo' => (int) $r['saldo'],
            'transaksi' => (int) $r['jumlah_transaksi'],
        ];
    }

    /** @return array{items:array,total:int} */
    public static function paginate(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $p] = self::where($f);
        $order = str_replace('{dir}', strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC', self::SORTS[$sort] ?? self::SORTS['year']);
        $total = (int) Database::fetchValue('SELECT COUNT(*) ' . self::FROM . " {$where}", $p);
        $rows  = Database::fetchAll(
            'SELECT s.id, s.student_code, s.nis, s.name, s.jenjang, s.kelas, s.graduation_year, s.graduated_at, s.graduation_academic_year,
                    b.total_masuk, b.total_keluar, b.saldo, b.jumlah_transaksi ' . self::FROM . "
              {$where} ORDER BY {$order}, s.id ASC LIMIT ? OFFSET ?",
            array_merge($p, [$perPage, max(0, ($page - 1) * $perPage)])
        );
        return ['items' => array_map([self::class, 'present'], $rows), 'total' => $total];
    }

    /** Ringkasan seluruh hasil filter. @return array{alumni:int,masuk:int,keluar:int,saldo:int} */
    public static function summary(array $f): array
    {
        [$where, $p] = self::where($f);
        $r = Database::fetchOne(
            'SELECT COUNT(*) AS n, COALESCE(SUM(b.total_masuk), 0) AS masuk, COALESCE(SUM(b.total_keluar), 0) AS keluar, COALESCE(SUM(b.saldo), 0) AS saldo '
            . self::FROM . " {$where}",
            $p
        ) ?? [];
        return ['alumni' => (int) ($r['n'] ?? 0), 'masuk' => (int) ($r['masuk'] ?? 0), 'keluar' => (int) ($r['keluar'] ?? 0), 'saldo' => (int) ($r['saldo'] ?? 0)];
    }

    /**
     * Tabel rekap: per tahun lulus ('year') atau per kelas terakhir ('kelas').
     * $group berasal dari whitelist di service — bukan input langsung.
     */
    public static function recap(array $f, string $group): array
    {
        [$where, $p] = self::where($f);
        if ($group === 'kelas') {
            $cols  = 's.jenjang AS g1, s.kelas AS g2';
            $by    = 's.jenjang, s.kelas';
            $order = 's.jenjang, CAST(s.kelas AS UNSIGNED), s.kelas';
        } else {
            $cols  = 's.graduation_year AS g1, NULL AS g2';
            $by    = 's.graduation_year';
            $order = 's.graduation_year DESC';
        }
        $rows = Database::fetchAll(
            "SELECT {$cols}, COUNT(*) AS n, SUM(b.total_masuk) AS masuk, SUM(b.total_keluar) AS keluar, SUM(b.saldo) AS saldo "
            . self::FROM . " {$where} GROUP BY {$by} ORDER BY {$order}",
            $p
        );
        return array_map(static fn (array $r): array => [
            'label' => $group === 'kelas' ? $r['g1'] . ' ' . $r['g2'] : ($r['g1'] === null ? '–' : (string) $r['g1']),
            'alumni' => (int) $r['n'], 'masuk' => (int) $r['masuk'], 'keluar' => (int) $r['keluar'], 'saldo' => (int) $r['saldo'],
        ], $rows);
    }

    /** Pilihan filter yang benar-benar ada di data. @return array{years:int[],academic_years:string[]} */
    public static function options(): array
    {
        $base = "FROM students WHERE status = 'alumni' AND deleted_at IS NULL";
        return [
            'years' => array_map('intval', array_column(Database::fetchAll("SELECT DISTINCT graduation_year {$base} AND graduation_year IS NOT NULL ORDER BY graduation_year DESC"), 'graduation_year')),
            'academic_years' => array_column(Database::fetchAll("SELECT DISTINCT graduation_academic_year {$base} AND graduation_academic_year IS NOT NULL ORDER BY graduation_academic_year DESC"), 'graduation_academic_year'),
        ];
    }
}
