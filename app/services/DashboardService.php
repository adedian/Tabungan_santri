<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ClassBalance;
use App\Models\Savings;
use App\Models\SyncState;
use DateTimeImmutable;

final class DashboardService
{
    public const RANGES = ['day', 'week', 'month'];
    public const SCOPES = ['savings', 'students'];

    /** Revisi dibaca SEBELUM query data: bila ada tulis di tengah jalan, polling berikutnya memicu muat ulang. */
    public static function summary(): array
    {
        $rev     = SyncState::revision(self::SCOPES);
        $totals  = Savings::totals();
        $jenjang = Savings::byJenjang();

        return [
            'rev'     => $rev,
            'totals'  => $totals,
            'santri'  => array_sum(array_column($jenjang, 'santri')),
            'jenjang' => array_values($jenjang),
            'alumni'  => Savings::alumniTotals(),
            'recent'  => Savings::recent(10),
        ];
    }

    /**
     * @return array{range:string, buckets:array<int,array>, totals:array{masuk:int,keluar:int}}
     */
    public static function activity(string $range, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);

        // Ekspresi SQL dari whitelist tetap — jangan pernah menyisipkan input pengguna.
        switch ($range) {
            case 'day':
                $starts = [];
                for ($i = 13; $i >= 0; $i--) {
                    $starts[] = $today->modify("-{$i} day");
                }
                $expr = 'transaction_date';
                $end  = static fn (DateTimeImmutable $s): DateTimeImmutable => $s;
                break;
            case 'week':
                $monday = $today->modify('-' . ((int) $today->format('N') - 1) . ' day');
                $starts = [];
                for ($i = 11; $i >= 0; $i--) {
                    $starts[] = $monday->modify("-{$i} week");
                }
                $expr = 'DATE_SUB(transaction_date, INTERVAL WEEKDAY(transaction_date) DAY)';
                $end  = static fn (DateTimeImmutable $s): DateTimeImmutable => $s->modify('+6 day');
                break;
            case 'month':
                $first  = $today->modify('first day of this month');
                $starts = [];
                for ($i = 11; $i >= 0; $i--) {
                    $starts[] = $first->modify("-{$i} month");
                }
                $expr = "DATE_FORMAT(transaction_date, '%Y-%m-01')";
                $end  = static fn (DateTimeImmutable $s): DateTimeImmutable => $s->modify('last day of this month');
                break;
            default:
                throw new \InvalidArgumentException('Rentang tidak valid.');
        }

        $keys = array_map(static fn (DateTimeImmutable $d): string => $d->format('Y-m-d'), $starts);
        $data = Savings::activity($expr, $keys[0], $end(end($starts))->format('Y-m-d'), $keys);

