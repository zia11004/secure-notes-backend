<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exception\ApiException;
use App\Http\Request;

/** Requires an account whose current database role is admin. */
final class EnsureAdmin
{
    public function handle(Request $request): void
    {
        if ($request->actor()['role'] !== 'admin') {
            throw new ApiException('FORBIDDEN', 'You cannot access this resource.', 403);
        }
    }
}
