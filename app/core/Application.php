<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CanMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

final class Application
{
    public static function run(): void
    {
        Config::load(BASE_PATH . '/app/config');
        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Jakarta'));

        $request = Request::capture();
        ErrorHandler::register();
        Session::start($request);

        $router = new Router();
        $router->aliasMiddleware('csrf', CsrfMiddleware::class);
        $router->aliasMiddleware('auth', AuthMiddleware::class);
        $router->aliasMiddleware('guest', GuestMiddleware::class);
        $router->aliasMiddleware('can', CanMiddleware::class);
        $router->globalMiddleware(['csrf']); // aman secara default: semua POST/PUT/DELETE wajib token

        require BASE_PATH . '/routes/web.php';

        $router->dispatch($request)->send();
    }
}
