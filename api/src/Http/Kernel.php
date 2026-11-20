<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ErrorHandler;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\JsonMiddleware;
use App\Infrastructure\FileRateLimiter;
use Throwable;

/** Runs shared request protections before any route or feature controller. */
final readonly class Kernel
{
    public function __construct(
        private Router $router,
        private CorsMiddleware $cors,
        private JsonMiddleware $json,
        private FileRateLimiter $rateLimiter,
        private ErrorHandler $errors,
    ) {}

    /** Applies the same protections and error contract to every route. */
    public function handle(Request $request): Response
    {
        try {
            $preflight = $this->cors->preflight($request);
            if ($preflight !== null) {
                return $this->cors->decorate($request, $preflight);
            }

            $this->rateLimiter->check($request->clientIp);
            $this->json->validate($request);
            $response = $this->router->dispatch($request);
        } catch (Throwable $error) {
            $response = $this->errors->render($error);
        }

        return $this->cors->decorate($request, $response);
    }
}
