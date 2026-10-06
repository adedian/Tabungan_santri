<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf')) {
            Session::set('_csrf', bin2hex(random_bytes(32)));
        }
        return (string) Session::get('_csrf');
    }

    public static function verify(?string $supplied): bool
    {
        $expected = (string) Session::get('_csrf', '');
        return $expected !== '' && $supplied !== null && hash_equals($expected, $supplied);
    }

    /** Ganti token (panggil setelah login/logout). */
    public static function rotate(): void
    {
        Session::forget('_csrf');
        self::token();
    }
}
