<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Report;
use App\Models\Savings;
use App\Models\Student;
use App\Models\SyncState;
use App\Services\Export\TableExport;
use DateTimeImmutable;

/** Laporan tabungan: ringkasan, rekap, aktivitas periode, dan penyusunan data ekspor. */
final class ReportService
{
    public const EXPORT_MAX_ROWS = 50000;
    public const DATASETS = ['transaksi' => 'Rincian transaksi', 'santri' => 'Rekap per santri'];

    /** Filter laporan: memakai aturan yang sama dengan Riwayat (kata kunci pencarian tidak dipakai). */
    public static function filters(array $query): array
    {
        $f = SavingsService::filtersFromQuery($query);
        $f['q'] = '';
        return $f;
    }

    /** Ringkasan + rekap per kelas + aktivitas periode. */
    public static function build(array $query): array
    {
        $rev = SyncState::revision(['savings', 'students']);
        $f   = self::filters($query);
        $sum = Report::summary($f);

        return [
            'rev'      => $rev,
            'filters'  => $f,
            'summary'  => $sum,
            'by_class' => Report::byClass($f),
            'activity' => self::activity($f, $sum),
            'period'   => self::periodLabel($f, $sum),
            'filter_label' => self::filterLabel($f),
            'options'  => Savings::filterOptions(),
            'student'  => self::studentInfo($f),
        ];
    }

