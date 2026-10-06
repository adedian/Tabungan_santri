<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Pembatasan percobaan login (jendela geser 15 menit):
 *  - 5 kegagalan per kombinasi (identifier + IP)  -> terkunci sementara
 *  - 20 kegagalan per IP                          -> terkunci sementara
 * Kunci memakai IP sehingga orang lain tidak bisa mengunci akun pengguna sah dari IP berbeda.
 */
final class LoginThrottle
{
    public const MAX_PER_IDENTIFIER = 5;
    public const MAX_PER_IP = 20;
    private const WINDOW_MINUTES = 15;

    public static function normalize(string $identifier): string
    {
        return mb_strtolower(mb_substr(trim($identifier), 0, 150));
    }

    /** @return int|null sisa detik terkunci, atau null jika boleh mencoba */
    public static function lockedFor(string $identifier, string $ip): ?int
    {
        $a = self::lockSeconds('identifier = ? AND ip_address = ?', [self::normalize($identifier), $ip], self::MAX_PER_IDENTIFIER);
        $b = self::lockSeconds('ip_address = ?', [$ip], self::MAX_PER_IP);
        if ($a === null && $b === null) {
            return null;
        }
        return max($a ?? 0, $b ?? 0);
    }

    /** Catat kegagalan; kembalikan true bila percobaan ini memicu penguncian. */
    public static function fail(string $identifier, string $ip): bool
    {
        Database::execute('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)', [self::normalize($identifier), $ip]);

        if (random_int(1, 50) === 1) { // bersih-bersih berkala
            Database::execute('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        }
        return self::count('identifier = ? AND ip_address = ?', [self::normalize($identifier), $ip]) === self::MAX_PER_IDENTIFIER;
    }

    public static function clear(string $identifier, string $ip): void
    {
        Database::execute('DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ?', [self::normalize($identifier), $ip]);
    }

    public static function minutes(int $seconds): int
    {
        return max(1, (int) ceil($seconds / 60));
    }

    private static function count(string $where, array $params): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM login_attempts WHERE {$where} AND attempted_at > (NOW() - INTERVAL " . self::WINDOW_MINUTES . ' MINUTE)',
            $params
        );
    }

    /** $where hanya berisi fragmen statis dari kode ini (bukan input pengguna). */
    private static function lockSeconds(string $where, array $params, int $max): ?int
    {
        if (self::count($where, $params) < $max) {
            return null;
        }
        // Terbuka kembali saat percobaan ke-$max (dari yang terbaru) keluar dari jendela waktu.
        $sec = Database::fetchValue(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), attempted_at + INTERVAL " . self::WINDOW_MINUTES . " MINUTE)
               FROM login_attempts
              WHERE {$where} AND attempted_at > (NOW() - INTERVAL " . self::WINDOW_MINUTES . " MINUTE)
              ORDER BY attempted_at DESC LIMIT 1 OFFSET ?",
            array_merge($params, [$max - 1])
        );
        return max(1, (int) $sec);
    }
}
