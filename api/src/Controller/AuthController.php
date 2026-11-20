<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserService;

/** Translates authentication requests into service calls and JSON responses. */
final readonly class AuthController
{
    public function __construct(private UserService $users) {}

    public function register(Request $request): Response
    {
        return Response::json(201, $this->users->register($request->jsonBody()));
    }

    public function login(Request $request): Response
    {
        return Response::json(200, $this->users->login($request->jsonBody()));
    }

    public function me(Request $request): Response
    {
        return Response::json(200, $request->actor());
    }
}
