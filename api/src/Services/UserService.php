<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ApiException;
use App\Infrastructure\DataServiceClient;
use App\Support\Input;
use App\Support\Permission;

/** Owns account rules, password hashing, and administrative changes. */
final readonly class UserService
{
    public function __construct(private DataServiceClient $data, private JwtService $jwt) {}

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function register(array $input): array
    {
        Input::fields($input, ['name', 'email', 'password', 'confirmPassword'], ['interests']);
        $user = $this->data->request('POST', '/users', [
            'name' => Input::name($input['name']),
            'email' => Input::email($input['email']),
            'passwordHash' => password_hash($this->confirmedPassword($input), PASSWORD_ARGON2ID),
            'role' => 'user',
            'interests' => Input::interests($input['interests'] ?? []),
        ]);

        return ['user' => $this->current($user['id']), 'token' => $this->jwt->issue($user['id'])];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function login(array $input): array
    {
        Input::fields($input, ['password'], ['email', 'username']);
        if (array_key_exists('email', $input) === array_key_exists('username', $input)) {
            throw new ApiException('VALIDATION_FAILED', 'Provide either email or username.', 422);
        }
        $lookup = array_key_exists('email', $input)
            ? ['email' => Input::email($input['email'])]
            : ['username' => Input::username($input['username'])];
        $password = Input::password($input['password']);
        try {
            $user = $this->data->request('POST', '/users/lookup', $lookup);
        } catch (ApiException $error) {
            if ($error->status !== 404) {
                throw $error;
            }
            throw new ApiException('INVALID_CREDENTIALS', 'Invalid username/email or password.', 401);
        }

        if (! is_string($user['passwordHash'] ?? null) || ! password_verify($password, $user['passwordHash'])) {
            throw new ApiException('INVALID_CREDENTIALS', 'Invalid username/email or password.', 401);
        }
        unset($user['passwordHash']);

        return ['user' => $this->current($user['id']), 'token' => $this->jwt->issue($user['id'])];
    }

    /** @return array<string, mixed> */
    public function current(string $id): array
    {
        try {
            $user = $this->data->request('GET', '/users/'.$id);
        } catch (ApiException $error) {
            if ($error->status === 404) {
                throw new ApiException('UNAUTHORIZED', 'The account no longer exists.', 401);
            }
            throw $error;
        }

        if ($user['role'] === 'admin') {
            $user['roleName'] = 'admin';
            $user['permissions'] = Permission::ALL;
        } elseif (is_string($user['roleId'] ?? null)) {
            $role = $this->data->request('GET', '/roles/'.$user['roleId']);
            $user['roleName'] = $role['name'];
            $user['permissions'] = $role['permissions'];
        } else {
            $user['roleName'] = 'user';
            $user['permissions'] = Permission::DEFAULT_USER;
        }

        return $user;
    }

    /** @param array<string, string> $query
     *  @return array<string, mixed>
     */
    public function list(array $query): array
    {
        return $this->data->request('GET', '/users', Input::pagination($query));
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(array $input, array $actor): array
    {
        Input::fields($input, ['name', 'email', 'password', 'confirmPassword'], ['role', 'roleId', 'interests']);
        $this->guardRoleAssignment($actor, $input, 'user');

        return $this->data->request('POST', '/users', [
            'name' => Input::name($input['name']),
            'email' => Input::email($input['email']),
            'passwordHash' => password_hash($this->confirmedPassword($input), PASSWORD_ARGON2ID),
            'role' => Input::role($input['role'] ?? 'user'),
            'roleId' => Input::roleId($input['roleId'] ?? null),
            'interests' => Input::interests($input['interests'] ?? []),
        ]);
    }

    /** @return array<string, mixed> */
    public function show(string $id): array
    {
        return $this->data->request('GET', '/users/'.Input::objectId($id));
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function update(string $id, array $input, array $actor): array
    {
        $id = Input::objectId($id);
        Input::fields($input, [], ['name', 'email', 'password', 'confirmPassword', 'role', 'roleId', 'interests']);
        if (array_key_exists('password', $input) !== array_key_exists('confirmPassword', $input)) {
            throw new ApiException('VALIDATION_FAILED', 'Password and confirmation must be provided together.', 422);
        }
        $target = $this->show($id);
        if ($actor['role'] !== 'admin' && $target['role'] === 'admin') {
            throw new ApiException('FORBIDDEN', 'You cannot change an administrator.', 403);
        }
        $this->guardRoleAssignment($actor, $input, $target['role']);
        if ($id === $actor['id'] && isset($input['role']) && Input::role($input['role']) !== 'admin') {
            throw new ApiException('FORBIDDEN', 'You cannot remove your own admin role.', 403);
        }
        if ($id === $actor['id'] && ($input['roleId'] ?? null) !== null) {
            throw new ApiException('FORBIDDEN', 'You cannot assign yourself a custom role.', 403);
        }

        $changes = [];
        if (array_key_exists('name', $input)) {
            $changes['name'] = Input::name($input['name']);
        }
        if (array_key_exists('email', $input)) {
            $changes['email'] = Input::email($input['email']);
        }
        if (array_key_exists('password', $input)) {
            $changes['passwordHash'] = password_hash($this->confirmedPassword($input), PASSWORD_ARGON2ID);
        }
        if (array_key_exists('role', $input)) {
            $changes['role'] = Input::role($input['role']);
            if ($changes['role'] === 'admin') {
                $changes['roleId'] = null;
            }
        }
        if (array_key_exists('roleId', $input)) {
            $changes['roleId'] = Input::roleId($input['roleId']);
        }
        if (array_key_exists('interests', $input)) {
            $changes['interests'] = Input::interests($input['interests']);
        }

        return $this->data->request('PATCH', '/users/'.$id, $changes);
    }

    public function delete(string $id, array $actor): void
    {
        $id = Input::objectId($id);
        if ($id === $actor['id']) {
            throw new ApiException('FORBIDDEN', 'You cannot remove your own account.', 403);
        }
        if ($actor['role'] !== 'admin' && $this->show($id)['role'] === 'admin') {
            throw new ApiException('FORBIDDEN', 'You cannot remove an administrator.', 403);
        }
        $this->data->request('DELETE', '/users/'.$id);
    }

    /** Only the built-in admin may grant administrator or custom-role access. */
    private function guardRoleAssignment(array $actor, array $input, string $currentRole): void
    {
        if ($actor['role'] !== 'admin' && (array_key_exists('role', $input) || array_key_exists('roleId', $input))) {
            throw new ApiException('FORBIDDEN', 'Only an administrator may assign roles.', 403);
        }
        $role = Input::role($input['role'] ?? $currentRole);
        $roleId = array_key_exists('roleId', $input) ? Input::roleId($input['roleId']) : null;
        if ($roleId !== null) {
            if ($role !== 'user') {
                throw new ApiException('VALIDATION_FAILED', 'Custom roles require a regular user.', 422);
            }
            $this->data->request('GET', '/roles/'.$roleId);
        }
    }

    /** @param array<string, mixed> $input */
    private function confirmedPassword(array $input): string
    {
        $password = Input::password($input['password']);
        $confirmation = $input['confirmPassword'] ?? null;
        if (! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
            throw new ApiException('PASSWORD_CONFIRMATION_MISMATCH', 'Passwords do not match.', 422);
        }

        return $password;
    }
}
