<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Query ledger tabungan. SEMUA query wajib memfilter deleted_at IS NULL.
 * (Operasi tulis ditambahkan di Phase 8–9.)
 */
final class Savings
{
    /**
     * Total tabungan santri (alumni dipisahkan: lihat alumniTotals(); satu ledger, jadi tidak ada hitung ganda).
     * @return array{masuk:int, keluar:int, saldo:int, transaksi:int}
     */
    public static function totals(): array
    {
        $r = Database::fetchOne(
            "SELECT COALESCE(SUM(CASE WHEN t.mutation_type = 'masuk'  THEN t.amount END), 0) AS masuk,
                    COALESCE(SUM(CASE WHEN t.mutation_type = 'keluar' THEN t.amount END), 0) AS keluar,
                    COUNT(*) AS transaksi
               FROM savings_transactions t JOIN students s ON s.id = t.student_id
              WHERE t.deleted_at IS NULL AND s.status <> 'alumni'"
        ) ?? ['masuk' => 0, 'keluar' => 0, 'transaksi' => 0];

        $masuk  = (int) $r['masuk'];
        $keluar = (int) $r['keluar'];
        return ['masuk' => $masuk, 'keluar' => $keluar, 'saldo' => $masuk - $keluar, 'transaksi' => (int) $r['transaksi']];
    }

    /**
     * Saldo & jumlah santri aktif per jenjang (jenjang SAAT INI milik santri).
     * Saldo mencakup semua santri (termasuk nonaktif) agar jumlahnya sama dengan total saldo.
     *
     * @return array<string, array{jenjang:string, santri:int, saldo:int}>
     */
    public static function byJenjang(): array
    {
        $out = ['TK' => ['jenjang' => 'TK', 'santri' => 0, 'saldo' => 0], 'SD' => ['jenjang' => 'SD', 'santri' => 0, 'saldo' => 0]];
        $rows = Database::fetchAll(
            "SELECT s.jenjang, SUM(s.status = 'aktif') AS santri, COALESCE(SUM(b.saldo), 0) AS saldo
               FROM students s JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id
              WHERE s.status <> 'alumni' AND s.deleted_at IS NULL
              GROUP BY s.jenjang"
        );
        foreach ($rows as $r) {
            $out[$r['jenjang']] = ['jenjang' => $r['jenjang'], 'santri' => (int) $r['santri'], 'saldo' => (int) $r['saldo']];
        }
        return $out;
    }

    /** Jumlah & saldo alumni (ledger yang sama, hanya santri berstatus alumni). @return array{alumni:int, saldo:int} */
    public static function alumniTotals(): array
    {
        $r = Database::fetchOne(
            "SELECT COUNT(*) AS n, COALESCE(SUM(b.saldo), 0) AS saldo
               FROM students s JOIN " . Database::STUDENT_BALANCES . " b ON b.student_id = s.id
              WHERE s.status = 'alumni' AND s.deleted_at IS NULL"
        ) ?? ['n' => 0, 'saldo' => 0];
        return ['alumni' => (int) $r['n'], 'saldo' => (int) $r['saldo']];
    }

    /** Saldo satu santri dari ledger: SUM(masuk) − SUM(keluar). */
    public static function balance(int $studentId): int
    {
        return (int) Database::fetchValue(
            "SELECT COALESCE(SUM(CASE WHEN mutation_type = 'masuk' THEN amount ELSE -amount END), 0)
               FROM savings_transactions WHERE student_id = ? AND deleted_at IS NULL",
            [$studentId]
        );
    }

    /** Ringkasan satu santri (untuk panel saldo). */
    public static function studentSummary(int $studentId): array
    {
        $r = Database::fetchOne('SELECT total_masuk, total_keluar, saldo, jumlah_transaksi FROM ' . Database::STUDENT_BALANCES . ' b WHERE b.student_id = ?', [$studentId])
            ?? ['total_masuk' => 0, 'total_keluar' => 0, 'saldo' => 0, 'jumlah_transaksi' => 0];
        return ['student_id' => $studentId, 'masuk' => (int) $r['total_masuk'], 'keluar' => (int) $r['total_keluar'],
                'saldo' => (int) $r['saldo'], 'transaksi' => (int) $r['jumlah_transaksi']];
    }