    /** Rekap per santri (berhalaman, dapat diurutkan). */
    public static function students(array $query): array
    {
        $f    = self::filters($query);
        $sort = in_array($query['sort'] ?? '', ['name', 'kelas', 'count', 'masuk', 'keluar', 'net', 'saldo'], true) ? (string) $query['sort'] : 'name';
        $dir  = strtolower((string) ($query['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $per  = (int) ($query['per_page'] ?? 25);
        $per  = in_array($per, [10, 25, 50], true) ? $per : 25;
        $page = max(1, (int) ($query['page'] ?? 1));

        $res   = Report::byStudent($f, $sort, $dir, $page, $per);
        $pages = max(1, (int) ceil($res['total'] / $per));
        if ($page > $pages) {
            $page = $pages;
            $res  = Report::byStudent($f, $sort, $dir, $page, $per);
        }
        return $res + ['page' => $page, 'pages' => $pages, 'sort' => $sort, 'dir' => $dir, 'per_page' => $per, 'as_of' => $f['to'] ?: null];
    }

    private static function studentInfo(array $f): ?array
    {
        if ($f['student_id'] < 1 || ($s = Student::find($f['student_id'])) === null) {
            return null;
        }
        return ['id' => $s['id'], 'name' => $s['name'], 'label' => $s['label'], 'jenjang' => $s['jenjang'], 'kelas' => $s['kelas'], 'code' => $s['code'], 'saldo' => $s['saldo']];
    }

    public static function periodLabel(array $f, array $sum): string
    {
        if ($f['from'] !== '' || $f['to'] !== '') {
            return ($f['from'] !== '' ? tanggal_id($f['from'], true) : 'awal') . ' – ' . ($f['to'] !== '' ? tanggal_id($f['to'], true) : 'sekarang');
        }
        return $sum['first_date'] ? 'Semua periode (' . tanggal_id($sum['first_date'], true) . ' – ' . tanggal_id($sum['last_date'], true) . ')' : 'Semua periode';
    }

    /** Deskripsi filter selain tanggal, mis. "Jenjang SD • Kelas 4A • Mutasi Keluar". */
    public static function filterLabel(array $f): string
    {
        $p = [];
        if ($f['jenjang'] !== '')    { $p[] = 'Jenjang ' . $f['jenjang']; }
        if ($f['kelas'] !== '')      { $p[] = 'Kelas ' . $f['kelas']; }
        if ($f['month'] > 0)         { $p[] = 'Bulan ' . bulan_nama($f['month']); }
        if ($f['year'] > 0)          { $p[] = 'Tahun ' . $f['year']; }
        if ($f['mutation'] !== '')   { $p[] = 'Mutasi ' . ucfirst($f['mutation']); }
        if (($s = self::studentInfo($f)) !== null) { $p[] = 'Santri ' . $s['name']; }
        return $p ? implode(' • ', $p) : 'Tanpa filter tambahan';
    }

    /**
     * Aktivitas per periode: harian (≤ 62 hari), mingguan (≤ 30 minggu), selain itu bulanan.
     * @return array{granularity:string,buckets:array,totals:array{masuk:int,keluar:int}}|array{granularity:string,buckets:array{},totals:array{masuk:int,keluar:int}}
     */
    private static function activity(array $f, array $sum): array
    {
        $empty = ['granularity' => 'month', 'buckets' => [], 'totals' => ['masuk' => 0, 'keluar' => 0]];
        $from = $f['from'] !== '' ? $f['from'] : $sum['first_date'];
        $to   = $f['to'] !== '' ? $f['to'] : $sum['last_date'];
        if (!$from || !$to || $sum['count'] === 0) {
            return $empty;
        }
        $a = new DateTimeImmutable($from);
        $b = new DateTimeImmutable($to);
        if ($a > $b) { [$a, $b] = [$b, $a]; }
        $days = (int) $a->diff($b)->days + 1;
        $range = $days <= 62 ? 'day' : ($days <= 210 ? 'week' : 'month');

        $starts = [];
        switch ($range) {
            case 'day':
                for ($d = $a; $d <= $b; $d = $d->modify('+1 day')) { $starts[] = $d; }
                $expr = 't.transaction_date';
                $end = static fn (DateTimeImmutable $s): DateTimeImmutable => $s;
                break;
            case 'week':
                for ($d = $a->modify('-' . ((int) $a->format('N') - 1) . ' day'); $d <= $b; $d = $d->modify('+1 week')) { $starts[] = $d; }
                $expr = 'DATE_SUB(t.transaction_date, INTERVAL WEEKDAY(t.transaction_date) DAY)';
                $end = static fn (DateTimeImmutable $s): DateTimeImmutable => $s->modify('+6 day');
                break;
            default:
                for ($d = $a->modify('first day of this month'); $d <= $b; $d = $d->modify('first day of next month')) { $starts[] = $d; }
                $expr = "DATE_FORMAT(t.transaction_date, '%Y-%m-01')";
                $end = static fn (DateTimeImmutable $s): DateTimeImmutable => $s->modify('last day of this month');
        }

        $keys = array_map(static fn (DateTimeImmutable $d): string => $d->format('Y-m-d'), $starts);
        $data = Report::activity($f, $expr, $keys);
        $buckets = [];
        $tot = ['masuk' => 0, 'keluar' => 0];
        foreach ($starts as $s) {
            $k = $s->format('Y-m-d');
            [$label, $title] = DashboardService::labels($range, $s, $end($s));
            $buckets[] = ['key' => $k, 'label' => $label, 'title' => $title, 'masuk' => $data[$k]['masuk'], 'keluar' => $data[$k]['keluar']];
            $tot['masuk'] += $data[$k]['masuk'];
            $tot['keluar'] += $data[$k]['keluar'];
        }
        return ['granularity' => $range, 'buckets' => $buckets, 'totals' => $tot];
    }

    /**
     * Susun data ekspor. Mengembalikan null bila baris melebihi batas (pemanggil menampilkan pesan).
     * @return array{table:?TableExport, rows:int, over:bool}
     */
    public static function exportTable(string $dataset, array $query, string $by): array
    {
        $f   = self::filters($query);
        $sum = Report::summary($f);
        $meta = [
            'Periode: ' . self::periodLabel($f, $sum),
            'Filter: ' . self::filterLabel($f),
            'Diekspor: ' . tanggal_id(date('Y-m-d')) . ' ' . date('H:i') . ' oleh ' . $by,
        ];

        if ($dataset === 'santri') {
            $rows = Report::allStudents($f);
            if (count($rows) > self::EXPORT_MAX_ROWS) {
                return ['table' => null, 'rows' => count($rows), 'over' => true];
            }
            $data = [];
            foreach ($rows as $i => $r) {
                $data[] = ['no' => $i + 1, 'code' => $r['code'], 'name' => $r['name'], 'jenjang' => $r['jenjang'], 'kelas' => $r['kelas'],
                           'count' => $r['count'], 'masuk' => $r['masuk'], 'keluar' => $r['keluar'], 'net' => $r['net'], 'saldo' => $r['saldo']];
            }
            $asOf = $f['to'] !== '' ? tanggal_id($f['to'], true) : 'saat ini';
            $totalSaldo = array_sum(array_column($rows, 'saldo'));
            return ['over' => false, 'rows' => count($data), 'table' => new TableExport(
                'Rekap Tabungan per Santri', $meta,
                [
                    ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 6],
                    ['key' => 'code', 'label' => 'ID Santri', 'type' => 'text', 'width' => 12],
                    ['key' => 'name', 'label' => 'Nama Santri', 'type' => 'text', 'width' => 30],
                    ['key' => 'jenjang', 'label' => 'Jenjang', 'type' => 'text', 'width' => 9],
                    ['key' => 'kelas', 'label' => 'Kelas', 'type' => 'text', 'width' => 10],
                    ['key' => 'count', 'label' => 'Jumlah Transaksi', 'type' => 'int', 'width' => 12],
                    ['key' => 'masuk', 'label' => 'Total Masuk (Rp)', 'type' => 'money', 'width' => 16],
                    ['key' => 'keluar', 'label' => 'Total Keluar (Rp)', 'type' => 'money', 'width' => 16],
                    ['key' => 'net', 'label' => 'Selisih (Rp)', 'type' => 'money', 'width' => 16],
                    ['key' => 'saldo', 'label' => ($f['to'] !== '' ? "Saldo per {$asOf}" : 'Saldo saat ini') . ' (Rp)', 'type' => 'money', 'width' => 20],
                ],
                $data,
                ['name' => 'TOTAL', 'count' => $sum['count'], 'masuk' => $sum['masuk'], 'keluar' => $sum['keluar'], 'net' => $sum['net'], 'saldo' => $totalSaldo],
                'Rekap per Santri'
            )];
        }

        // transaksi
        if ($sum['count'] > self::EXPORT_MAX_ROWS) {
            return ['table' => null, 'rows' => $sum['count'], 'over' => true];
        }
        $data = [];
        foreach (Report::transactions($f, self::EXPORT_MAX_ROWS) as $i => $r) {
            $masuk = $r['mutation_type'] === 'masuk';
            $data[] = [
                'no' => $i + 1, 'code' => $r['transaction_code'], 'date' => $r['transaction_date'], 'name' => $r['name'], 'sid' => $r['student_code'],
                'jenjang' => $r['jenjang'], 'kelas' => $r['kelas'], 'period' => bulan_nama((int) $r['period_month']) . ' ' . $r['period_year'],
                'mutation' => $masuk ? 'Masuk' : 'Keluar', 'masuk' => $masuk ? (int) $r['amount'] : null, 'keluar' => $masuk ? null : (int) $r['amount'],
                'description' => $r['description'], 'saldo' => (int) $r['rb'],
            ];
        }
        return ['over' => false, 'rows' => count($data), 'table' => new TableExport(
            'Rincian Transaksi Tabungan', $meta,
            [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 6],
                ['key' => 'code', 'label' => 'Kode Transaksi', 'type' => 'text', 'width' => 20],
                ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'width' => 12],
                ['key' => 'name', 'label' => 'Nama Santri', 'type' => 'text', 'width' => 28],
                ['key' => 'sid', 'label' => 'ID Santri', 'type' => 'text', 'width' => 11],
                ['key' => 'jenjang', 'label' => 'Jenjang', 'type' => 'text', 'width' => 9],
                ['key' => 'kelas', 'label' => 'Kelas', 'type' => 'text', 'width' => 10],
                ['key' => 'period', 'label' => 'Bulan', 'type' => 'text', 'width' => 16],
                ['key' => 'mutation', 'label' => 'Mutasi', 'type' => 'text', 'width' => 9],
                ['key' => 'masuk', 'label' => 'Masuk (Rp)', 'type' => 'money', 'width' => 14],
                ['key' => 'keluar', 'label' => 'Keluar (Rp)', 'type' => 'money', 'width' => 14],
                ['key' => 'description', 'label' => 'Keterangan', 'type' => 'text', 'width' => 40],
                ['key' => 'saldo', 'label' => 'Saldo Santri (Rp)', 'type' => 'money', 'width' => 18],
            ],
            $data,
            ['name' => 'TOTAL', 'masuk' => $sum['masuk'], 'keluar' => $sum['keluar']],
            'Rincian Transaksi'
        )];
    }
}
