<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ApiException;
use App\Infrastructure\DataServiceClient;
use App\Support\Input;
use App\Support\Permission;

/** Enforces note ownership and the admin's read-only cross-user visibility. */
final readonly class NoteService
{
    public function __construct(private DataServiceClient $data) {}

    /** @param array<string, mixed> $actor
     *  @param array<string, string> $query
     *  @return array<string, mixed>
     */
    public function list(array $actor, array $query): array
    {
        $scope = $query['scope'] ?? 'own';
        if (! in_array($scope, ['own', 'all'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Invalid scope.', 422);
        }
        if ($scope === 'all' && ! Permission::allows($actor, 'notes.read_all')) {
            throw new ApiException('FORBIDDEN', 'You cannot view all notes.', 403);
        }

        $parameters = Input::pagination($query);
        if ($scope === 'own') {
            $parameters['owner_id'] = $actor['id'];
        }

        return $this->data->request('GET', '/notes', $parameters);
    }

    /** @param array<string, mixed> $actor
     *  @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(array $actor, array $input): array
    {
        Input::fields($input, ['title', 'body']);

        return $this->data->request('POST', '/notes', [
            'ownerId' => $actor['id'],
            'title' => Input::title($input['title']),
            'body' => Input::body($input['body']),
        ]);
    }

    /** @param array<string, mixed> $actor
     *  @return array<string, mixed>
     */
    public function show(array $actor, string $id): array
    {
        $note = $this->data->request('GET', '/notes/'.Input::objectId($id));
        if ($note['ownerId'] !== $actor['id'] && ! Permission::allows($actor, 'notes.read_all')) {
            throw new ApiException('NOT_FOUND', 'Note not found.', 404);
        }

        return $note;
    }

    /** @param array<string, mixed> $actor
     *  @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function update(array $actor, string $id, array $input): array
    {
        $note = $this->show($actor, $id);
        if ($note['ownerId'] !== $actor['id']) {
            throw new ApiException('FORBIDDEN', 'You cannot change another user’s note.', 403);
        }
        Input::fields($input, [], ['title', 'body']);
        $changes = [];
        if (array_key_exists('title', $input)) {
            $changes['title'] = Input::title($input['title']);
        }
        if (array_key_exists('body', $input)) {
            $changes['body'] = Input::body($input['body']);
        }

        return $this->data->request('PATCH', '/notes/'.$note['id'], $changes);
    }

    /** @param array<string, mixed> $actor */
    public function delete(array $actor, string $id): void
    {
        $note = $this->show($actor, $id);
        if ($note['ownerId'] !== $actor['id']) {
            throw new ApiException('FORBIDDEN', 'You cannot remove another user’s note.', 403);
        }
        $this->data->request('DELETE', '/notes/'.$note['id']);
    }
}