    /** Seluruh transaksi santri (urut tanggal, id) pada rentang tanggal opsional — untuk cetak rekap. */
    public static function ledger(int $studentId, string $from = '', string $to = ''): array
    {
        $sql = 'SELECT id, transaction_date, mutation_type, amount, description FROM savings_transactions WHERE student_id = ? AND deleted_at IS NULL';
        $p = [$studentId];
        if ($from !== '') { $sql .= ' AND transaction_date >= ?'; $p[] = $from; }
        if ($to !== '')   { $sql .= ' AND transaction_date <= ?'; $p[] = $to; }
        return Database::fetchAll($sql . ' ORDER BY transaction_date ASC, id ASC', $p);
    }

    /** Saldo santri SEBELUM tanggal tertentu (tanggal itu tidak termasuk). */
    public static function balanceBefore(int $studentId, string $date): int
    {
        return (int) Database::fetchValue(
            "SELECT COALESCE(SUM(CASE WHEN mutation_type = 'masuk' THEN amount ELSE -amount END), 0)
               FROM savings_transactions WHERE student_id = ? AND deleted_at IS NULL AND transaction_date < ?",
            [$studentId, $date]
        );
    }

    /** Tanggal transaksi pertama & terakhir santri (null bila belum ada transaksi). */
    public static function studentDates(int $studentId): array
    {
        $r = Database::fetchOne(
            'SELECT MIN(transaction_date) AS first_date, MAX(transaction_date) AS last_date
               FROM savings_transactions WHERE student_id = ? AND deleted_at IS NULL',
            [$studentId]
        );
        return ['first_date' => $r['first_date'] ?? null, 'last_date' => $r['last_date'] ?? null];
    }

