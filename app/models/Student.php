<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Master santri. Saldo selalu dari Database::STUDENT_BALANCES (ledger), tidak pernah disimpan di sini. */
final class Student
{
    /** Kunci sort dari klien => ekspresi ORDER BY (whitelist; bukan input langsung). */
    private const SORTS = [
        'name'   => 's.name {dir}',
        'code'   => 'CAST(s.student_code AS UNSIGNED) {dir}, s.student_code {dir}',
        'kelas'  => 's.jenjang {dir}, CAST(s.kelas AS UNSIGNED) {dir}, s.kelas {dir}',
        'urut'   => 's.no_urut IS NULL, s.no_urut {dir}',
        'saldo'  => 'saldo {dir}',
    ];

    private const COLUMNS = "s.id, s.student_code, s.nis, s.no_urut, s.name, s.jenjang, s.kelas, s.dawis_blok, s.status,
                             s.graduated_at, s.graduation_year, s.graduation_academic_year,
                             COALESCE(b.saldo, 0) AS saldo, COALESCE(b.jumlah_transaksi, 0) AS transaksi";

    /** Escape karakter khusus LIKE. */
    public static function like(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    /**
     * Santri yang diarsipkan (deleted_at) TIDAK PERNAH ikut. Alumni hanya ikut bila status 'semua' dan 'with_alumni' = true.
     * @param array{q?:string,jenjang?:string,kelas?:string,status?:string,with_alumni?:bool} $f
     * @return array{0:string,1:array} [WHERE ..., params]
     */
    private static function where(array $f): array
    {
        $sql = ['s.deleted_at IS NULL'];
        $p   = [];

        // Setiap kata harus cocok dengan salah satu kolom: nama, ID, NIS, kelas, jenjang, dawis/blok.
        foreach (array_slice(preg_split('/\s+/u', trim((string) ($f['q'] ?? ''))) ?: [], 0, 5) as $tok) {
            if ($tok === '') {
                continue;
            }
            $l = self::like($tok);
            $sql[] = '(s.name LIKE ? OR s.student_code LIKE ? OR s.nis LIKE ? OR s.kelas LIKE ? OR s.jenjang = ? OR s.dawis_blok LIKE ?)';
            array_push($p, "%{$l}%", "{$l}%", "{$l}%", "{$l}%", mb_strtoupper($tok), "%{$l}%");
        }
        if (!empty($f['jenjang'])) {
            $sql[] = 's.jenjang = ?';
            $p[]   = $f['jenjang'];
        }
        if (!empty($f['kelas'])) {
            $sql[] = 's.kelas = ?';
            $p[]   = $f['kelas'];
        }
        if (!empty($f['status']) && $f['status'] !== 'semua') {
            $sql[] = 's.status = ?';
            $p[]   = $f['status'];
        } elseif (empty($f['with_alumni'])) {
            $sql[] = "s.status <> 'alumni'"; // alumni dipisahkan: tidak muncul di daftar santri
        }
        return ['WHERE ' . implode(' AND ', $sql), $p];
    }

    /** @return array{items:array,total:int} */
    public static function paginate(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $params] = self::where($f);
        $dir   = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $order = str_replace('{dir}', $dir, self::SORTS[$sort] ?? self::SORTS['name']);

        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM students s {$where}", $params);
        $rows  = Database::fetchAll(
            'SELECT ' . self::COLUMNS . "
               FROM students s LEFT JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id
               {$where}
              ORDER BY {$order}, s.id ASC
              LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, max(0, ($page - 1) * $perPage)])
        );
        return ['items' => array_map([self::class, 'present'], $rows), 'total' => $total];
    }

    /** Pencarian ringan untuk pilihan santri (form transaksi, filter). */
    public static function search(string $q, int $limit, bool $onlyActive, ?string $jenjang): array
    {
        [$where, $params] = self::where(['q' => $q, 'jenjang' => $jenjang, 'status' => $onlyActive ? 'aktif' : 'semua', 'with_alumni' => !$onlyActive]);
        $rows = Database::fetchAll(
            'SELECT ' . self::COLUMNS . "
               FROM students s LEFT JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id
               {$where}
              ORDER BY s.name ASC, s.id ASC LIMIT ?",
            array_merge($params, [$limit])
        );
        return array_map([self::class, 'present'], $rows);
    }

    public static function find(int $id): ?array
    {
        $row = Database::fetchOne(
            'SELECT ' . self::COLUMNS . '
               FROM students s LEFT JOIN ' . Database::STUDENT_BALANCES . ' b ON b.student_id = s.id
              WHERE s.id = ? AND s.deleted_at IS NULL LIMIT 1',
            [$id]
        );
        return $row ? self::present($row) : null;
    }

    /** Santri untuk cetak massal: urut jenjang, kelas (alami), no. urut (kosong terakhir), nama. */
    public static function forPrint(string $jenjang, string $kelas, string $status): array
    {
        $f = ['jenjang' => $jenjang, 'kelas' => $kelas, 'status' => $status];
        [$where, $params] = self::where($f);
        $rows = Database::fetchAll(
            "SELECT s.id, s.student_code, s.nis, s.no_urut, s.name, s.jenjang, s.kelas, s.dawis_blok, s.status, NULL AS graduated_at, NULL AS graduation_year, NULL AS graduation_academic_year, 0 AS saldo, 0 AS transaksi
               FROM students s {$where}
              ORDER BY s.jenjang, CAST(s.kelas AS UNSIGNED), s.kelas, s.no_urut IS NULL, s.no_urut, s.name, s.id",
            $params
        );
        return array_map([self::class, 'present'], $rows);
    }

    /** @return array{TK:string[],SD:string[]} kelas yang sudah dipakai, terurut alami */
    public static function classes(): array
    {
        $out = ['TK' => [], 'SD' => []];
        $rows = Database::fetchAll("SELECT DISTINCT jenjang, kelas FROM students WHERE deleted_at IS NULL AND status <> 'alumni' ORDER BY jenjang, CAST(kelas AS UNSIGNED), kelas");
        foreach ($rows as $r) {
            $out[$r['jenjang']][] = $r['kelas'];
        }
        return $out;
    }

    public static function codeExists(string $code, ?int $exceptId = null): bool
    {
        return (bool) Database::fetchValue('SELECT 1 FROM students WHERE student_code = ? AND id <> ? LIMIT 1', [$code, $exceptId ?? 0]);
    }

    public static function nisExists(string $nis, ?int $exceptId = null): bool
    {
        return (bool) Database::fetchValue('SELECT 1 FROM students WHERE nis = ? AND id <> ? LIMIT 1', [$nis, $exceptId ?? 0]);
    }

    public static function duplicateExists(string $name, string $jenjang, string $kelas, ?int $exceptId = null): bool
    {
        return (bool) Database::fetchValue(
            "SELECT 1 FROM students WHERE name = ? AND jenjang = ? AND kelas = ? AND id <> ? AND deleted_at IS NULL AND status <> 'alumni' LIMIT 1",
            [$name, $jenjang, $kelas, $exceptId ?? 0]
        );
    }

    /** Kode berikutnya: angka terbesar + 1, 3 digit (001, 002, ...). Keunikan dijaga constraint UNIQUE. */
    public static function nextCode(): string
    {
        $max = (int) Database::fetchValue("SELECT COALESCE(MAX(CAST(student_code AS UNSIGNED)), 0) FROM students WHERE student_code REGEXP '^[0-9]+$'");
        return str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function insert(array $d): int
    {
        Database::execute(
            'INSERT INTO students (student_code, nis, no_urut, name, jenjang, kelas, dawis_blok, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['student_code'], $d['nis'], $d['no_urut'], $d['name'], $d['jenjang'], $d['kelas'], $d['dawis_blok'], $d['status']]
        );
        return Database::lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::execute(
            'UPDATE students SET nis = ?, no_urut = ?, name = ?, jenjang = ?, kelas = ?, dawis_blok = ?, status = ? WHERE id = ?',
            [$d['nis'], $d['no_urut'], $d['name'], $d['jenjang'], $d['kelas'], $d['dawis_blok'], $d['status'], $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE students SET status = ? WHERE id = ?', [$status, $id]);
    }

    private static function present(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'code'       => $r['student_code'],
            'nis'        => $r['nis'],
            'no_urut'    => $r['no_urut'] === null ? null : (int) $r['no_urut'],
            'name'       => $r['name'],
            'jenjang'    => $r['jenjang'],
            'kelas'      => $r['kelas'],
            'dawis_blok' => $r['dawis_blok'],
            'status'     => $r['status'],
            'graduated_at'             => $r['graduated_at'] ?? null,
            'graduation_year'          => isset($r['graduation_year']) ? (int) $r['graduation_year'] : null,
            'graduation_academic_year' => $r['graduation_academic_year'] ?? null,
            'saldo'      => (int) $r['saldo'],
            'transaksi'  => (int) $r['transaksi'],
            'label'      => $r['name'] . ' — ' . $r['kelas'] . ' — ' . $r['jenjang'],
        ];
    }
}
