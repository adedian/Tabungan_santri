<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\LoginThrottle;

final class AuthController extends Controller
{
    /** Hash bcrypt acak: dipakai agar waktu respons sama baik username ada maupun tidak. */
    private const DUMMY_HASH = '$2y$10$MVTTNAQLF9HcWIDQak6zdOYuojyuzPMZS9QZjCPhaqpSZiTxgpzfi';

    private const GENERIC_ERROR = 'Username/email atau kata sandi salah.';

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', [
            'expired' => (bool) Session::pull('_expired', false),
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $identifier = mb_substr(trim((string) $request->post('identifier', '')), 0, 150);
        $password   = (string) $request->post('password', ''); // jangan di-trim
        $ip         = $request->ip();

        if ($identifier === '' || $password === '') {
            return $this->back('Username/email dan kata sandi wajib diisi.', $identifier);
        }

        $locked = LoginThrottle::lockedFor($identifier, $ip);
        if ($locked !== null) {
            return $this->back(
                'Terlalu banyak percobaan login. Coba lagi dalam ' . LoginThrottle::minutes($locked) . ' menit.',
                $identifier
            );
        }

        $user  = User::findByLogin($identifier);
        $valid = password_verify($password, $user['password'] ?? self::DUMMY_HASH) && $user !== null;

        if (!$valid) {
            $nowLocked = LoginThrottle::fail($identifier, $ip);
            // Hanya catat identifier bila cocok dengan akun nyata (kalau tidak, bisa jadi itu kata sandi yang salah ketik).
            AuditLog::record(
                'Login gagal',
                'Auth',
                null,
                ($user ? "Akun {$user['username']}" : 'Akun tidak dikenal') . ($nowLocked ? ' — dikunci sementara' : ''),
                ['id' => $user['id'] ?? null, 'name' => $user['name'] ?? '(tidak dikenal)']
            );
            return $this->back(self::GENERIC_ERROR, $identifier);
        }

        if ($user['status'] !== 'aktif') {
            AuditLog::record('Login ditolak (akun nonaktif)', 'Auth', null, "Akun {$user['username']}", ['id' => $user['id'], 'name' => $user['name']]);
            return $this->back('Akun Anda dinonaktifkan. Hubungi administrator.', $identifier);
        }

        $intended = Session::get('intended');
        Auth::login($user);
        Session::forget('intended');
        LoginThrottle::clear($identifier, $ip);
        User::touchLogin(
            (int) $user['id'],
            password_needs_rehash($user['password'], PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : null
        );
        AuditLog::record('Login', 'Auth', null, 'Berhasil masuk');

        return Response::redirect(is_string($intended) && $intended !== '' ? $intended : '/dashboard');
    }

    public function logout(Request $request): Response
    {
        AuditLog::record('Logout', 'Auth');
        Auth::logout();
        Session::flash('success', 'Anda telah keluar.');
        return Response::redirect('/login');
    }

    private function back(string $message, string $identifier): Response
    {
        Session::flash('error', $message);
        Session::flash('old', ['identifier' => $identifier]);
        return Response::redirect('/login');
    }
}
