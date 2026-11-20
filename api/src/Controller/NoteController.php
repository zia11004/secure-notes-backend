<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Services\NoteService;

/** Handles note HTTP details while NoteService enforces ownership. */
final readonly class NoteController
{
    public function __construct(private NoteService $notes) {}

    public function index(Request $request): Response
    {
        return Response::json(200, $this->notes->list($request->actor(), $request->queryParameters()));
    }

    public function store(Request $request): Response
    {
        return Response::json(201, $this->notes->create($request->actor(), $request->jsonBody()));
    }

    public function show(Request $request): Response
    {
        return Response::json(200, $this->notes->show($request->actor(), $request->routeParameter('id')));
    }

    public function update(Request $request): Response
    {
        return Response::json(200, $this->notes->update($request->actor(), $request->routeParameter('id'), $request->jsonBody()));
    }

    public function destroy(Request $request): Response
    {
        $this->notes->delete($request->actor(), $request->routeParameter('id'));

        return new Response(204, '');
    }
}
