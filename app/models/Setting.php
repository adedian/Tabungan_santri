<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Pengaturan key/value; dimuat sekali per request (tabel kecil). */
final class Setting
{
    private static ?array $all = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
                self::$all[$row['setting_key']] = $row['setting_value'];
            }
        }
        $value = self::$all[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }
}
