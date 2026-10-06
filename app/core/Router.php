<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;

final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:mixed, middleware:array}> */
    private array $routes = [];
    private array $groups = [];
    private array $aliases = [];
    private array $global = [];

    public function aliasMiddleware(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /** Middleware yang dijalankan untuk SEMUA route (mis. csrf). */
    public function globalMiddleware(array $middleware): void
    {
        $this->global = $middleware;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groups[] = [
            'prefix'     => $attributes['prefix'] ?? '',
            'middleware' => $attributes['middleware'] ?? [],
        ];
        $callback($this);
        array_pop($this->groups);
    }

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware): void
    {
        $prefix = '';
        $groupMw = [];
        foreach ($this->groups as $g) {
            $prefix .= '/' . trim($g['prefix'], '/');
            $groupMw = array_merge($groupMw, $g['middleware']);
        }
        $full = '/' . trim($prefix . '/' . trim($path, '/'), '/');

        $this->routes[] = [
            'method'     => $method,
            'regex'      => $this->compile($full),
            'handler'    => $handler,
            'middleware' => array_merge($groupMw, $middleware),
        ];
    }

    /** {id:int} -> \d+, {slug} atau {slug:any} -> [^/]+ */
    private function compile(string $path): string
    {
        $parts = preg_split('#(\{\w+(?::\w+)?\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('#^\{(\w+)(?::(\w+))?\}$#', $part, $m)) {
                $pattern = ($m[2] ?? 'any') === 'int' ? '\d+' : '[^/]+';
                $regex .= '(?P<' . $m[1] . '>' . $pattern . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        return '#^' . $regex . '$#';
    }

    public function dispatch(Request $request): Response
    {
        $method  = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($m as $key => $value) {
                if (is_string($key)) {
                    $params[] = ctype_digit($value) && strlen($value) < 10 ? (int) $value : $value;
                }
            }

            $core = fn (Request $r): Response => $this->invoke($route['handler'], $r, $params);
            return $this->runPipeline(array_merge($this->global, $route['middleware']), $request, $core);
        }

        if ($allowed) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        throw new HttpException(404);
    }

    private function runPipeline(array $middleware, Request $request, callable $core): Response
    {
        $next = $core;
        foreach (array_reverse($middleware) as $spec) {
            $instance = $this->makeMiddleware($spec);
            $next = static fn (Request $r): Response => $instance->handle($r, $next);
        }
        return $next($request);
    }

    private function makeMiddleware(string $spec): MiddlewareInterface
    {
        [$name, $args] = array_pad(explode(':', $spec, 2), 2, '');
        $class = $this->aliases[$name] ?? null;
        if ($class === null) {
            throw new \LogicException("Middleware '{$name}' belum terdaftar.");
        }
        return new $class(...($args === '' ? [] : explode(',', $args)));
    }

    private function invoke(mixed $handler, Request $request, array $params): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0](), $handler[1]];
        }
        $result = $handler($request, ...$params);

        return match (true) {
            $result instanceof Response => $result,
            is_array($result)           => Response::json($result),
            is_string($result)          => Response::html($result),
            default                     => Response::noContent(),
        };
    }
}
