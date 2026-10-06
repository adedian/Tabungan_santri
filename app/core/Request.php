<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?Request $current = null;

    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $server
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        $uri  = (string) parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri  = rawurldecode($uri);
        $base = self::detectBase($uri, $server);
        Url::setBase($base);

        $path = substr($uri, strlen($base));
        $path = '/' . trim($path === false ? '' : $path, '/');

        $post = $_POST;
        $contentType = $server['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input', false, null, 0, 1048576) ?: '';
            $decoded = json_decode($raw, true);
            $post = is_array($decoded) ? $decoded : [];
        }

        // Method override untuk <form> HTML (PUT/PATCH/DELETE)
        if ($method === 'POST' && isset($post['_method'])) {
            $override = strtoupper((string) $post['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        return self::$current = new self($method, $path, $_GET, $post, $server);
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /** Aplikasi bisa dibuka lewat /Tabungan_santri/ (rewrite root), /Tabungan_santri/public/, atau vhost. */
    private static function detectBase(string $uri, array $server): string
    {
        $configured = Config::get('app.base_path');
        if (is_string($configured)) {
            return rtrim($configured, '/');
        }

        $script     = str_replace('\\', '/', $server['SCRIPT_NAME'] ?? '/index.php');
        $publicBase = rtrim(dirname($script), '/');
        if ($publicBase === '' || $publicBase === '.') {
            return '';
        }
        if ($uri === $publicBase || str_starts_with($uri, $publicBase . '/')) {
            return $publicBase;
        }
        $rootBase = rtrim(dirname($publicBase), '/');
        if ($rootBase !== '' && ($uri === $rootBase || str_starts_with($uri, $rootBase . '/'))) {
            return $rootBase;
        }
        return '';
    }

    public static function isSecure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        return ($https !== '' && strtolower((string) $https) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    /** Gabungan body (prioritas) dan query string. */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = $this->post + $this->query;
        return $key === null ? $all : ($all[$key] ?? $default);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function isMethod(string ...$methods): bool
    {
        return in_array($this->method, $methods, true);
    }

    /** Client mengharapkan JSON (endpoint /api atau fetch dengan Accept JSON). */
    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || stripos($this->header('Accept') ?? '', 'application/json') !== false
            || strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    /** Request polling latar belakang: tidak memperpanjang sesi & tidak menghabiskan flash message. */
    public function isBackgroundPoll(): bool
    {
        return $this->header('X-Background-Poll') === '1';
    }

    /** Hanya REMOTE_ADDR; header X-Forwarded-For sengaja tidak dipercaya (mudah dipalsukan). */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
