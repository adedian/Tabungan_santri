<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Menolak POST/PUT/PATCH/DELETE tanpa token CSRF yang valid (field `_csrf` atau header X-CSRF-Token). */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->isMethod('GET', 'HEAD', 'OPTIONS')) {
            return $next($request);
        }

        $token = $request->header('X-CSRF-Token') ?? $request->post('_csrf');
        if (Csrf::verify(is_string($token) ? $token : null)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            // Bukan 419: kode itu non-standar dan Apache menggantinya menjadi 500.
            throw new HttpException(403, 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba kembali.');
        }

        // Form biasa (mis. halaman login dibiarkan terbuka sampai sesi habis): kembali dengan pesan ramah.
        Session::flash('error', 'Sesi formulir telah berakhir. Silakan coba kembali.');
        return Response::redirect(Auth::check() ? '/dashboard' : '/login');
    }
}
