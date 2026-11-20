<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ApiException;
use App\Infrastructure\DataServiceClient;
use App\Support\Input;

/** Keeps posts public to read while restricting writes to their authors. */
final readonly class PostService
{
    public function __construct(private DataServiceClient $data) {}

    /** @param array<string, string> $query
     *  @return array<string, mixed>
     */
    public function list(array $query): array
    {
        return $this->data->request('GET', '/posts', Input::pagination($query));
    }

    /** @return array<string, mixed> */
    public function show(string $id): array
    {
        return $this->data->request('GET', '/posts/'.Input::objectId($id));
    }

    /** @param array<string, string> $query
     *  @return array<string, mixed>
     */
    public function byUser(string $userReference, array $query): array
    {
        return $this->data->request('GET', '/users/'.Input::userReference($userReference).'/posts', Input::pagination($query));
    }

    /** @param array<string, mixed> $actor
     *  @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(array $actor, array $input): array
    {
        Input::fields($input, ['title', 'body']);

        return $this->data->request('POST', '/posts', [
            'authorId' => $actor['id'],
            'title' => Input::title($input['title']),
            'body' => Input::body($input['body']),
        ]);
    }

    /** @param array<string, mixed> $actor
     *  @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function update(array $actor, string $id, array $input): array
    {
        $post = $this->show($id);
        $this->ensureAuthor($actor, $post);
        Input::fields($input, [], ['title', 'body']);
        $changes = [];
        if (array_key_exists('title', $input)) {
            $changes['title'] = Input::title($input['title']);
        }
        if (array_key_exists('body', $input)) {
            $changes['body'] = Input::body($input['body']);
        }

        return $this->data->request('PATCH', '/posts/'.$post['id'], $changes);
    }

    /** @param array<string, mixed> $actor */
    public function delete(array $actor, string $id): void
    {
        $post = $this->show($id);
        $this->ensureAuthor($actor, $post);
        $this->data->request('DELETE', '/posts/'.$post['id']);
    }

    /** @param array<string, mixed> $actor
     *  @param array<string, mixed> $post
     */
    private function ensureAuthor(array $actor, array $post): void
    {
        if ($post['authorId'] !== $actor['id']) {
            throw new ApiException('FORBIDDEN', 'You cannot change another user’s post.', 403);
        }
    }
}
