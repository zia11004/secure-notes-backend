<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Services\PostService;

/** Serves public post reads and authenticated author writes. */
final readonly class PostController
{
    public function __construct(private PostService $posts) {}

    public function index(Request $request): Response
    {
        return Response::json(200, $this->posts->list($request->queryParameters()));
    }

    public function show(Request $request): Response
    {
        return Response::json(200, $this->posts->show($request->routeParameter('id')));
    }

    public function byUser(Request $request): Response
    {
        return Response::json(200, $this->posts->byUser($request->routeParameter('username'), $request->queryParameters()));
    }

    public function store(Request $request): Response
    {
        return Response::json(201, $this->posts->create($request->actor(), $request->jsonBody()));
    }

    public function update(Request $request): Response
    {
        return Response::json(200, $this->posts->update($request->actor(), $request->routeParameter('id'), $request->jsonBody()));
    }

    public function destroy(Request $request): Response
    {
        $this->posts->delete($request->actor(), $request->routeParameter('id'));

        return new Response(204, '');
    }
}
