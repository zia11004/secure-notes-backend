<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\DataServiceClient;
use App\Support\Input;

/** Validates custom role names and permission sets before persistence. */
final readonly class RoleService
{
    public function __construct(private DataServiceClient $data) {}

    public function list(array $query): array
    {
        return $this->data->request('GET', '/roles', Input::pagination($query));
    }

    public function show(string $id): array
    {
        return $this->data->request('GET', '/roles/'.Input::objectId($id));
    }

    public function create(array $input): array
    {
        Input::fields($input, ['name', 'permissions']);

        return $this->data->request('POST', '/roles', [
            'name' => Input::roleName($input['name']),
            'permissions' => Input::permissions($input['permissions']),
        ]);
    }

    public function update(string $id, array $input): array
    {
        Input::fields($input, [], ['name', 'permissions']);
        $changes = [];
        if (array_key_exists('name', $input)) {
            $changes['name'] = Input::roleName($input['name']);
        }
        if (array_key_exists('permissions', $input)) {
            $changes['permissions'] = Input::permissions($input['permissions']);
        }

        return $this->data->request('PATCH', '/roles/'.Input::objectId($id), $changes);
    }

    public function delete(string $id): void
    {
        $this->data->request('DELETE', '/roles/'.Input::objectId($id));
    }
}
