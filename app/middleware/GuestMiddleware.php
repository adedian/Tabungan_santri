<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/** Halaman khusus tamu (mis. /login); pengguna yang sudah login diarahkan ke dashboard. */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return Auth::check() ? Response::redirect('/dashboard') : $next($request);
    }
}
