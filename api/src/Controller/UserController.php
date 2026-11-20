<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserService;

/** Handles administrator user-management HTTP responses. */
final readonly class UserController
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): Response
    {
        return Response::json(200, $this->users->list($request->queryParameters()));
    }

    public function store(Request $request): Response
    {
        return Response::json(201, $this->users->create($request->jsonBody(), $request->actor()));
    }

    public function show(Request $request): Response
    {
        return Response::json(200, $this->users->show($request->routeParameter('id')));
    }

    public function update(Request $request): Response
    {
        return Response::json(200, $this->users->update($request->routeParameter('id'), $request->jsonBody(), $request->actor()));
    }

    public function destroy(Request $request): Response
    {
        $this->users->delete($request->routeParameter('id'), $request->actor());

        return new Response(204, '');
    }
}
