<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApiException;
use Closure;
use InvalidArgumentException;

/** Registers typed API routes and validates their query keys before dispatch. */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: Closure, queryKeys: list<string>, guards: list<Closure>}> */
    private array $routes = [];

    /** @param Closure(Request): Response $handler
     *  @param list<string> $queryKeys
     */
    public function get(string $path, Closure $handler, array $queryKeys = [], array $guards = []): void
    {
        $this->add('GET', $path, $handler, $queryKeys, $guards);
    }

    /** @param Closure(Request): Response $handler */
    public function post(string $path, Closure $handler, array $guards = []): void
    {
        $this->add('POST', $path, $handler, [], $guards);
    }

    /** @param Closure(Request): Response $handler */
    public function put(string $path, Closure $handler, array $guards = []): void
    {
        $this->add('PUT', $path, $handler, [], $guards);
    }

    /** @param Closure(Request): Response $handler */
    public function patch(string $path, Closure $handler, array $guards = []): void
    {
        $this->add('PATCH', $path, $handler, [], $guards);
    }

    /** @param Closure(Request): Response $handler */
    public function delete(string $path, Closure $handler, array $guards = []): void
    {
        $this->add('DELETE', $path, $handler, [], $guards);
    }

    /** @param Closure(Request): Response $handler
     *  @param list<string> $queryKeys
     */
    public function add(string $method, string $path, Closure $handler, array $queryKeys = [], array $guards = []): void
    {
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
            || ! str_starts_with($path, '/api/')) {
            throw new InvalidArgumentException('API route method or path is invalid.');
        }

        $segments = explode('/', ltrim($path, '/'));
        $expression = [];
        $names = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-z][a-z0-9_]*)\}$/', $segment, $matches)) {
                $name = $matches[1];
                if (in_array($name, $names, true)) {
                    throw new InvalidArgumentException('Route parameter names must be unique.');
                }
                $names[] = $name;
                $expression[] = '(?P<'.$name.'>[^/]+)';
            } else {
                $expression[] = preg_quote($segment, '#');
            }
        }

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^/'.implode('/', $expression).'$#D',
            'handler' => $handler,
            'queryKeys' => $queryKeys,
            'guards' => $guards,
        ];
    }

    /** Rejects missing routes and methods before invoking a controller. */
    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];
        foreach ($this->routes as $route) {
            if (! preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $allowedMethods[] = $route['method'];
            if ($request->method !== $route['method']) {
                continue;
            }

            $parameters = [];
            foreach ($matches as $name => $value) {
                if (! is_string($name)) {
                    continue;
                }
                if (preg_match('/%(?![A-Fa-f0-9]{2})/', $value)) {
                    throw new ApiException('VALIDATION_FAILED', 'The route parameter is malformed.', 422);
                }
                $decoded = rawurldecode($value);
                if (str_contains($decoded, '/') || str_contains($decoded, "\0") || ! mb_check_encoding($decoded, 'UTF-8')) {
                    throw new ApiException('VALIDATION_FAILED', 'The route parameter is invalid.', 422);
                }
                $parameters[$name] = $decoded;
            }

            $request->setRouteParameters($parameters);
            $request->validateQuery($route['queryKeys']);
            foreach ($route['guards'] as $guard) {
                $guard($request);
            }

            return ($route['handler'])($request);
        }

        if ($allowedMethods !== []) {
            throw new ApiException('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, [
                'Allow' => implode(', ', array_unique($allowedMethods)),
            ]);
        }

        throw new ApiException('NOT_FOUND', 'Resource not found.', 404);
    }
}
