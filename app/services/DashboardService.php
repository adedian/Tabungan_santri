<?php
declare(strict_types=1);

namespace App\Services;

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
}
