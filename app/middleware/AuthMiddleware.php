<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Hanya untuk pengguna yang sudah login. */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw new HttpException(401); // JS mengarahkan ke /login
        }

        if ($request->isMethod('GET') && $request->path() !== '/logout') {
            Session::set('intended', $request->path());
        }
        return Response::redirect('/login');
    }
}
