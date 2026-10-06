<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\Promotion;
use App\Models\SyncState;
use PDOException;
use RuntimeException;

/** Pelanggaran aturan kenaikan di dalam transaksi DB: memicu rollback, lalu menjadi respons 422/409. */
final class PromotionRuleException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}

/**
 * Kenaikan kelas: Review → pilih Naik/Tidak Naik → konfirmasi → proses (satu transaksi DB, semua atau tidak sama sekali).
 *
 *  - Naik         : kelas berikutnya (lihat ClassLadder). SD kelas 6 + Naik = LULUS → status alumni (tanpa "kelas 7").
 *  - Tidak naik   : kelas tetap; tetap tercatat di riwayat.
 *  - Transaksi tabungan TIDAK diubah/dipindah/diduplikasi: saldo tetap dari ledger yang sama.
 *  - Satu kelas hanya dapat diproses sekali per tahun ajaran asal (UNIQUE) dan satu santri sekali per tahun ajaran (UNIQUE).
 */
final class PromotionService
{
    public const MSG_DONE = 'Kenaikan kelas untuk tahun ajaran ini sudah diproses.';

    /** Pilihan layar proses: daftar tahun ajaran asal, bawaan, dan kelas yang punya santri aktif. */
    public static function options(): array
    {
        $def   = ClassLadder::defaultFromStart();
        $years = [];
        for ($y = $def + 1; $y >= $def - 4; $y--) {
            $years[] = ['value' => ClassLadder::yearLabel($y), 'to' => ClassLadder::yearLabel($y + 1)];
        }
        return [
            'years'        => $years,
            'default_year' => ClassLadder::yearLabel($def),
            'classes'      => Promotion::classesWithStudents(),
            'recent'       => array_map([self::class, 'presentBatch'], Promotion::recentBatches(10)),
            'rev'          => SyncState::revision(['students']),
        ];
    }

    /**
     * Daftar santri untuk satu kelas + tujuan kenaikannya.
     * @return array{ok:bool, message?:string, ...}
     */
    public static function candidates(string $fromYear, string $jenjang, string $kelas): array
    {
        [$start, $jenjang, $kelas, $err] = self::normalize($fromYear, $jenjang, $kelas);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }
        $next = ClassLadder::next($jenjang, $kelas);
        if ($next === null) {
            return ['ok' => false, 'message' => 'Kelas "' . $kelas . '" tidak dapat dinaikkan otomatis (format kelas tidak dikenali). Ubah kelas santri secara manual di Data Santri.'];
        }

        $batch = Promotion::findBatch($fromYear, $jenjang, $kelas);
        $rows  = $batch ? [] : Promotion::eligible($fromYear, $jenjang, $kelas);

