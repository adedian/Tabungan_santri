<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        foreach (glob(rtrim($dir, '/\\') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (str_contains($name, '.example')) {
                continue;
            }
            self::$items[$name] = require $file;
        }
    }

    /** Ambil nilai dengan notasi titik: Config::get('app.session.name') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
