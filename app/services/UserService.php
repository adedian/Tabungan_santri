<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\AuditLog;
use App\Models\User;
use PDOException;

/**
 * Aturan bisnis manajemen pengguna. Perlindungan:
 *  - akun sendiri tidak boleh dinonaktifkan / diturunkan perannya (mencegah mengunci diri),
 *  - Super Admin aktif terakhir tidak boleh dinonaktifkan / diturunkan (dicek di dalam transaksi + kunci baris),
 *  - kata sandi tidak pernah dicatat ke audit log maupun dikembalikan ke klien.
 */
final class UserService
{
    public const PER_PAGE = [10, 25, 50];
    public const ROLES    = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'operator' => 'Operator'];
    public const PASSWORD_MIN = 8;

    public static function filtersFromQuery(array $q): array
    {
        $sort = in_array($q['sort'] ?? '', ['name', 'username', 'role', 'status', 'last_login'], true) ? (string) $q['sort'] : 'name';
        $per  = (int) ($q['per_page'] ?? 25);
        return [
            'q'        => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($q['q'] ?? ''))), 0, 100),
            'role'     => isset(self::ROLES[$q['role'] ?? '']) ? (string) $q['role'] : '',
            'status'   => in_array($q['status'] ?? '', ['aktif', 'nonaktif'], true) ? (string) $q['status'] : '',
            'sort'     => $sort,
            'dir'      => strtolower((string) ($q['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            'page'     => max(1, (int) ($q['page'] ?? 1)),
            'per_page' => in_array($per, self::PER_PAGE, true) ? $per : 25,
        ];
    }

    public static function listing(array $query): array
    {
        $f   = self::filtersFromQuery($query);
        $res = User::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);

        $pages = max(1, (int) ceil($res['total'] / $f['per_page']));
        if ($f['page'] > $pages) {
            $f['page'] = $pages;
            $res = User::paginate($f, $f['sort'], $f['dir'], $f['page'], $f['per_page']);
        }
        return [
            'items'   => $res['items'],
            'total'   => $res['total'],
            'page'    => $f['page'],
            'pages'   => $pages,
            'filters' => $f,
            'roles'   => self::ROLES,
            'me'      => Auth::id(),
        ];
    }

    /** @return array{ok:bool, errors?:array<string,string>, user?:array} */
    public static function create(array $in, array $actor): array
    {
        [$d, $errors] = self::clean($in, null);
        $pw = self::checkPassword($in, 'password', $errors, $d['username']);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $id = Database::transaction(function () use ($d, $pw, $actor): int {
                $id = User::insert($d + ['status' => 'aktif'], password_hash($pw, PASSWORD_DEFAULT));
                AuditLog::record('Menambahkan pengguna', 'Pengguna', $d['username'], $d['name'] . ' — ' . self::ROLES[$d['role']], $actor);
                return $id;
            });
        } catch (PDOException $e) {
            if ($dup = self::duplicateError($e)) {
                return ['ok' => false, 'errors' => $dup];
            }
            throw $e;
        }
        return ['ok' => true, 'user' => User::find($id)];
    }

    /** @return array{ok:bool, notfound?:bool, errors?:array<string,string>, message?:string, user?:array} */
    public static function update(int $id, array $in, array $actor): array
    {
        $old = User::find($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        [$d, $errors] = self::clean($in, $old);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        if ($id === (int) $actor['id'] && $d['role'] !== $old['role']) {
            return ['ok' => false, 'errors' => ['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']];
        }

        try {
            $blocked = Database::transaction(function () use ($id, $d, $old, $actor): ?string {
                $locked = User::lockActiveSuperAdmins();
                $cur    = User::find($id, true);
                if ($cur === null) {
                    return 'notfound';
                }
                if ($cur['role'] === 'super_admin' && $d['role'] !== 'super_admin' && $cur['status'] === 'aktif' && $locked === [$id]) {
                    return 'Super Admin aktif terakhir tidak dapat diturunkan perannya.';
                }
                User::updateProfile($id, $d);
                $changes = self::diff($old, $d);
                if ($changes !== []) {
                    AuditLog::record('Mengubah pengguna', 'Pengguna', $old['username'], $old['name'] . ': ' . implode('; ', $changes), $actor);
                }
                return null;
            });
        } catch (PDOException $e) {
            if ($dup = self::duplicateError($e)) {
                return ['ok' => false, 'errors' => $dup];
            }
            throw $e;
        }

        if ($blocked === 'notfound') {
            return ['ok' => false, 'notfound' => true];
        }
        if ($blocked !== null) {
            return ['ok' => false, 'errors' => ['role' => $blocked], 'message' => $blocked];
        }
        return ['ok' => true, 'user' => User::find($id)];
    }

    /** @return array{ok:bool, notfound?:bool, message?:string, user?:array} */
    public static function setStatus(int $id, string $status, array $actor): array
    {
        $old = User::find($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        if ($status === 'nonaktif' && $id === (int) $actor['id']) {
            return ['ok' => false, 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'];
        }

        $blocked = Database::transaction(function () use ($id, $status, $actor): ?string {
            $locked = User::lockActiveSuperAdmins();
            $cur    = User::find($id, true);
            if ($cur === null) {
                return 'notfound';
            }
            if ($status === 'nonaktif' && $cur['role'] === 'super_admin' && $cur['status'] === 'aktif' && $locked === [$id]) {
                return 'Super Admin aktif terakhir tidak dapat dinonaktifkan.';
            }
            if ($cur['status'] !== $status) {
                User::setStatus($id, $status);
                AuditLog::record($status === 'aktif' ? 'Mengaktifkan pengguna' : 'Menonaktifkan pengguna', 'Pengguna', $cur['username'], $cur['name'], $actor);
            }
            return null;
        });

        if ($blocked === 'notfound') {
            return ['ok' => false, 'notfound' => true];
        }
        if ($blocked !== null) {
            return ['ok' => false, 'message' => $blocked];
        }
        return ['ok' => true, 'user' => User::find($id)];
    }

    /** @return array{ok:bool, notfound?:bool, errors?:array<string,string>, user?:array} */
    public static function resetPassword(int $id, array $in, array $actor): array
    {
        $old = User::find($id);
        if ($old === null) {
            return ['ok' => false, 'notfound' => true];
        }
        $errors = [];
        $pw = self::checkPassword($in, 'password', $errors, $old['username']);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        Database::transaction(static function () use ($id, $pw, $old, $actor): void {
            User::setPassword($id, password_hash($pw, PASSWORD_DEFAULT));
            AuditLog::record('Mengatur ulang kata sandi', 'Pengguna', $old['username'], $old['name'], $actor);
        });
        return ['ok' => true, 'user' => $old];
    }

    /**
     * Validasi & normalisasi profil. $existing = null saat membuat baru.
     * @return array{0:array,1:array<string,string>}
     */
    private static function clean(array $in, ?array $existing): array
    {
        $e  = [];
        $id = $existing['id'] ?? null;
        $str = static fn (string $k): string => trim((string) preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? '')));

        $name = $str('name');
        if ($name === '') {
            $e['name'] = 'Nama wajib diisi.';
        } elseif (mb_strlen($name) > 100) {
            $e['name'] = 'Nama maksimal 100 karakter.';
        }

        $username = strtolower(trim((string) ($in['username'] ?? '')));
        if ($username === '') {
            $e['username'] = 'Username wajib diisi.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/', $username)) {
            $e['username'] = 'Username 3–50 karakter: huruf kecil, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (User::usernameExists($username, $id)) {
            $e['username'] = 'Username sudah dipakai.';
        }

        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if ($email === '') {
            $email = null;
        } elseif (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Email tidak valid.';
        } elseif (User::emailExists($email, $id)) {
            $e['email'] = 'Email sudah dipakai.';
        }

        $role = (string) ($in['role'] ?? '');
        if (!isset(self::ROLES[$role])) {
            $e['role'] = 'Peran wajib dipilih.';
        }

        return [['name' => $name, 'username' => $username, 'email' => $email, 'role' => $role], $e];
    }

    /** Kata sandi tidak di-trim. Mengembalikan nilai; menambahkan pesan ke $errors bila tidak sah. */
    private static function checkPassword(array $in, string $key, array &$errors, string $username): string
    {
        $pw = (string) ($in[$key] ?? '');
        if ($pw === '') {
            $errors[$key] = 'Kata sandi wajib diisi.';
        } elseif (mb_strlen($pw) < self::PASSWORD_MIN) {
            $errors[$key] = 'Kata sandi minimal ' . self::PASSWORD_MIN . ' karakter.';
        } elseif (strlen($pw) > 72) { // batas input bcrypt; lebih dari ini terpotong diam-diam
            $errors[$key] = 'Kata sandi maksimal 72 byte.';
        } elseif (strtolower($pw) === $username) {
            $errors[$key] = 'Kata sandi tidak boleh sama dengan username.';
        }
        return $pw;
    }

    private static function duplicateError(PDOException $e): ?array
    {
        if ($e->getCode() !== '23000') {
            return null;
        }
        if (str_contains($e->getMessage(), 'uq_users_username')) {
            return ['username' => 'Username sudah dipakai.'];
        }
        if (str_contains($e->getMessage(), 'uq_users_email')) {
            return ['email' => 'Email sudah dipakai.'];
        }
        return null;
    }

    /** @return string[] ringkasan perubahan untuk audit log (tanpa data sensitif) */
    private static function diff(array $old, array $new): array
    {
        $out = [];
        foreach (['name' => 'nama', 'username' => 'username', 'email' => 'email'] as $k => $label) {
            if (($old[$k] ?? null) !== ($new[$k] ?? null)) {
                $out[] = "{$label} “" . ($old[$k] ?? '—') . '” → “' . ($new[$k] ?? '—') . '”';
            }
        }
        if ($old['role'] !== $new['role']) {
            $out[] = 'peran ' . self::ROLES[$old['role']] . ' → ' . self::ROLES[$new['role']];
        }
        return $out;
    }
}
