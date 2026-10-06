<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private const MESSAGES = [
        401 => ['Perlu login', 'Sesi Anda berakhir. Silakan login kembali.'],
        403 => ['Akses ditolak', 'Anda tidak memiliki izin untuk membuka halaman ini.'],
        404 => ['Halaman tidak ditemukan', 'Alamat yang Anda tuju tidak tersedia atau sudah dipindahkan.'],
        405 => ['Metode tidak diizinkan', 'Permintaan tidak dapat diproses dengan cara ini.'],
        429 => ['Terlalu banyak percobaan', 'Silakan tunggu beberapa saat sebelum mencoba kembali.'],
        500 => ['Terjadi kesalahan', 'Silakan coba kembali.'],
    ];

    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            Logger::warning("Deprecated: {$message}", ['file' => "{$file}:{$line}"]);
            return true;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status() : 500;

        if ($status >= 500) {
            Logger::exception($e);
        }

        try {
            self::render($e, $status)->withHeader('X-Robots-Tag', 'noindex')->send();
        } catch (Throwable $inner) {
            Logger::exception($inner);
            if (!headers_sent()) {
                http_response_code($status);
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo 'Terjadi kesalahan. Silakan coba kembali.';
        }
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        Logger::error('Fatal: ' . $err['message'], ['file' => $err['file'] . ':' . $err['line']]);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Terjadi kesalahan. Silakan coba kembali.';
        }
    }

    private static function render(Throwable $e, int $status): Response
    {
        [$title, $message] = self::MESSAGES[$status] ?? self::MESSAGES[500];
        $debug = (bool) Config::get('app.debug', false);

        if ($e instanceof HttpException && $e->getMessage() !== '') {
            $message = $e->getMessage();
        }
        $detail = $debug && $status >= 500 ? get_class($e) . ': ' . $e->getMessage() : null;

        $request = Request::current();
        if ($request !== null && $request->wantsJson()) {
            $response = Response::error($message . ($detail ? " [{$detail}]" : ''), $status);
        } else {
            $response = Response::html(View::render('errors/error', [
                'code'    => $status,
                'title'   => $title,
                'message' => $message,
                'detail'  => $detail,
            ], 'layouts/plain'), $status);
        }

        if ($e instanceof HttpException) {
            foreach ($e->headers() as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }
        return $response;
    }
}
