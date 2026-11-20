<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exception\ApiException;
use App\Http\Request;
use JsonException;

/** Rejects oversized, malformed, and ambiguous JSON write bodies. */
final class JsonMiddleware
{
    /** Parses write bodies once so controllers receive a bounded JSON object. */
    public function validate(Request $request): void
    {
        if (in_array($request->method, ['GET', 'DELETE'], true) && $request->rawBody !== '') {
            throw new ApiException('VALIDATION_FAILED', 'This endpoint does not accept a request body.', 422);
        }
        if (! in_array($request->method, ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }
        if (strlen($request->rawBody) > 65536) {
            throw new ApiException('PAYLOAD_TOO_LARGE', 'The JSON body exceeds 64 KiB.', 413);
        }
        if (! preg_match('~^application/json(?:\s*;\s*charset=utf-8)?$~i', $request->header('content-type') ?? '')) {
            throw new ApiException('UNSUPPORTED_MEDIA_TYPE', 'Send an application/json request body.', 415);
        }
        if (! str_starts_with(ltrim($request->rawBody), '{')) {
            throw new ApiException('VALIDATION_FAILED', 'The JSON body must be an object.', 422);
        }

        try {
            $body = json_decode($request->rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('INVALID_JSON', 'The JSON body is malformed.', 400);
        }
        if (! is_array($body)) {
            throw new ApiException('VALIDATION_FAILED', 'The JSON body must be an object.', 422);
        }

        $request->setJsonBody($body);
    }
}