    /**
     * Saldo berjalan TERENDAH di seluruh riwayat santri (urut tanggal, id). Dipakai agar tidak ada titik
     * waktu di mana saldo negatif — termasuk saat transaksi bertanggal mundur disisipkan (juga untuk edit/hapus nanti).
     */
    public static function minRunningBalance(int $studentId): int
    {
        return (int) Database::fetchValue(
            "SELECT COALESCE(MIN(rb), 0) FROM (
                SELECT SUM(CASE WHEN mutation_type = 'masuk' THEN amount ELSE -amount END)
                       OVER (ORDER BY transaction_date, id) AS rb
                  FROM savings_transactions WHERE student_id = ? AND deleted_at IS NULL
             ) x",
            [$studentId]
        );
    }

    /** Kunci baris santri (serialisasi transaksi bersamaan untuk santri yang sama). Panggil di dalam transaksi DB. */
    public static function lockStudent(int $studentId): ?array
    {
        return Database::fetchOne('SELECT id, name, jenjang, kelas, status FROM students WHERE id = ? FOR UPDATE', [$studentId]);
    }

    /** Nomor transaksi TAB-YYYYMMDD-NNNN; penghitung atomik per tanggal (aman dari duplikat). */
    public static function nextCode(string $date): string
    {
        Database::execute(
            'INSERT INTO transaction_sequences (seq_date, last_no) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1)',
            [$date]
        );
        return 'TAB-' . str_replace('-', '', $date) . '-' . str_pad((string) Database::lastInsertId(), 4, '0', STR_PAD_LEFT);
    }

    public static function insert(array $d): int
    {
        Database::execute(
            'INSERT INTO savings_transactions
               (transaction_code, student_id, transaction_date, period_month, period_year, jenjang, kelas, mutation_type, amount, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['code'], $d['student_id'], $d['date'], $d['period_month'], $d['period_year'], $d['jenjang'], $d['kelas'],
             $d['mutation'], $d['amount'], $d['description'], $d['created_by']]
        );
        return Database::lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $rows = self::recentWhere('t.id = ?', [$id], 1);
        return $rows[0] ?? null;
    }

    /** Transaksi yang paling akhir DIINPUT (bukan paling akhir tanggalnya). */
    public static function recent(int $limit = 10): array
    {
        return self::recentWhere('1 = 1', [], $limit);
    }

    private static function recentWhere(string $where, array $params, int $limit): array
    {
        $rows = Database::fetchAll(
            "SELECT t.id, t.transaction_code, t.transaction_date, t.created_at, s.id AS student_id, s.name,
                    t.jenjang, t.kelas, t.mutation_type, t.amount, t.description
               FROM savings_transactions t
               JOIN students s ON s.id = t.student_id
              WHERE t.deleted_at IS NULL AND {$where}
              ORDER BY t.id DESC
              LIMIT ?",
            array_merge($params, [$limit])
        );
        return array_map(static fn (array $r): array => [
            'id'          => (int) $r['id'],
            'code'        => $r['transaction_code'],
            'date'        => $r['transaction_date'],
            'created_at'  => $r['created_at'],
            'student_id'  => (int) $r['student_id'],
            'student'     => $r['name'],
            'jenjang'     => $r['jenjang'],
            'kelas'       => $r['kelas'],
            'mutation'    => $r['mutation_type'],
            'amount'      => (int) $r['amount'],
            'description' => $r['description'],
        ], $rows);
    }

    /* ===================== Riwayat: daftar, filter, saldo berjalan ===================== */

    /** Kunci sort klien => ORDER BY (whitelist). Tie-breaker selalu t.id DESC. */
    private const LIST_SORTS = [
        'date'     => 't.transaction_date {dir}, t.id {dir}',
        'name'     => 's.name {dir}',
        'jenjang'  => 't.jenjang {dir}',
        'kelas'    => 'CAST(t.kelas AS UNSIGNED) {dir}, t.kelas {dir}',
        'month'    => 't.period_year {dir}, t.period_month {dir}',
        'mutation' => 't.mutation_type {dir}',
        'amount'   => 't.amount {dir}',
    ];

    /**
     * @param array{q?:string,jenjang?:string,kelas?:string,month?:int,year?:int,mutation?:string,from?:string,to?:string,student_id?:int} $f
     * @return array{0:string,1:array}
     */
    public static function listWhere(array $f): array
    {
        $sql = ['t.deleted_at IS NULL'];
        $p   = [];

        foreach (array_slice(preg_split('/\s+/u', trim((string) ($f['q'] ?? ''))) ?: [], 0, 5) as $tok) {
            if ($tok === '') {
                continue;
            }
            $l = Student::like($tok);
            $sql[] = '(s.name LIKE ? OR t.transaction_code LIKE ? OR t.description LIKE ? OR t.kelas LIKE ? OR t.jenjang = ?)';
            array_push($p, "%{$l}%", "{$l}%", "%{$l}%", "{$l}%", mb_strtoupper($tok));
        }
        if (!empty($f['jenjang'])) { $sql[] = 't.jenjang = ?';        $p[] = $f['jenjang']; }
        if (!empty($f['kelas']))   { $sql[] = 't.kelas = ?';          $p[] = $f['kelas']; }
        if (!empty($f['month']))   { $sql[] = 't.period_month = ?';   $p[] = (int) $f['month']; }
        if (!empty($f['year']))    { $sql[] = 't.period_year = ?';    $p[] = (int) $f['year']; }
        if (!empty($f['mutation'])){ $sql[] = 't.mutation_type = ?';  $p[] = $f['mutation']; }
        if (!empty($f['from']))    { $sql[] = 't.transaction_date >= ?'; $p[] = $f['from']; }
        if (!empty($f['to']))      { $sql[] = 't.transaction_date <= ?'; $p[] = $f['to']; }
        if (!empty($f['student_id'])) { $sql[] = 't.student_id = ?';  $p[] = (int) $f['student_id']; }

        return ['WHERE ' . implode(' AND ', $sql), $p];
    }

    /**
     * Daftar transaksi + ringkasan (dari seluruh hasil filter, bukan hanya halaman ini) + saldo berjalan per baris.
     * @return array{items:array, total:int, summary:array{masuk:int,keluar:int,net:int,count:int}}
     */
    public static function paginate(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $params] = self::listWhere($f);
        $dir   = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $order = str_replace('{dir}', $dir, self::LIST_SORTS[$sort] ?? self::LIST_SORTS['date']);

        $agg = Database::fetchOne(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(CASE WHEN t.mutation_type = 'masuk'  THEN t.amount END), 0) AS masuk,
                    COALESCE(SUM(CASE WHEN t.mutation_type = 'keluar' THEN t.amount END), 0) AS keluar
               FROM savings_transactions t JOIN students s ON s.id = t.student_id {$where}",
            $params
        ) ?? ['n' => 0, 'masuk' => 0, 'keluar' => 0];

        $rows = Database::fetchAll(
            "SELECT t.id, t.transaction_code, t.transaction_date, t.created_at, t.student_id, s.name,
                    t.jenjang, t.kelas, t.period_month, t.period_year, t.mutation_type, t.amount, t.description
               FROM savings_transactions t JOIN students s ON s.id = t.student_id
               {$where}
              ORDER BY {$order}, t.id DESC
              LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, max(0, ($page - 1) * $perPage)])
        );

        $balances = self::runningBalances(array_map(static fn (array $r): int => (int) $r['id'], $rows),
                                          array_map(static fn (array $r): int => (int) $r['student_id'], $rows));

        $items = array_map(static fn (array $r): array => [
            'id'           => (int) $r['id'],
            'code'         => $r['transaction_code'],
            'date'         => $r['transaction_date'],
            'created_at'   => $r['created_at'],
            'student_id'   => (int) $r['student_id'],
            'student'      => $r['name'],
            'jenjang'      => $r['jenjang'],
            'kelas'        => $r['kelas'],
            'period_month' => (int) $r['period_month'],
            'period_year'  => (int) $r['period_year'],
            'mutation'     => $r['mutation_type'],
            'amount'       => (int) $r['amount'],
            'description'  => $r['description'],
            'saldo'        => $balances[(int) $r['id']] ?? 0,
        ], $rows);

        $masuk = (int) $agg['masuk']; $keluar = (int) $agg['keluar'];
        return ['items' => $items, 'total' => (int) $agg['n'], 'summary' => ['masuk' => $masuk, 'keluar' => $keluar, 'net' => $masuk - $keluar, 'count' => (int) $agg['n']]];
    }

    /**
     * Saldo santri SETELAH tiap transaksi (urut tanggal, id) pada seluruh riwayatnya — bukan hanya baris yang terfilter.
     * Hanya menghitung santri yang tampil di halaman ini.
     * @return array<int,int> id transaksi => saldo
     */
    public static function runningBalances(array $ids, array $studentIds): array
    {
        $ids = array_values(array_unique($ids));
        $studentIds = array_values(array_unique($studentIds));
        if ($ids === []) {
            return [];
        }
        $inS = implode(',', array_fill(0, count($studentIds), '?'));
        $inI = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::fetchAll(
            "SELECT id, rb FROM (
                SELECT id, SUM(CASE WHEN mutation_type = 'masuk' THEN amount ELSE -amount END)
                            OVER (PARTITION BY student_id ORDER BY transaction_date, id) AS rb
                  FROM savings_transactions
                 WHERE deleted_at IS NULL AND student_id IN ({$inS})
             ) x WHERE id IN ({$inI})",
            array_merge($studentIds, $ids)
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (int) $r['rb'];
        }
        return $out;
    }

    /** Pilihan filter yang benar-benar ada di data: kelas per jenjang & tahun periode. */
    public static function filterOptions(): array
    {
        $classes = ['TK' => [], 'SD' => []];
        foreach (Database::fetchAll('SELECT DISTINCT jenjang, kelas FROM savings_transactions WHERE deleted_at IS NULL ORDER BY jenjang, CAST(kelas AS UNSIGNED), kelas') as $r) {
            $classes[$r['jenjang']][] = $r['kelas'];
        }
        $years = array_map('intval', array_column(
            Database::fetchAll('SELECT DISTINCT period_year FROM savings_transactions WHERE deleted_at IS NULL ORDER BY period_year DESC'),
            'period_year'
        ));
        return ['classes' => $classes, 'years' => $years];
    }

    /** Baris mentah (belum dihapus) untuk edit/hapus. */
    public static function findRaw(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT t.*, s.name AS student_name FROM savings_transactions t JOIN students s ON s.id = t.student_id
              WHERE t.id = ? AND t.deleted_at IS NULL',
            [$id]
        );
    }

    public static function updateRow(int $id, array $d, int $by): void
    {
        Database::execute(
            'UPDATE savings_transactions
                SET transaction_date = ?, period_month = ?, period_year = ?, jenjang = ?, kelas = ?,
                    mutation_type = ?, amount = ?, description = ?, updated_by = ?
              WHERE id = ? AND deleted_at IS NULL',
            [$d['date'], $d['period_month'], $d['period_year'], $d['jenjang'], $d['kelas'], $d['mutation'], $d['amount'], $d['description'], $by, $id]
        );
    }

    public static function softDelete(int $id, int $by): void
    {
        Database::execute('UPDATE savings_transactions SET deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ? AND deleted_at IS NULL', [$by, $by, $id]);
    }

    /**
     * Baris mentah (belum dihapus) untuk banyak ID, urut santri lalu id.
     * @param int[] $ids
     */
    public static function findManyRaw(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return Database::fetchAll(
            "SELECT t.*, s.name AS student_name FROM savings_transactions t JOIN students s ON s.id = t.student_id
              WHERE t.id IN ({$in}) AND t.deleted_at IS NULL ORDER BY t.student_id, t.id",
            array_values($ids)
        );
    }

    /** Tanggal pertama di mana saldo berjalan santri menjadi negatif (null bila tidak pernah). */
    public static function firstNegativeDate(int $studentId): ?string
    {
        $d = Database::fetchValue(
            "SELECT transaction_date FROM (
                SELECT transaction_date, id, SUM(CASE WHEN mutation_type = 'masuk' THEN amount ELSE -amount END)
                       OVER (ORDER BY transaction_date, id) AS rb
                  FROM savings_transactions WHERE student_id = ? AND deleted_at IS NULL
             ) x WHERE rb < 0 ORDER BY transaction_date, id LIMIT 1",
            [$studentId]
        );
        return $d === null ? null : (string) $d;
    }

    /**
     * Total masuk/keluar per periode, termasuk periode kosong (0).
     * $bucketExpr berasal dari whitelist di Dashboard service — BUKAN input pengguna.
     *
     * @param array<int,string> $bucketStarts tanggal awal tiap periode (Y-m-d), berurutan
     * @return array<string, array{masuk:int, keluar:int}> kunci = tanggal awal periode
     */
    public static function activity(string $bucketExpr, string $from, string $to, array $bucketStarts): array
    {
        $rows = Database::fetchAll(
            "SELECT {$bucketExpr} AS bucket,
                    SUM(CASE WHEN mutation_type = 'masuk'  THEN amount ELSE 0 END) AS masuk,
                    SUM(CASE WHEN mutation_type = 'keluar' THEN amount ELSE 0 END) AS keluar
               FROM savings_transactions
              WHERE deleted_at IS NULL AND transaction_date BETWEEN ? AND ?
              GROUP BY bucket",
            [$from, $to]
        );
        $out = [];
        foreach ($bucketStarts as $start) {
            $out[$start] = ['masuk' => 0, 'keluar' => 0];
        }
        foreach ($rows as $r) {
            if (isset($out[$r['bucket']])) {
                $out[$r['bucket']] = ['masuk' => (int) $r['masuk'], 'keluar' => (int) $r['keluar']];
            }
        }
        return $out;
    }
}
