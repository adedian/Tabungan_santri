<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use Throwable;

final class AuditLog
{
    /**
     * Catat aktivitas. Kegagalan mencatat tidak boleh menggagalkan aksi utama (hanya dilog).
     *
     * @param array|null $actor ['id' => ?int, 'name' => string]; default: pengguna yang sedang login
     */
    public static function record(
        string $action,
        string $module,
        ?string $referenceId = null,
        ?string $description = null,
        ?array $actor = null
    ): void {
        try {
            if ($actor === null) {
                $user  = Auth::user();
                $actor = ['id' => $user['id'] ?? null, 'name' => $user['name'] ?? 'Sistem'];
            }
            $request = Request::current();

            Database::execute(
                'INSERT INTO audit_logs (user_id, user_name, action, module, reference_id, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $actor['id'],
                    mb_substr((string) $actor['name'], 0, 100),
                    mb_substr($action, 0, 100),
                    mb_substr($module, 0, 50),
                    $referenceId !== null ? mb_substr($referenceId, 0, 50) : null,
                    $description !== null ? mb_substr($description, 0, 500) : null,
                    $request?->ip(),
                ]
            );
        } catch (Throwable $e) {
            Logger::exception($e);
        }
    }

    private const LIST_SORTS = [
        'time'   => 'a.created_at {dir}, a.id {dir}',
        'user'   => 'a.user_name {dir}',
        'module' => 'a.module {dir}',
        'action' => 'a.action {dir}',
    ];

    /** @return array{0:string,1:array} klausa WHERE + parameter (alias: a = audit_logs) */
    public static function listWhere(array $f): array
    {
        $sql = [];
        $p   = [];

        foreach (array_slice(preg_split('/\s+/u', trim((string) ($f['q'] ?? ''))) ?: [], 0, 5) as $tok) {
            if ($tok === '') {
                continue;
            }
            $l = Student::like($tok);
            $sql[] = '(a.user_name LIKE ? OR a.action LIKE ? OR a.description LIKE ? OR a.reference_id LIKE ? OR a.ip_address LIKE ?)';
            array_push($p, "%{$l}%", "%{$l}%", "%{$l}%", "{$l}%", "{$l}%");
        }
        if (!empty($f['module']))  { $sql[] = 'a.module = ?';  $p[] = $f['module']; }
        if (!empty($f['user_id'])) { $sql[] = 'a.user_id = ?'; $p[] = (int) $f['user_id']; }
        if (!empty($f['from']))    { $sql[] = 'a.created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) {
            // rentang setengah terbuka agar indeks created_at tetap terpakai
            $sql[] = 'a.created_at < ?';
            $p[]   = (new \DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        }

        return [$sql ? 'WHERE ' . implode(' AND ', $sql) : '', $p];
    }

    /**
     * Daftar log (ringkasan dihitung dari SELURUH hasil filter, bukan hanya halaman ini).
     * @return array{items:array,total:int,summary:array{count:int,users:int,modules:int,latest:?string}}
     */
    public static function paginate(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $params] = self::listWhere($f);
        $dir   = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $order = str_replace('{dir}', $dir, self::LIST_SORTS[$sort] ?? self::LIST_SORTS['time']);

        $agg = Database::fetchOne(
            "SELECT COUNT(*) AS n, COUNT(DISTINCT COALESCE(a.user_id, CONCAT('n:', a.user_name))) AS users,
                    COUNT(DISTINCT a.module) AS modules, MAX(a.created_at) AS latest
               FROM audit_logs a {$where}",
            $params
        ) ?? [];

        $rows = Database::fetchAll(
            "SELECT a.id, a.user_id, a.user_name, a.action, a.module, a.reference_id, a.description, a.ip_address, a.created_at
               FROM audit_logs a {$where}
              ORDER BY {$order}, a.id DESC
              LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, max(0, ($page - 1) * $perPage)])
        );

        $items = array_map(static fn (array $r): array => [
            'id'          => (int) $r['id'],
            'user_id'     => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'user'        => $r['user_name'],
            'action'      => $r['action'],
            'module'      => $r['module'],
            'reference'   => $r['reference_id'],
            'description' => $r['description'],
            'ip'          => $r['ip_address'],
            'created_at'  => $r['created_at'],
        ], $rows);

        $total = (int) ($agg['n'] ?? 0);
        return [
            'items'   => $items,
            'total'   => $total,
            'summary' => [
                'count'   => $total,
                'users'   => (int) ($agg['users'] ?? 0),
                'modules' => (int) ($agg['modules'] ?? 0),
                'latest'  => $agg['latest'] ?? null,
            ],
        ];
    }

    /** Pilihan untuk dropdown filter. @return array{modules:string[],users:array<int,array{id:int,name:string}>} */
    public static function filterOptions(): array
    {
        $modules = array_column(Database::fetchAll('SELECT DISTINCT module FROM audit_logs ORDER BY module'), 'module');
        $users   = Database::fetchAll(
            'SELECT a.user_id AS id, (SELECT x.user_name FROM audit_logs x WHERE x.user_id = a.user_id ORDER BY x.id DESC LIMIT 1) AS name
               FROM audit_logs a WHERE a.user_id IS NOT NULL GROUP BY a.user_id ORDER BY name'
        );
        return [
            'modules' => array_map('strval', $modules),
            'users'   => array_map(static fn (array $u): array => ['id' => (int) $u['id'], 'name' => (string) $u['name']], $users),
        ];
    }
}
