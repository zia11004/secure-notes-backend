<?php

declare(strict_types=1);

namespace App\Support;

/** Defines the permissions understood by API guards and custom roles. */
final class Permission
{
    public const ALL = [
        'notes.read', 'notes.write', 'notes.read_all',
        'posts.write', 'users.manage', 'interests.read',
    ];

    public const DEFAULT_USER = ['notes.read', 'notes.write', 'posts.write'];

    public const LABELS = [
        'notes.read' => 'Read own notes',
        'notes.write' => 'Create and manage own notes',
        'notes.read_all' => 'Read all users’ notes',
        'posts.write' => 'Create and manage own posts',
        'users.manage' => 'Manage regular users',
        'interests.read' => 'View interest groups',
    ];

    public static function allows(array $actor, string $permission): bool
    {
        return in_array($permission, $actor['permissions'] ?? [], true);
    }
}
