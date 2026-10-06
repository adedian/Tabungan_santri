<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    /** Untuk login: mengembalikan juga hash password. */
    public static function findByLogin(string $identifier): ?array
    {
        return Database::fetchOne(
            'SELECT id, name, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$identifier, $identifier]
        );
    }

    /** Untuk sesi berjalan: tanpa hash password, hanya akun aktif. */
    public static function findActiveById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT id, name, username, email, role, status FROM users WHERE id = ? AND status = 'aktif' LIMIT 1",
            [$id]
        );
    }

    public static function touchLogin(int $id, ?string $newHash = null): void
    {
        if ($newHash !== null) {
            Database::execute('UPDATE users SET last_login_at = NOW(), password = ? WHERE id = ?', [$newHash, $id]);
            return;
        }
        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    private const COLUMNS = 'u.id, u.name, u.username, u.email, u.role, u.status, u.last_login_at, u.created_at';
    private const SORTS = [
        'name'       => 'u.name {dir}',
        'username'   => 'u.username {dir}',
        'role'       => 'u.role {dir}', // urutan ENUM: super_admin < admin < operator
        'status'     => 'u.status {dir}',
        'last_login' => 'u.last_login_at IS NULL, u.last_login_at {dir}',
    ];

    /** @return array{0:string,1:array} */
    private static function where(array $f): array
    {
        $sql = [];
        $p   = [];
        foreach (array_slice(preg_split('/\s+/u', trim((string) ($f['q'] ?? ''))) ?: [], 0, 5) as $tok) {
            if ($tok === '') {
                continue;
            }
            $l = Student::like($tok);
            $sql[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            array_push($p, "%{$l}%", "%{$l}%", "%{$l}%");
        }
        if (!empty($f['role']))   { $sql[] = 'u.role = ?';   $p[] = $f['role']; }
        if (!empty($f['status'])) { $sql[] = 'u.status = ?'; $p[] = $f['status']; }
        return [$sql ? 'WHERE ' . implode(' AND ', $sql) : '', $p];
    }

    /** Daftar pengguna TANPA hash password. @return array{items:array,total:int} */
    public static function paginate(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $params] = self::where($f);
        $dir   = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $order = str_replace('{dir}', $dir, self::SORTS[$sort] ?? self::SORTS['name']);

        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM users u {$where}", $params);
        $rows  = Database::fetchAll(
            'SELECT ' . self::COLUMNS . " FROM users u {$where} ORDER BY {$order}, u.id ASC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, max(0, ($page - 1) * $perPage)])
        );
        return ['items' => array_map([self::class, 'present'], $rows), 'total' => $total];
    }

    public static function find(int $id, bool $lock = false): ?array
    {
        $row = Database::fetchOne('SELECT ' . self::COLUMNS . ' FROM users u WHERE u.id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        return $row ? self::present($row) : null;
    }

    /** Kunci seluruh Super Admin aktif (cegah dua perubahan serentak menghabiskan semuanya). @return int[] */
    public static function lockActiveSuperAdmins(): array
    {
        $rows = Database::fetchAll("SELECT id FROM users WHERE role = 'super_admin' AND status = 'aktif' FOR UPDATE");
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    public static function usernameExists(string $username, ?int $exceptId = null): bool
    {
        return (bool) Database::fetchValue('SELECT 1 FROM users WHERE username = ? AND id <> ? LIMIT 1', [$username, $exceptId ?? 0]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        return (bool) Database::fetchValue('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $exceptId ?? 0]);
    }

    public static function insert(array $d, string $hash): int
    {
        Database::execute(
            'INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)',
            [$d['name'], $d['username'], $d['email'], $hash, $d['role'], $d['status']]
        );
        return Database::lastInsertId();
    }

    public static function updateProfile(int $id, array $d): void
    {
        Database::execute(
            'UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE id = ?',
            [$d['name'], $d['username'], $d['email'], $d['role'], $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function setPassword(int $id, string $hash): void
    {
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $id]);
    }

    private static function present(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'username'      => $r['username'],
            'email'         => $r['email'],
            'role'          => $r['role'],
            'status'        => $r['status'],
            'last_login_at' => $r['last_login_at'],
            'created_at'    => $r['created_at'],
        ];
    }
}
