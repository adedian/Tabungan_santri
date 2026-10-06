<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /** Panggil $next($request) untuk melanjutkan, atau kembalikan Response untuk menghentikan. */
    public function handle(Request $request, callable $next): Response;
}
