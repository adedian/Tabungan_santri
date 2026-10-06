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
}
