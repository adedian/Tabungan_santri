<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name        = (string) Config::get('app.session.name', 'TABSESS');
        $idleTimeout = (int) Config::get('app.session.idle_timeout', 7200);

        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $idleTimeout);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_save_path($dir);
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Url::base() === '' ? '/' : Url::base() . '/',
            'secure'   => Request::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $background = $request->isBackgroundPoll();

        // Sesi kedaluwarsa karena tidak aktif
        $last = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $idleTimeout) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_expired'] = true;
        }
        if (!$background) {
            $_SESSION['_last_activity'] = time();
        }

        // Flash: data "berikutnya" menjadi "saat ini" pada request halaman biasa
        if (!$background && !$request->wantsJson()) {
            $_SESSION['_flash']      = $_SESSION['_flash_next'] ?? [];
            $_SESSION['_flash_next'] = [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** Simpan untuk ditampilkan pada halaman berikutnya (setelah redirect). */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public static function flashed(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Kosongkan seluruh data sesi dan ganti ID (dipakai saat logout; sesi tetap hidup untuk pesan flash). */
    public static function flush(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    /** Lepas session lock (penting untuk endpoint polling agar tidak memblokir request lain). */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
