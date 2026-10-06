<?php
declare(strict_types=1);

namespace App\Core;

/** Pembangun URL yang sadar base path (aplikasi bisa berjalan di sub-folder maupun vhost). */
final class Url
{
    private static string $base = '';

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    public static function to(string $path = '/'): string
    {
        return self::$base . '/' . ltrim($path, '/');
    }

    /** URL aset dengan cache-busting berdasarkan waktu modifikasi file. */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = BASE_PATH . '/public/assets/' . $path;
        $v    = is_file($file) ? (string) filemtime($file) : '0';
        return self::to('assets/' . $path) . '?v=' . $v;
    }
}
