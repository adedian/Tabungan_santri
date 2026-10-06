<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use Throwable;

final class AuditLog
{
    /**
     * Catat aktivitas. Kegagalan mencatat tidak boleh menggagalkan aksi utama (hanya dilog).
     *
     * @param array|null $actor ['id' => ?int, 'name' => string]; default: pengguna yang sedang login
     */
    public static function record(
        string $action,
        string $module,
        ?string $referenceId = null,
        ?string $description = null,
        ?array $actor = null
    ): void {
        try {
            if ($actor === null) {
                $user  = Auth::user();
                $actor = ['id' => $user['id'] ?? null, 'name' => $user['name'] ?? 'Sistem'];
            }
            $request = Request::current();

            Database::execute(
                'INSERT INTO audit_logs (user_id, user_name, action, module, reference_id, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $actor['id'],
                    mb_substr((string) $actor['name'], 0, 100),
                    mb_substr($action, 0, 100),
                    mb_substr($module, 0, 50),
                    $referenceId !== null ? mb_substr($referenceId, 0, 50) : null,
                    $description !== null ? mb_substr($description, 0, 500) : null,
                    $request?->ip(),
                ]
            );
        } catch (Throwable $e) {
            Logger::exception($e);
        }
    }
}