        $buckets = [];
        $sum = ['masuk' => 0, 'keluar' => 0];
        foreach ($starts as $s) {
            $k = $s->format('Y-m-d');
            [$label, $title] = self::labels($range, $s, $end($s));
            $buckets[] = ['key' => $k, 'label' => $label, 'title' => $title, 'masuk' => $data[$k]['masuk'], 'keluar' => $data[$k]['keluar']];
            $sum['masuk']  += $data[$k]['masuk'];
            $sum['keluar'] += $data[$k]['keluar'];
        }
        return ['range' => $range, 'buckets' => $buckets, 'totals' => $sum];
    }

    private static function short(DateTimeImmutable $d): string
    {
        return (int) $d->format('j') . ' ' . bulan_pendek((int) $d->format('n'));
    }

    /** @return array{0:string,1:string} [label sumbu, judul tooltip] */
    public static function labels(string $range, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        switch ($range) {
            case 'day':
                return [self::short($start), hari_id($start->format('Y-m-d')) . ', ' . tanggal_id($start->format('Y-m-d'))];
            case 'week':
                return [self::short($start), 'Minggu ' . self::short($start) . ' – ' . self::short($end) . ' ' . $end->format('Y')];
            default:
                $m = bulan_pendek((int) $start->format('n'));
                return [$m . ' ’' . $start->format('y'),bulan_nama((int) $start->format('n')) . ' ' . $start->format('Y')];
        }
    }

    /* ======================================================================
       Saldo Tabungan Setiap Kelas (Revisi 2)
       ====================================================================== */

    public const PERIODS = ['day', 'month', 'year'];
    /** Urutan tampil jenjang; jenjang lain (bila kelak ada) menyusul di belakang menurut abjad. */
    public const JENJANG_ORDER = ['TK', 'SD'];

    /** Parameter query klien → filter aman (nilai tak sah diganti bawaan: bulan berjalan, semua jenjang). */
    public static function classFilters(array $q, ?DateTimeImmutable $today = null): array
    {
        $today  = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $period = in_array($q['period'] ?? '', self::PERIODS, true) ? (string) $q['period'] : 'month';

        $date = (string) ($q['date'] ?? '');
        $d    = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $date < '2000-01-01' || $date > '2100-12-31') {
            $date = $today->format('Y-m-d');
        }
        $month = (int) ($q['month'] ?? 0);
        $year  = (int) ($q['year'] ?? 0);

        return [
            'jenjang' => (string) (in_array($q['jenjang'] ?? '', self::JENJANG_ORDER, true) ? $q['jenjang'] : ''),
            'period'  => $period,
            'date'    => $date,
            'month'   => ($month >= 1 && $month <= 12) ? $month : (int) $today->format('n'),
            'year'    => ($year >= 2000 && $year <= 2100) ? $year : (int) $today->format('Y'),
        ];
    }

    /** @return array{from:string,to:string,label:string} */
    public static function classPeriod(array $f): array
    {
        if ($f['period'] === 'day') {
            return ['from' => $f['date'], 'to' => $f['date'], 'label' => tanggal_id($f['date'])];
        }
        if ($f['period'] === 'year') {
            return ['from' => $f['year'] . '-01-01', 'to' => $f['year'] . '-12-31', 'label' => 'Tahun ' . $f['year']];
        }
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $f['year'], $f['month']));
        return ['from' => $first->format('Y-m-d'), 'to' => $first->modify('last day of this month')->format('Y-m-d'),
                'label' => bulan_nama($f['month']) . ' ' . $f['year']];
    }

    /**
     * Saldo, mutasi, dan perpindahan kelas per kelas untuk satu periode.
     * Awal + Masuk − Keluar ± Pindah = Akhir (Pindah ≠ 0 hanya bila ada kenaikan/kelulusan pada periode itu).
     */
    public static function classBalances(array $query, ?DateTimeImmutable $today = null): array
    {
        $rev = SyncState::revision(self::SCOPES); // dibaca SEBELUM query data (lihat summary())
        $f   = self::classFilters($query, $today);
        $p   = self::classPeriod($f);

        $before = (new DateTimeImmutable($p['from']))->modify('-1 day')->format('Y-m-d');
        $awal   = ClassBalance::balancesAsOf($before);
        $akhir  = ClassBalance::balancesAsOf($p['to']);
        $mut    = ClassBalance::mutations($p['from'], $p['to']);

        // Kelas yang ditampilkan: dipakai santri aktif + muncul pada data + saran bawaan (selalu tampil, Rp 0 bila kosong)
        $classes = [];
        $add = static function (string $j, string $k) use (&$classes): void {
            $classes[ClassBalance::key($j, $k)] = [$j, $k];
        };
        foreach (StudentService::DEFAULT_CLASSES as $j => $list) {
            foreach ($list as $k) { $add($j, $k); }
        }
        foreach (ClassBalance::activeClasses() as [$j, $k]) { $add($j, $k); }
        foreach ([$awal, $akhir, $mut] as $set) {
            foreach (array_keys($set) as $key) { [$j, $k] = explode('|', $key, 2); $add($j, $k); }
        }

        $groups = [];
        $total  = self::zero();
        foreach ($classes as $key => [$j, $k]) {
            if ($f['jenjang'] !== '' && $j !== $f['jenjang']) {
                continue;
            }
            $row = [
                'kelas'  => $k,
                'label'  => self::classLabel($j, $k),
                'awal'   => $awal[$key] ?? 0,
                'masuk'  => $mut[$key]['masuk'] ?? 0,
                'keluar' => $mut[$key]['keluar'] ?? 0,
                'akhir'  => $akhir[$key] ?? 0,
                'n'      => $mut[$key]['n'] ?? 0,
            ];
            $row['pindah'] = $row['akhir'] - $row['awal'] - $row['masuk'] + $row['keluar'];
            $groups[$j]['jenjang'] = $j;
            $groups[$j]['classes'][] = $row;
            $groups[$j]['totals'] = self::add($groups[$j]['totals'] ?? self::zero(), $row);
            $total = self::add($total, $row);
        }

        uksort($groups, static function (string $a, string $b): int {
            $ia = array_search($a, self::JENJANG_ORDER, true);
            $ib = array_search($b, self::JENJANG_ORDER, true);
            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });
        foreach ($groups as &$g) {
            usort($g['classes'], static fn (array $a, array $b): int => ((int) $a['kelas'] <=> (int) $b['kelas']) ?: strnatcasecmp($a['kelas'], $b['kelas']));
        }
        unset($g);

        $first = ClassBalance::firstYear();
        $thisYear = (int) ($today ?? new DateTimeImmutable('today'))->format('Y');
        $years = [];
        for ($y = $thisYear; $y >= min($first ?? $thisYear, $thisYear, $f['year']); $y--) {
            $years[] = $y;
        }

        return [
            'rev'     => $rev,
            'today'   => ($today ?? new DateTimeImmutable('today'))->format('Y-m-d'), // acuan "hari ini" server (WIB) untuk Reset
            'filters' => $f,
            'period'  => $p + ['before' => $before],
            'totals'  => $total,
            'groups'  => array_values($groups),
            'has_data' => $total['n'] > 0,
            'options' => ['years' => $years, 'jenjang' => self::JENJANG_ORDER],
        ];
    }

    private static function zero(): array
    {
        return ['awal' => 0, 'masuk' => 0, 'keluar' => 0, 'pindah' => 0, 'akhir' => 0, 'n' => 0];
    }

    private static function add(array $t, array $r): array
    {
        foreach (['awal', 'masuk', 'keluar', 'pindah', 'akhir', 'n'] as $k) {
            $t[$k] += $r[$k];
        }
        return $t;
    }

    /** "1A" → "Kelas 1A"; "TK A" → "TK A" (kelas yang sudah bernama tetap apa adanya). */
    public static function classLabel(string $jenjang, string $kelas): string
    {
        return ctype_digit(substr($kelas, 0, 1)) ? 'Kelas ' . $kelas : $kelas;
    }
}
