<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Services\RoleService;
use App\Support\Permission;

/** Presents the role catalog and role-management HTTP responses. */
final readonly class RoleController
{
    public function __construct(private RoleService $roles) {}

    public function permissions(Request $request): Response
    {
        $items = [];
        foreach (Permission::LABELS as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label];
        }

        return Response::json(200, ['items' => $items]);
    }

    public function index(Request $request): Response
    {
        return Response::json(200, $this->roles->list($request->queryParameters()));
    }

    public function store(Request $request): Response
    {
        return Response::json(201, $this->roles->create($request->jsonBody()));
    }

    public function show(Request $request): Response
    {
        return Response::json(200, $this->roles->show($request->routeParameter('id')));
    }

    public function update(Request $request): Response
    {
        return Response::json(200, $this->roles->update($request->routeParameter('id'), $request->jsonBody()));
    }

    public function destroy(Request $request): Response
    {
        $this->roles->delete($request->routeParameter('id'));

        return new Response(204, '');
    }
}
