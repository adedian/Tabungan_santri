<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Otorisasi per izin, mis. 'can:savings.edit'. Gunakan SETELAH 'auth'. */
final class CanMiddleware implements MiddlewareInterface
{
    private array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }

    public function handle(Request $request, callable $next): Response
    {
        foreach ($this->permissions as $permission) {
            if (!Auth::can($permission)) {
                throw new HttpException(403);
            }
        }
        return $next($request);
    }
}
