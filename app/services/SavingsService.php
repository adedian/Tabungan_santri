<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\Savings;
use App\Models\Student;
use App\Models\SyncState;
use DateTimeImmutable;
use RuntimeException;

/** Pelanggaran aturan bisnis di dalam transaksi DB: memicu rollback, lalu diubah menjadi respons 422. */
final class SavingsRuleException extends RuntimeException
{
    public function __construct(public array $errors, string $message, public array $extra = [])
    {
        parent::__construct($message);
    }
}

/** Aturan bisnis transaksi tabungan. Saldo selalu dari ledger; tidak ada kolom saldo. */
final class SavingsService
{
    public const MAX_AMOUNT = 1_000_000_000; // Rp 1 miliar per transaksi (batas kewajaran, mencegah salah ketik)
    public const MSG_INSUFFICIENT = 'Saldo santri tidak mencukupi.';

    /**
     * @param array $actor ['id' => int, 'name' => string]
     * @return array{ok:bool, errors?:array<string,string>, message?:string, saldo?:int, transaction?:array, student?:array}
     */
    public static function create(array $in, array $actor): array
    {
        [$d, $errors] = self::clean($in);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'message' => 'Periksa kembali isian Anda.'];
        }

        try {
            $id = Database::transaction(function () use ($d, $actor): int {
                $student = Savings::lockStudent($d['student_id']); // serialisasi per santri
                if ($student === null) {
                    throw new SavingsRuleException(['student_id' => 'Santri tidak ditemukan.'], 'Santri tidak ditemukan.');
                }
                if ($student['status'] !== 'aktif') {
                    throw new SavingsRuleException(['student_id' => 'Santri nonaktif tidak dapat menerima transaksi.'], 'Santri nonaktif tidak dapat menerima transaksi.');
                }

                if ($d['mutation'] === 'keluar') {
                    $saldo = Savings::balance($d['student_id']);
                    if ($saldo < $d['amount']) {
                        throw new SavingsRuleException(['amount' => self::MSG_INSUFFICIENT], self::MSG_INSUFFICIENT, ['saldo' => $saldo]);
                    }
                }

                $d['code']       = Savings::nextCode($d['date']);
                $d['created_by'] = $actor['id'];
                $id = Savings::insert($d);

                // Transaksi bertanggal mundur tidak boleh membuat saldo negatif di titik waktu mana pun.
                if (Savings::minRunningBalance($d['student_id']) < 0) {
                    throw new SavingsRuleException(
                        ['transaction_date' => 'Dengan tanggal ini, saldo santri menjadi negatif pada tanggal setelahnya. Periksa kembali tanggal transaksi.'],
                        self::MSG_INSUFFICIENT
                    );
                }

                SyncState::bump('savings');
                AuditLog::record(
                    'Menambahkan transaksi', 'Tabungan', $d['code'],
                    $student['name'] . ' — ' . ($d['mutation'] === 'masuk' ? 'Masuk ' : 'Keluar ') . rupiah($d['amount'])
                    . ' — ' . mb_substr($d['description'], 0, 120),
                    $actor
                );
                return $id;
            });
        } catch (SavingsRuleException $e) {
            return ['ok' => false, 'errors' => $e->errors, 'message' => $e->getMessage()] + ($e->extra ?: []);
        }

        return [
            'ok'          => true,
            'transaction' => Savings::find($id),
            'summary'     => Savings::studentSummary($d['student_id']),
        ];
    }

    public const PER_PAGE = [10, 25, 50];

    /** Parameter query klien -> filter aman (nilai tak sah diabaikan, bukan error). */
    public static function filtersFromQuery(array $q): array
    {
        $date = static function (mixed $v): string {
            $v = is_string($v) ? trim($v) : '';
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : '';
        };
        $from = $date($q['from'] ?? '');
        $to   = $date($q['to'] ?? '');
        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $month = (int) ($q['month'] ?? 0);
        $year  = (int) ($q['year'] ?? 0);
        $sort  = in_array($q['sort'] ?? '', ['date', 'name', 'jenjang', 'kelas', 'month', 'mutation', 'amount'], true) ? (string) $q['sort'] : 'date';
        $dir   = strtolower((string) ($q['dir'] ?? ($sort === 'date' ? 'desc' : 'asc'))) === 'desc' ? 'desc' : 'asc';
        $per   = (int) ($q['per_page'] ?? 25);

        return [
            'q'          => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($q['q'] ?? ''))), 0, 100),
            'jenjang'    => in_array($q['jenjang'] ?? '', ['TK', 'SD'], true) ? (string) $q['jenjang'] : '',
            'kelas'      => mb_substr(mb_strtoupper(trim((string) ($q['kelas'] ?? ''))), 0, 20),
            'month'      => ($month >= 1 && $month <= 12) ? $month : 0,
            'year'       => ($year >= 2000 && $year <= 2100) ? $year : 0,
            'mutation'   => in_array($q['mutation'] ?? '', ['masuk', 'keluar'], true) ? (string) $q['mutation'] : '',
            'from'       => $from,
            'to'         => $to,
            'student_id' => max(0, (int) ($q['student_id'] ?? 0)),
            'sort'       => $sort,
            'dir'        => $dir,
            'page'       => max(1, (int) ($q['page'] ?? 1)),
            'per_page'   => in_array($per, self::PER_PAGE, true) ? $per : 25,
        ];
    }

    /** Profil santri + ringkasan saldo untuk halaman Detail Tabungan. null bila santri tidak ada. */
    public static function studentProfile(int $id): ?array
    {
        $rev = SyncState::revision(['savings', 'students']);
        $s   = Student::find($id);
        if ($s === null) {
            return null;
        }
        return [
            'rev'     => $rev,
            'student' => $s,
            'summary' => Savings::studentSummary($id) + Savings::studentDates($id),
        ];
    }

    /** Daftar riwayat + ringkasan + pilihan filter. */
    public static function listing(array $query): array
    {
        $rev = SyncState::revision(['savings', 'students']);
        $f   = self::filtersFromQuery($query);

        $res   = Savings::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        $pages = max(1, (int) ceil($res['total'] / $f['per_page']));
        if ($f['page'] > $pages) {
            $f['page'] = $pages;
            $res = Savings::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        }

        $st = $f['student_id'] > 0 ? Student::find($f['student_id']) : null;
        return [
            'rev'     => $rev,
            'items'   => $res['items'],
            'total'   => $res['total'],
            'summary' => $res['summary'],
            'page'    => $f['page'],
            'pages'   => $pages,
            'filters' => $f,
            'options' => Savings::filterOptions(),
            'student' => $st ? ['id' => $st['id'], 'name' => $st['name'], 'label' => $st['label'], 'jenjang' => $st['jenjang'], 'kelas' => $st['kelas'], 'code' => $st['code'], 'saldo' => $st['saldo']] : null,
        ];
    }

    /**
     * Ubah transaksi. Santri tetap; pindah santri = hapus lalu buat baru. Kode transaksi tidak berubah.
     * Perubahan tidak boleh membuat saldo santri negatif di titik waktu mana pun.
     * @return array{ok:bool, notfound?:bool, errors?:array<string,string>, message?:string, transaction?:array, summary?:array}
     */
    public static function update(int $id, array $in, array $actor): array
    {
        $old = Savings::findRaw($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        [$d, $errors] = self::clean(['student_id' => $old['student_id']] + $in);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'message' => 'Periksa kembali isian Anda.'];
        }

        try {
            Database::transaction(function () use ($id, $d, $old, $actor): void {
                Savings::lockStudent((int) $old['student_id']);
                Savings::updateRow($id, $d, (int) $actor['id']);

                if (($neg = Savings::firstNegativeDate((int) $old['student_id'])) !== null) {
                    $msg = 'Perubahan ini membuat saldo santri negatif pada ' . tanggal_id($neg, true) . '.';
                    throw new SavingsRuleException(['amount' => $msg], $msg);
                }

                SyncState::bump('savings');
                $changes = self::diff($old, $d);
                AuditLog::record('Mengubah transaksi', 'Tabungan', $old['transaction_code'],
                    $old['student_name'] . ': ' . ($changes ? implode('; ', $changes) : 'tanpa perubahan data'), $actor);
            });
        } catch (SavingsRuleException $e) {
            return ['ok' => false, 'errors' => $e->errors, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'transaction' => Savings::find($id), 'summary' => Savings::studentSummary((int) $old['student_id'])];
    }

    /**
     * Hapus (soft delete) transaksi. Ditolak bila penghapusan membuat saldo negatif
     * (mis. menghapus setoran yang sudah dipakai penarikan setelahnya).
     * @return array{ok:bool, notfound?:bool, message?:string, summary?:array}
     */
    public static function delete(int $id, array $actor): array
    {
        $old = Savings::findRaw($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        try {
            Database::transaction(function () use ($id, $old, $actor): void {
                Savings::lockStudent((int) $old['student_id']);
                Savings::softDelete($id, (int) $actor['id']);

                if (($neg = Savings::firstNegativeDate((int) $old['student_id'])) !== null) {
                    throw new SavingsRuleException([], 'Transaksi tidak dapat dihapus karena saldo santri menjadi negatif pada '
                        . tanggal_id($neg, true) . '. Ubah atau hapus transaksi keluar setelahnya terlebih dahulu.');
                }

                SyncState::bump('savings');
                AuditLog::record('Menghapus transaksi', 'Tabungan', $old['transaction_code'],
                    $old['student_name'] . ' — ' . ($old['mutation_type'] === 'masuk' ? 'Masuk ' : 'Keluar ') . rupiah((int) $old['amount'])
                    . ' (' . tanggal_id($old['transaction_date'], true) . ') — ' . mb_substr((string) $old['description'], 0, 120), $actor);
            });
        } catch (SavingsRuleException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        return ['ok' => true, 'summary' => Savings::studentSummary((int) $old['student_id'])];
    }

    /** Ringkasan perubahan untuk audit log. */
    private static function diff(array $old, array $new): array
    {
        $out = [];
        $cmp = [
            'Tanggal'    => [$old['transaction_date'], $new['date'], static fn ($v) => tanggal_id((string) $v, true)],
            'Bulan'      => [bulan_nama((int) $old['period_month']) . ' ' . $old['period_year'], bulan_nama($new['period_month']) . ' ' . $new['period_year'], null],
            'Jenjang'    => [$old['jenjang'], $new['jenjang'], null],
            'Kelas'      => [$old['kelas'], $new['kelas'], null],
            'Mutasi'     => [$old['mutation_type'], $new['mutation'], null],
            'Nominal'    => [(int) $old['amount'], $new['amount'], static fn ($v) => rupiah((int) $v)],
            'Keterangan' => [$old['description'], $new['description'], static fn ($v) => '"' . mb_substr((string) $v, 0, 60) . '"'],
        ];
        foreach ($cmp as $label => [$a, $b, $fmt]) {
            if ((string) $a !== (string) $b) {
                $out[] = $label . ': ' . ($fmt ? $fmt($a) : $a) . ' → ' . ($fmt ? $fmt($b) : $b);
            }
        }
        return $out;
    }

    /**
     * Validasi & normalisasi. Mengembalikan [data, errors].
     * @return array{0:array,1:array<string,string>}
     */
    private static function clean(array $in): array
    {
        $e = [];
        $squish = static fn (mixed $v): string => trim((string) preg_replace('/\s+/u', ' ', (string) $v));

        $studentId = $in['student_id'] ?? null;
        if (!is_numeric($studentId) || (int) $studentId < 1) {
            $e['student_id'] = 'Nama santri wajib dipilih.';
        }

        $date = $squish($in['transaction_date'] ?? '');
        $dt   = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($date === '') {
            $e['transaction_date'] = 'Tanggal wajib diisi.';
        } elseif (!$dt || $dt->format('Y-m-d') !== $date) {
            $e['transaction_date'] = 'Tanggal tidak valid.';
        } elseif ($date > date('Y-m-d')) {
            $e['transaction_date'] = 'Tanggal tidak boleh melewati hari ini.';
        } elseif ($date < '2000-01-01') {
            $e['transaction_date'] = 'Tanggal terlalu lama.';
        }

        $jenjang = strtoupper($squish($in['jenjang'] ?? ''));
        if (!in_array($jenjang, ['TK', 'SD'], true)) {
            $e['jenjang'] = 'Jenjang wajib dipilih.';
        }

        $kelas = mb_strtoupper($squish($in['kelas'] ?? ''));
        if ($kelas === '') {
            $e['kelas'] = 'Kelas wajib diisi.';
        } elseif (mb_strlen($kelas) > 20 || !preg_match('/^[A-Z0-9][A-Z0-9 .\-\/]*$/', $kelas)) {
            $e['kelas'] = 'Kelas tidak valid.';
        }

        $month = $in['period_month'] ?? null;
        if (!is_numeric($month) || (int) $month < 1 || (int) $month > 12 || (string) (int) $month !== (string) $month) {
            $e['period_month'] = 'Bulan wajib dipilih.';
        }

        $mutation = $squish($in['mutation_type'] ?? '');
        if (!in_array($mutation, ['masuk', 'keluar'], true)) {
            $e['mutation_type'] = 'Mutasi wajib dipilih.';
        }

        $amount = self::parseAmount($in['amount'] ?? '');
        if ($amount === null || $amount < 1) {
            $e['amount'] = 'Nominal harus lebih dari 0.';
        } elseif ($amount > self::MAX_AMOUNT) {
            $e['amount'] = 'Nominal melebihi batas Rp ' . number_format(self::MAX_AMOUNT, 0, ',', '.') . ' per transaksi.';
        }

        $desc = $squish($in['description'] ?? '');
        $label = $mutation === 'keluar' ? 'Keterangan keluar' : 'Keterangan masuk';
        if ($desc === '') {
            $e['description'] = $label . ' wajib diisi.';
        } elseif (mb_strlen($desc) > 255) {
            $e['description'] = $label . ' maksimal 255 karakter.';
        }

        $periodYear = ($dt && !isset($e['period_month'])) ? self::periodYear((int) $month, $dt) : 0;

        return [[
            'student_id'   => (int) $studentId,
            'date'         => $date,
            'jenjang'      => $jenjang,
            'kelas'        => $kelas,
            'period_month' => (int) $month,
            'period_year'  => $periodYear,
            'mutation'     => $mutation,
            'amount'       => (int) $amount,
            'description'  => $desc,
        ], $e];
    }

    /** "50000", 50000, "50.000", "Rp 50.000" → 50000. Selain itu (huruf, minus, koma desimal) → null. */
    public static function parseAmount(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }
        if (!is_string($v)) {
            return null;
        }
        $s = trim($v);
        if (!preg_match('/^(?:Rp\.?\s*)?(\d{1,3}(?:\.\d{3})+|\d+)$/i', $s, $m)) {
            return null;
        }
        $digits = str_replace('.', '', $m[1]);
        return strlen($digits) > 15 ? null : (int) $digits;
    }

    /** Tahun periode = tahun terdekat dengan tanggal transaksi (Des dibayar pada Jan → tahun sebelumnya). */
    public static function periodYear(int $periodMonth, DateTimeImmutable $date): int
    {
        $y = (int) $date->format('Y');
        $base = $y * 12 + (int) $date->format('n');
        $best = $y;
        foreach ([$y - 1, $y, $y + 1] as $cand) {
            if (abs($cand * 12 + $periodMonth - $base) < abs($best * 12 + $periodMonth - $base)) {
                $best = $cand;
            }
        }
        return $best;
    }
}
