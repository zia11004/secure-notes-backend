<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exception\ApiException;
use App\Http\Request;
use App\Services\JwtService;
use App\Services\UserService;

/** Loads the current database role for every authenticated request. */
final readonly class Authenticate
{
    public function __construct(private JwtService $jwt, private UserService $users) {}

    public function handle(Request $request): void
    {
        $authorization = $request->header('authorization');
        if (! is_string($authorization) || ! preg_match('/^Bearer ([A-Za-z0-9._-]+)$/D', $authorization, $matches)) {
            throw new ApiException('UNAUTHORIZED', 'Authentication is required.', 401);
        }

        $request->setActor($this->users->current($this->jwt->subject($matches[1])));
    }
}
