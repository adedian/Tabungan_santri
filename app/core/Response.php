<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(array $payload, int $status = 200): self
    {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return new self($body === false ? '{"success":false,"message":"Terjadi kesalahan.","data":null}' : $body, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    /** Format JSON konsisten: {success, message, data} */
    public static function success(mixed $data = null, string $message = '', int $status = 200): self
    {
        return self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = [], mixed $data = null): self
    {
        $payload = ['success' => false, 'message' => $message, 'data' => $data];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        return self::json($payload, $status);
    }

    /** Hanya path internal; mencegah open redirect. */
    public static function redirect(string $path, int $status = 302): self
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
            $path = '/';
        }
        return new self('', $status, ['Location' => Url::to($path)]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach (self::defaultHeaders() as $name => $value) {
                header($name . ': ' . $value);
            }
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }

    /**
     * Header keamanan untuk semua respons dinamis.
     * CSP: tidak ada inline <script> (data dikirim lewat data-attribute / JSON script block).
     */
    private static function defaultHeaders(): array
    {
        return [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'same-origin',
            'Cache-Control'           => 'no-store',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ];
    }
}
