<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\AppConfig;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;

/** Allows only the explicitly configured React deployment origins. */
final readonly class CorsMiddleware
{
    public function __construct(private AppConfig $config) {}

    /** Answers browser preflight only for configured frontend origins. */
    public function preflight(Request $request): ?Response
    {
        if ($request->method !== 'OPTIONS' || $request->header('access-control-request-method') === null) {
            return null;
        }

        if (! in_array($request->header('origin'), $this->config->frontendOrigins, true)) {
            throw new ApiException('ORIGIN_FORBIDDEN', 'This browser origin is not allowed.', 403);
        }

        $method = strtoupper($request->header('access-control-request-method'));
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new ApiException('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
        }

        return new Response(204, '', [
            'Content-Type' => 'application/json; charset=utf-8',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE',
            'Access-Control-Allow-Headers' => 'Accept, Authorization, Content-Type',
            'Access-Control-Max-Age' => '600',
        ]);
    }

    /** Adds origin headers to successful and failed API responses alike. */
    public function decorate(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        if ($origin === null || ! in_array($origin, $this->config->frontendOrigins, true)) {
            return $response;
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Vary' => 'Origin',
        ]);
    }
}
