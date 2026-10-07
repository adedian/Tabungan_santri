<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\SyncState;
use PDOException;

/** Aturan bisnis master santri: validasi, normalisasi, simpan + naikkan revisi + audit (satu transaksi). */
final class StudentService
{
    public const PER_PAGE = [10, 25, 50];
    public const MSG_ALUMNI = 'Santri ini sudah lulus (alumni). Datanya dikelola di menu Tabungan Alumni.';
    public const DEFAULT_CLASSES = [
        'TK' => ['TK A', 'TK B'],
        'SD' => ['1A', '2A', '3A', '4A', '5A', '6A'],
    ];

    /** Rapikan parameter query dari klien menjadi filter yang aman. */
    public static function filtersFromQuery(array $q): array
    {
        $jenjang = in_array($q['jenjang'] ?? '', ['TK', 'SD'], true) ? $q['jenjang'] : '';
        $status  = in_array($q['status'] ?? '', ['aktif', 'nonaktif', 'semua'], true) ? $q['status'] : 'aktif';
        $sort    = in_array($q['sort'] ?? '', ['name', 'code', 'kelas', 'urut', 'saldo'], true) ? $q['sort'] : 'name';
        $per     = (int) ($q['per_page'] ?? 25);

        return [
            'q'        => mb_substr(self::squish((string) ($q['q'] ?? '')), 0, 100),
            'jenjang'  => $jenjang,
            'kelas'    => mb_substr(self::squish((string) ($q['kelas'] ?? '')), 0, 20),
            'status'   => $status,
            'sort'     => $sort,
            'dir'      => strtolower((string) ($q['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            'page'     => max(1, (int) ($q['page'] ?? 1)),
            'per_page' => in_array($per, self::PER_PAGE, true) ? $per : 25,
        ];
    }

    /** Hasil daftar + pilihan kelas untuk filter/form. */
    public static function listing(array $query): array
    {
        $rev = SyncState::revision(['students', 'savings']);
        $f   = self::filtersFromQuery($query);

        $res   = Student::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        $pages = max(1, (int) ceil($res['total'] / $f['per_page']));
        if ($f['page'] > $pages) { // halaman melebihi jumlah (mis. data berkurang): ambil halaman terakhir
            $f['page'] = $pages;
            $res = Student::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        }

        return [
            'rev'     => $rev,
            'items'   => $res['items'],
            'total'   => $res['total'],
            'page'    => $f['page'],
            'pages'   => $pages,
            'filters' => $f,
            'classes' => self::classOptions(),
        ];
    }

    /** Kelas yang sudah dipakai + saran bawaan, untuk datalist/filter. */
    public static function classOptions(): array
    {
        $used = Student::classes();
        $out  = [];
        foreach (['TK', 'SD'] as $j) {
            $all = array_values(array_unique(array_merge($used[$j], self::DEFAULT_CLASSES[$j])));
            usort($all, static fn (string $a, string $b): int => ((int) $a <=> (int) $b) ?: strnatcasecmp($a, $b));
            $out[$j] = ['used' => $used[$j], 'all' => $all];
        }
        return $out;
    }

    /** @return array{ok:bool, errors?:array<string,string>, student?:array} */
    public static function create(array $in): array
    {
        [$d, $errors] = self::clean($in, null);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        for ($try = 0; $try < 5; $try++) {
            $code = $d['student_code'] ?? Student::nextCode();
            try {
                $id = Database::transaction(function () use ($d, $code): int {
                    $id = Student::insert(array_merge($d, ['student_code' => $code, 'status' => 'aktif']));
                    SyncState::bump('students');
                    AuditLog::record('Menambahkan santri', 'Santri', $code, "{$d['name']} — {$d['jenjang']} {$d['kelas']}");
                    return $id;
                });
                return ['ok' => true, 'student' => Student::find($id)];
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000' || !str_contains($e->getMessage(), 'uq_students_code')) {
                    throw $e;
                }
                if ($d['student_code'] !== null) { // kode ditentukan pengguna & bentrok
                    return ['ok' => false, 'errors' => ['student_code' => 'ID santri sudah dipakai.']];
                }
                // kode otomatis bentrok (dua admin bersamaan): coba lagi dengan angka berikutnya
            }
        }
        throw new \RuntimeException('Gagal membuat kode santri unik.');
    }

    /** @return array{ok:bool, errors?:array<string,string>, student?:array, notfound?:bool} */
    public static function update(int $id, array $in): array
    {
        $old = Student::find($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        if ($old['status'] === 'alumni') {
            return ['ok' => false, 'message' => self::MSG_ALUMNI];
        }
        [$d, $errors] = self::clean($in, $old);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $changes = self::diff($old, $d);
        try {
            Database::transaction(function () use ($id, $d, $old, $changes): void {
                Student::update($id, $d);
                SyncState::bump('students');
                if ($changes !== []) {
                    AuditLog::record('Mengubah santri', 'Santri', $old['code'], $old['name'] . ': ' . implode('; ', $changes));
                }
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_students_nis')) {
                return ['ok' => false, 'errors' => ['nis' => 'NIS/NISN sudah dipakai santri lain.']];
            }
            throw $e;
        }
        return ['ok' => true, 'student' => Student::find($id)];
    }

    /** @return array{ok:bool, student?:array, notfound?:bool} */
    public static function setStatus(int $id, string $status): array
    {
        $old = Student::find($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        if ($old['status'] === 'alumni') {
            return ['ok' => false, 'message' => self::MSG_ALUMNI];
        }
        if ($old['status'] !== $status) {
            Database::transaction(static function () use ($id, $status, $old): void {
                Student::setStatus($id, $status);
                SyncState::bump('students');
                AuditLog::record($status === 'aktif' ? 'Mengaktifkan santri' : 'Menonaktifkan santri', 'Santri', $old['code'], $old['name']);
            });
        }
        return ['ok' => true, 'student' => Student::find($id)];
    }

    /**
     * Hapus massal santri = ARSIP (soft delete): data, riwayat kelas, dan transaksi tetap utuh di database,
     * hanya tidak tampil lagi. Santri yang masih punya saldo tidak diarsipkan (dananya tidak boleh "hilang dari layar").
     * Alumni tidak dapat diarsipkan dari sini. Tiap santri independen: yang gagal dilaporkan, yang lain tetap diproses.
     *
     * @param int[] $ids
     * @return array{ok:bool, message?:string, deleted?:int, skipped?:array<int,array{id:int,name:string,reason:string}>}
     */
    public static function bulkArchive(array $ids, array $actor): array
    {
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Pilih minimal satu santri.'];
        }
        if (count($ids) > SavingsService::BULK_MAX) {
            return ['ok' => false, 'message' => 'Maksimal ' . SavingsService::BULK_MAX . ' santri per proses.'];
        }
        sort($ids);

        return Database::transaction(static function () use ($ids, $actor): array {
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $rows = Database::fetchAll(
                "SELECT s.id, s.student_code, s.name, s.jenjang, s.kelas, s.status, COALESCE(b.saldo, 0) AS saldo
                   FROM students s LEFT JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id
                  WHERE s.id IN ({$in}) AND s.deleted_at IS NULL ORDER BY s.id FOR UPDATE",
                $ids
            );
            $found   = array_column($rows, null, 'id');
            $deleted = 0;
            $skipped = [];

            foreach ($ids as $id) {
                $r = $found[$id] ?? null;
                if ($r === null) {
                    $skipped[] = ['id' => $id, 'name' => '#' . $id, 'reason' => 'Tidak ditemukan atau sudah diarsipkan.'];
                } elseif ($r['status'] === 'alumni') {
                    $skipped[] = ['id' => $id, 'name' => $r['name'], 'reason' => 'Alumni tidak dapat dihapus dari daftar santri.'];
                } elseif ((int) $r['saldo'] !== 0) {
                    $skipped[] = ['id' => $id, 'name' => $r['name'], 'reason' => 'Masih memiliki saldo ' . rupiah((int) $r['saldo']) . '. Nonaktifkan saja bila tidak lagi menabung.'];
                } else {
                    Database::execute('UPDATE students SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL', [(int) $actor['id'], $id]);
                    AuditLog::record('Menghapus (arsip) santri', 'Santri', $r['student_code'], $r['name'] . ' — ' . $r['jenjang'] . ' ' . $r['kelas'] . ' [hapus massal]', $actor);
                    $deleted++;
                }
            }
            if ($deleted > 0) {
                SyncState::bump('students');
            }
            return ['ok' => true, 'deleted' => $deleted, 'skipped' => $skipped];
        });
    }

    /**
     * Validasi & normalisasi. $existing = null saat membuat baru.
     * @return array{0:array,1:array<string,string>}
     */
    private static function clean(array $in, ?array $existing): array
    {
        $e   = [];
        $id  = $existing['id'] ?? null;
        $str = static fn (string $k): string => self::squish((string) ($in[$k] ?? ''));

        $name = $str('name');
        if ($name === '') {
            $e['name'] = 'Nama santri wajib diisi.';
        } elseif (mb_strlen($name) > 100) {
            $e['name'] = 'Nama santri maksimal 100 karakter.';
        }

        $jenjang = strtoupper($str('jenjang'));
        if (!in_array($jenjang, ['TK', 'SD'], true)) {
            $e['jenjang'] = 'Jenjang wajib dipilih.';
        }

        $kelas = mb_strtoupper($str('kelas'));
        if ($kelas === '') {
            $e['kelas'] = 'Kelas wajib diisi.';
        } elseif (mb_strlen($kelas) > 20 || !preg_match('/^[A-Z0-9][A-Z0-9 .\-\/]*$/', $kelas)) {
            $e['kelas'] = 'Kelas hanya boleh huruf, angka, spasi, titik, atau tanda hubung (maks. 20 karakter).';
        }

        $code = null;
        if ($existing === null) {
            $raw = $str('student_code');
            if ($raw !== '') {
                if (mb_strlen($raw) > 20 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-]*$/', $raw)) {
                    $e['student_code'] = 'ID santri hanya boleh huruf, angka, titik, garis bawah, atau tanda hubung (maks. 20).';
                } elseif (Student::codeExists($raw)) {
                    $e['student_code'] = 'ID santri sudah dipakai.';
                } else {
                    $code = $raw;
                }
            }
        }

        $nis = $str('nis');
        if ($nis !== '') {
            if (mb_strlen($nis) > 30 || !preg_match('/^[A-Za-z0-9._\-\/]+$/', $nis)) {
                $e['nis'] = 'NIS/NISN hanya boleh huruf, angka, atau tanda hubung (maks. 30).';
            } elseif (Student::nisExists($nis, $id)) {
                $e['nis'] = 'NIS/NISN sudah dipakai santri lain.';
            }
        }

        $urutRaw = $str('no_urut');
        $urut    = null;
        if ($urutRaw !== '') {
            if (!ctype_digit($urutRaw) || (int) $urutRaw < 1 || (int) $urutRaw > 9999) {
                $e['no_urut'] = 'No. urut harus angka 1–9999.';
            } else {
                $urut = (int) $urutRaw;
            }
        }

        $dawis = $str('dawis_blok');
        if (mb_strlen($dawis) > 100) {
            $e['dawis_blok'] = 'Dawis/Blok maksimal 100 karakter.';
        }

        $status = $existing['status'] ?? 'aktif';
        if ($existing !== null && isset($in['status'])) {
            if (!in_array($in['status'], ['aktif', 'nonaktif'], true)) {
                $e['status'] = 'Status tidak valid.';
            } else {
                $status = (string) $in['status'];
            }
        }

        if (!isset($e['name']) && !isset($e['jenjang']) && !isset($e['kelas'])
            && Student::duplicateExists($name, $jenjang, $kelas, $id)) {
            $e['name'] = 'Santri dengan nama, jenjang, dan kelas yang sama sudah ada. Bedakan nama (mis. tambahkan inisial) bila memang berbeda orang.';
        }

        return [[
            'student_code' => $code,
            'nis'          => $nis === '' ? null : $nis,
            'no_urut'      => $urut,
            'name'         => $name,
            'jenjang'      => $jenjang,
            'kelas'        => $kelas,
            'dawis_blok'   => $dawis === '' ? null : $dawis,
            'status'       => $status,
        ], $e];
    }

    /** Ringkasan perubahan untuk audit log. */
    private static function diff(array $old, array $new): array
    {
        $labels = ['name' => 'Nama', 'jenjang' => 'Jenjang', 'kelas' => 'Kelas', 'nis' => 'NIS', 'no_urut' => 'No. urut', 'dawis_blok' => 'Dawis/Blok', 'status' => 'Status'];
        $out = [];
        foreach ($labels as $k => $label) {
            if ((string) ($old[$k] ?? '') !== (string) ($new[$k] ?? '')) {
                $out[] = $label . ': ' . (($old[$k] ?? '') === '' || $old[$k] === null ? '-' : $old[$k]) . ' → ' . (($new[$k] ?? '') === '' || $new[$k] === null ? '-' : $new[$k]);
            }
        }
        return $out;
    }

    private static function squish(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
