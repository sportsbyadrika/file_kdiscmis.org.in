<?php
declare(strict_types=1);

namespace App;

/**
 * Tiny exact-match router (sufficient for this app's fixed route set).
 *
 * Routes are registered per HTTP method against an exact path, mapped to
 * a [ControllerClass::class, 'method'] handler.
 */
final class Router
{
    /** @var array<string, array<string, callable|array>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array|callable $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** Resolve the request path relative to the configured base URL. */
    private function currentPath(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::get('app.base_url', ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return $this->normalize($uri === '' ? '/' : $uri);
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = $this->currentPath();

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            // Distinguish 405 from 404 where helpful.
            $existsOtherMethod = isset($this->routes['GET'][$path]) || isset($this->routes['POST'][$path]);
            http_response_code($existsOtherMethod ? 405 : 404);
            $this->renderError($existsOtherMethod ? 405 : 404);
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            (new $class())->{$action}();
            return;
        }
        $handler();
    }

    private function renderError(int $code): void
    {
        $titles = [404 => 'Page Not Found', 405 => 'Method Not Allowed'];
        $title  = $titles[$code] ?? 'Error';
        if (Auth::check()) {
            View::render('placeholder', [
                'pageTitle' => $title,
                'active'    => '',
                'heading'   => $code . ' — ' . $title,
                'icon'      => 'bi-exclamation-triangle',
                'stage'     => '',
                'note'      => 'The page you requested could not be found.',
            ]);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo $code . ' ' . $title;
        }
    }
}
