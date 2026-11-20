<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApiException;

/** A small, typed view of the incoming HTTP request. */
final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;

    /** @var array<string, string> */
    private array $queryParameters = [];

    /** @var array<string, string> */
    private array $routeParameters = [];

    /** @var array<string, mixed>|null */
    private ?array $actor = null;

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $rawQuery,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly string $clientIp,
    ) {}

    /** Captures only the incoming fields needed by the API boundary. */
    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $query = parse_url($uri, PHP_URL_QUERY);
        if (! is_string($path) || ! is_string($uri)) {
            throw new ApiException('BAD_REQUEST', 'The request URI is invalid.', 400);
        }

        $headers = [];
        foreach (getallheaders() ?: [] as $name => $value) {
            $headers[strtolower($name)] = $value;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            is_string($query) ? $query : '',
            $headers,
            file_get_contents('php://input', false, null, 0, 65537) ?: '',
            filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown',
        );
    }

    /** Looks up a header without depending on client capitalization. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @param array<string, mixed> $body */
    public function setJsonBody(array $body): void
    {
        $this->jsonBody = $body;
    }

    /** @return array<string, mixed> Validated JSON for a feature controller. */
    public function jsonBody(): array
    {
        return $this->jsonBody ?? [];
    }

    /** Rejects malformed, duplicate, and undeclared query keys before a controller runs.
     *  @param list<string> $allowedKeys
     */
    public function validateQuery(array $allowedKeys): void
    {
        if ($this->rawQuery === '') {
            return;
        }
        if (strlen($this->rawQuery) > 2048) {
            throw new ApiException('VALIDATION_FAILED', 'The query string is too long.', 422);
        }

        $parameters = [];
        foreach (explode('&', $this->rawQuery) as $pair) {
            if ($pair === '' || preg_match('/%(?![A-Fa-f0-9]{2})/', $pair)) {
                throw new ApiException('VALIDATION_FAILED', 'The query string is malformed.', 422);
            }
            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($rawKey);
            $value = urldecode($rawValue);
            if (! preg_match('/^[a-z][a-z0-9_]*$/D', $key)
                || ! mb_check_encoding($value, 'UTF-8')
                || preg_match('/[\x00-\x1F\x7F]/', $value)
                || ! in_array($key, $allowedKeys, true)
                || array_key_exists($key, $parameters)) {
                throw new ApiException('VALIDATION_FAILED', 'The query string contains an invalid or duplicate parameter.', 422);
            }
            $parameters[$key] = $value;
        }

        $this->queryParameters = $parameters;
    }

    /** @return array<string, string> The allowlisted query values for this route. */
    public function queryParameters(): array
    {
        return $this->queryParameters;
    }

    /** @param array<string, string> $parameters Route values selected by the router. */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    /** Returns a decoded route value for feature-specific validation. */
    public function routeParameter(string $name): ?string
    {
        return $this->routeParameters[$name] ?? null;
    }

    /** @param array<string, mixed> $actor Authenticated user profile. */
    public function setActor(array $actor): void
    {
        $this->actor = $actor;
    }

    /** @return array<string, mixed> */
    public function actor(): array
    {
        if ($this->actor === null) {
            throw new ApiException('UNAUTHORIZED', 'Authentication is required.', 401);
        }

        return $this->actor;
    }
}
