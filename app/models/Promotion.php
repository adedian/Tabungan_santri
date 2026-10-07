<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Kenaikan kelas: batch per kelas per tahun ajaran + riwayat kelas per santri. */
final class Promotion
{
    /** Kelas aktif yang punya santri aktif (untuk pilihan "Kelas Asal"), urut jenjang & kelas alami. @return array{TK:string[],SD:string[]} */
    public static function classesWithStudents(): array
    {
        $out  = ['TK' => [], 'SD' => []];
        $rows = Database::fetchAll(
            "SELECT DISTINCT jenjang, kelas FROM students
              WHERE status = 'aktif' AND deleted_at IS NULL
              ORDER BY jenjang, CAST(kelas AS UNSIGNED), kelas"
        );
        foreach ($rows as $r) {
            $out[$r['jenjang']][] = $r['kelas'];
        }
        return $out;
    }

    public static function findBatch(string $fromYear, string $jenjang, string $kelas): ?array
    {
        return Database::fetchOne(
            'SELECT p.*, u.name AS processed_by_name
               FROM class_promotions p JOIN users u ON u.id = p.processed_by
              WHERE p.from_year = ? AND p.jenjang_asal = ? AND p.kelas_asal = ? LIMIT 1',
            [$fromYear, $jenjang, $kelas]
        );
    }

    /**
     * Santri yang masih harus diproses: aktif, di kelas itu, dan BELUM punya riwayat pada tahun ajaran asal tersebut
     * (santri yang baru pindah ke kelas ini lewat kenaikan tahun yang sama tidak ikut dinaikkan dua kali).
     * @param bool $lock kunci baris (FOR UPDATE) — hanya di dalam transaksi proses
     */
    public static function eligible(string $fromYear, string $jenjang, string $kelas, bool $lock = false): array
    {
        return Database::fetchAll(
            "SELECT s.id, s.student_code, s.nis, s.no_urut, s.name, s.jenjang, s.kelas, COALESCE(b.saldo, 0) AS saldo
               FROM students s LEFT JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id
              WHERE s.status = 'aktif' AND s.deleted_at IS NULL AND s.jenjang = ? AND s.kelas = ?
                AND NOT EXISTS (SELECT 1 FROM student_class_history h WHERE h.student_id = s.id AND h.from_year = ?)
              ORDER BY s.no_urut IS NULL, s.no_urut, s.name, s.id" . ($lock ? ' FOR UPDATE' : ''),
            [$jenjang, $kelas, $fromYear]
        );
    }

    /** Jumlah santri nonaktif di kelas itu (tidak ikut proses; hanya untuk catatan di layar). */
    public static function inactiveCount(string $jenjang, string $kelas): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM students WHERE status = 'nonaktif' AND deleted_at IS NULL AND jenjang = ? AND kelas = ?",
            [$jenjang, $kelas]
        );
    }

    public static function insertBatch(array $d): int
    {
        Database::execute(
            'INSERT INTO class_promotions
               (from_year, to_year, jenjang_asal, kelas_asal, jenjang_tujuan, kelas_tujuan, count_naik, count_tinggal, count_lulus, processed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['from_year'], $d['to_year'], $d['jenjang'], $d['kelas'], $d['target_jenjang'], $d['target_kelas'],
             $d['naik'], $d['tinggal'], $d['lulus'], $d['by']]
        );
        return Database::lastInsertId();
    }

    public static function insertHistory(array $d): void
    {
        Database::execute(
            'INSERT INTO student_class_history
               (student_id, promotion_id, from_year, academic_year, previous_jenjang, previous_class, new_jenjang, new_class, promotion_status, processed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['student_id'], $d['promotion_id'], $d['from_year'], $d['to_year'], $d['prev_jenjang'], $d['prev_class'],
             $d['new_jenjang'], $d['new_class'], $d['status'], $d['by']]
        );
    }

    /** Naik kelas: pindahkan kelas/jenjang aktif santri. */
    public static function moveStudent(int $id, string $jenjang, string $kelas): void
    {
        Database::execute('UPDATE students SET jenjang = ?, kelas = ? WHERE id = ?', [$jenjang, $kelas, $id]);
    }

    /** Lulus: ubah menjadi alumni. Jenjang & kelas TIDAK diubah (menjadi "kelas terakhir"). */
    public static function graduate(int $id, int $graduationYear, string $academicYear): void
    {
        Database::execute(
            "UPDATE students SET status = 'alumni', graduated_at = CURDATE(), graduation_year = ?, graduation_academic_year = ? WHERE id = ?",
            [$graduationYear, $academicYear, $id]
        );
    }

    /** Riwayat kelas satu santri, terlama dulu. */
    public static function history(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT h.id, h.from_year, h.academic_year, h.previous_jenjang, h.previous_class, h.new_jenjang, h.new_class,
                    h.promotion_status, h.processed_at, u.name AS processed_by_name
               FROM student_class_history h JOIN users u ON u.id = h.processed_by
              WHERE h.student_id = ? ORDER BY h.processed_at ASC, h.id ASC',
            [$studentId]
        );
    }

    /** Proses terbaru (untuk tabel "Riwayat Proses"). */
    public static function recentBatches(int $limit): array
    {
        return Database::fetchAll(
            'SELECT p.id, p.from_year, p.to_year, p.jenjang_asal, p.kelas_asal, p.jenjang_tujuan, p.kelas_tujuan,
                    p.count_naik, p.count_tinggal, p.count_lulus, p.processed_at, u.name AS processed_by_name
               FROM class_promotions p JOIN users u ON u.id = p.processed_by
              ORDER BY p.processed_at DESC, p.id DESC LIMIT ?',
            [$limit]
        );
    }
}
