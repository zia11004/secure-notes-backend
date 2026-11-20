<?php

declare(strict_types=1);

namespace App\Exception;

use App\Http\Response;
use Throwable;

/** Converts exceptions to safe JSON while retaining unexpected failures in server logs. */
final class ErrorHandler
{
    /** Prevents internal exception details from reaching API clients. */
    public function render(Throwable $error): Response
    {
        if ($error instanceof ApiException) {
            return Response::json($error->status, [
                'error' => ['code' => $error->errorCode, 'message' => $error->getMessage()],
            ], $error->headers);
        }

        error_log((string) $error);

        return Response::json(500, [
            'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'An unexpected error occurred.'],
        ]);
    }
}
