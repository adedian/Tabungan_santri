<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return Response::html(View::render($view, $data, $layout));
    }

    protected function success(mixed $data = null, string $message = '', int $status = 200): Response
    {
        return Response::success($data, $message, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): Response
    {
        return Response::error($message, $status, $errors);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }
}
