<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Penghitung revisi untuk polling realtime. Setiap perubahan data memanggil bump() DI DALAM
 * transaksi database yang sama dengan perubahannya, sehingga klien yang melihat revisi baru
 * pasti melihat data baru juga.
 */
final class SyncState
{
    public const SCOPES = ['savings', 'students'];

    /** Jumlah revisi dari scope yang diminta (naik terus; cukup dibandingkan sama/beda). */
    public static function revision(array $scopes): int
    {
        $scopes = array_values(array_intersect($scopes, self::SCOPES));
        if ($scopes === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($scopes), '?'));
        return (int) Database::fetchValue("SELECT COALESCE(SUM(revision), 0) FROM sync_state WHERE scope IN ({$in})", $scopes);
    }

    public static function bump(string ...$scopes): void
    {
        foreach ($scopes as $scope) {
            if (in_array($scope, self::SCOPES, true)) {
                Database::execute('UPDATE sync_state SET revision = revision + 1 WHERE scope = ?', [$scope]);
            }
        }
    }
}
