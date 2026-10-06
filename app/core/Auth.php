<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/** Status login berbasis sesi. Pengguna dimuat ulang dari DB tiap request (akun dinonaktifkan = langsung keluar). */
final class Auth
{
    private const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        'operator'    => 'Operator',
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    /** Dipanggil setelah kredensial terverifikasi. */
    public static function login(array $user): void
    {
        Session::regenerate(); // cegah session fixation
        Session::set('auth_user_id', (int) $user['id']);
        Csrf::rotate();
        unset($user['password']);
        self::$user   = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::flush();
        Csrf::rotate();
        self::$user   = null;
        self::$loaded = true;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = Session::get('auth_user_id');
        if (!is_int($id)) {
            return self::$user = null;
        }
        self::$user = User::findActiveById($id);
        if (self::$user === null) {
            Session::forget('auth_user_id'); // akun dihapus/dinonaktifkan
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function roleLabel(?string $role = null): string
    {
        return self::ROLE_LABELS[$role ?? (string) self::role()] ?? '-';
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }
        if ($role === 'super_admin') {
            return true;
        }
        $map = (array) Config::get('permissions', []);
        return in_array($role, $map[$permission] ?? [], true);
    }
}