        return [
            'ok'       => true,
            'from_year' => $fromYear,
            'to_year'  => ClassLadder::yearLabel($start + 1),
            'jenjang'  => $jenjang,
            'kelas'    => $kelas,
            'target'   => $next,
            'processed' => $batch ? self::presentBatch($batch) : null,
            'inactive' => Promotion::inactiveCount($jenjang, $kelas),
            'items'    => array_map(static fn (array $r): array => [
                'id' => (int) $r['id'], 'code' => $r['student_code'], 'nis' => $r['nis'], 'no_urut' => $r['no_urut'] === null ? null : (int) $r['no_urut'],
                'name' => $r['name'], 'saldo' => (int) $r['saldo'],
            ], $rows),
        ];
    }

    /**
     * Proses kenaikan satu kelas.
     * @param array $in {from_year, jenjang, kelas, target_kelas?, decisions: {id: 'naik'|'tidak_naik'}}
     * @param array $actor ['id' => int, 'name' => string]
     * @return array{ok:bool, message?:string, status?:int, result?:array}
     */
    public static function process(array $in, array $actor): array
    {
        $fromYear = trim((string) ($in['from_year'] ?? ''));
        [$start, $jenjang, $kelas, $err] = self::normalize($fromYear, (string) ($in['jenjang'] ?? ''), (string) ($in['kelas'] ?? ''));
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }
        $next = ClassLadder::next($jenjang, $kelas);
        if ($next === null) {
            return ['ok' => false, 'message' => 'Kelas ini tidak dapat dinaikkan otomatis.'];
        }

        $targetJenjang = $targetKelas = null;
        if ($next['type'] === 'naik') {
            $targetJenjang = $next['jenjang'];
            $targetKelas   = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) ($in['target_kelas'] ?? ''))));
            if ($targetKelas === '') {
                $targetKelas = $next['kelas'];
            }
            if (($e = ClassLadder::checkTarget($jenjang, $kelas, $targetJenjang, $targetKelas)) !== null) {
                return ['ok' => false, 'message' => $e];
            }
        }

        $decisions = $in['decisions'] ?? null;
        if (!is_array($decisions) || $decisions === []) {
            return ['ok' => false, 'message' => 'Tentukan status Naik / Tidak Naik untuk setiap santri.'];
        }
        $clean = [];
        foreach ($decisions as $id => $d) {
            if (!is_numeric($id) || (int) $id < 1 || !in_array($d, ['naik', 'tidak_naik'], true)) {
                return ['ok' => false, 'message' => 'Ada santri yang statusnya belum ditentukan atau tidak valid.'];
            }
            $clean[(int) $id] = $d;
        }
        if (count($clean) > SavingsService::BULK_MAX * 2) {
            return ['ok' => false, 'message' => 'Terlalu banyak santri dalam satu proses.'];
        }

        $toYear = ClassLadder::yearLabel($start + 1);

        try {
            $result = Database::transaction(function () use ($fromYear, $toYear, $start, $jenjang, $kelas, $next, $targetJenjang, $targetKelas, $clean, $actor): array {
                if (Promotion::findBatch($fromYear, $jenjang, $kelas) !== null) {
                    throw new PromotionRuleException(self::MSG_DONE, 409);
                }
                $rows = Promotion::eligible($fromYear, $jenjang, $kelas, true); // kunci baris santri
                if ($rows === []) {
                    throw new PromotionRuleException('Tidak ada santri aktif yang dapat diproses pada kelas ini.', 409);
                }
                $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
                $given = array_keys($clean);
                sort($given);
                $want = $ids;
                sort($want);
                if ($given !== $want) {
                    throw new PromotionRuleException(
                        count($given) < count($want)
                            ? 'Ada santri yang statusnya belum ditentukan. Muat ulang halaman lalu periksa kembali.'
                            : 'Daftar santri berubah (ada santri yang pindah/diubah). Muat ulang halaman lalu periksa kembali.',
                        409
                    );
                }

                $naik = $tinggal = $lulus = 0;
                foreach ($clean as $d) {
                    if ($d === 'tidak_naik') {
                        $tinggal++;
                    } elseif ($next['type'] === 'lulus') {
                        $lulus++;
                    } else {
                        $naik++;
                    }
                }

                $promoId = Promotion::insertBatch([
                    'from_year' => $fromYear, 'to_year' => $toYear, 'jenjang' => $jenjang, 'kelas' => $kelas,
                    'target_jenjang' => $targetJenjang, 'target_kelas' => $targetKelas,
                    'naik' => $naik, 'tinggal' => $tinggal, 'lulus' => $lulus, 'by' => (int) $actor['id'],
                ]);

                foreach ($rows as $r) {
                    $sid    = (int) $r['id'];
                    $choice = $clean[$sid];
                    if ($choice === 'tidak_naik') {
                        $status = 'tidak_naik';
                        $nj = $jenjang;
                        $nk = $kelas;
                    } elseif ($next['type'] === 'lulus') {
                        $status = 'lulus';
                        $nj = $nk = null;
                        Promotion::graduate($sid, $start + 1, $fromYear);
                    } else {
                        $status = 'naik';
                        $nj = $targetJenjang;
                        $nk = $targetKelas;
                        Promotion::moveStudent($sid, $nj, $nk);
                    }
                    Promotion::insertHistory([
                        'student_id' => $sid, 'promotion_id' => $promoId, 'from_year' => $fromYear, 'to_year' => $toYear,
                        'prev_jenjang' => $jenjang, 'prev_class' => $kelas, 'new_jenjang' => $nj, 'new_class' => $nk,
                        'status' => $status, 'by' => (int) $actor['id'],
                    ]);
                }

                SyncState::bump('students');
                $tujuan = $next['type'] === 'lulus' ? 'Lulus' : $targetJenjang . ' ' . $targetKelas;
                AuditLog::record(
                    'Memproses kenaikan kelas', 'Kenaikan Kelas', (string) $promoId,
                    "{$jenjang} {$kelas} → {$tujuan} • TA {$fromYear} → {$toYear} • "
                    . ($next['type'] === 'lulus' ? "{$lulus} lulus" : "{$naik} naik") . ", {$tinggal} tidak naik",
                    $actor
                );
                return ['id' => $promoId, 'naik' => $naik, 'tinggal' => $tinggal, 'lulus' => $lulus, 'total' => count($rows)];
            });
        } catch (PromotionRuleException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'status' => $e->status];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && (str_contains($e->getMessage(), 'uq_promotion_class_year') || str_contains($e->getMessage(), 'uq_history_student_year'))) {
                return ['ok' => false, 'message' => self::MSG_DONE, 'status' => 409]; // dua operator memproses bersamaan
            }
            throw $e;
        }

        return ['ok' => true, 'result' => $result + ['from_year' => $fromYear, 'to_year' => $toYear]];
    }

    /** Riwayat kelas satu santri, siap tampil. */
    public static function history(int $studentId): array
    {
        return array_map(static fn (array $h): array => [
            'from_year' => $h['from_year'], 'to_year' => $h['academic_year'],
            'previous'  => $h['previous_jenjang'] . ' ' . $h['previous_class'],
            'new'       => $h['new_class'] === null ? null : $h['new_jenjang'] . ' ' . $h['new_class'],
            'status'    => $h['promotion_status'],
            'at'        => $h['processed_at'], 'by' => $h['processed_by_name'],
        ], Promotion::history($studentId));
    }

    private static function presentBatch(array $p): array
    {
        return [
            'id' => (int) $p['id'], 'from_year' => $p['from_year'], 'to_year' => $p['to_year'],
            'from' => $p['jenjang_asal'] . ' ' . $p['kelas_asal'],
            'to'   => $p['kelas_tujuan'] === null ? null : $p['jenjang_tujuan'] . ' ' . $p['kelas_tujuan'],
            'naik' => (int) $p['count_naik'], 'tinggal' => (int) $p['count_tinggal'], 'lulus' => (int) $p['count_lulus'],
            'at' => $p['processed_at'], 'by' => $p['processed_by_name'],
        ];
    }

    /** @return array{0:int,1:string,2:string,3:?string} [tahun mulai, jenjang, kelas, galat] */
    private static function normalize(string $fromYear, string $jenjang, string $kelas): array
    {
        $start = ClassLadder::parseYear($fromYear);
        if ($start === null) {
            return [0, '', '', 'Tahun ajaran asal tidak valid (contoh: 2025/2026).'];
        }
        $jenjang = strtoupper(trim($jenjang));
        if (!in_array($jenjang, ['TK', 'SD'], true)) {
            return [0, '', '', 'Jenjang wajib dipilih.'];
        }
        $kelas = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $kelas)));
        if ($kelas === '') {
            return [0, '', '', 'Kelas asal wajib dipilih.'];
        }
        return [$start, $jenjang, $kelas, null];
    }
}
