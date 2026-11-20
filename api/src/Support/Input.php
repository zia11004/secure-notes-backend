<?php

declare(strict_types=1);

namespace App\Support;

use App\Exception\ApiException;

/** Validates public API values before business logic or MongoDB calls. */
final class Input
{
    /** @param array<string, mixed> $value
     *  @param list<string> $required
     *  @param list<string> $optional
     *  @return array<string, mixed>
     */
    public static function fields(array $value, array $required, array $optional = []): array
    {
        $allowed = [...$required, ...$optional];
        foreach ($required as $name) {
            if (! array_key_exists($name, $value)) {
                self::fail($name);
            }
        }
        foreach ($value as $name => $_) {
            if (! in_array($name, $allowed, true)) {
                self::fail((string) $name);
            }
        }
        if ($value === []) {
            self::fail('body');
        }

        return $value;
    }

    public static function name(mixed $value): string
    {
        return self::text($value, 'name', 2, 100);
    }

    public static function title(mixed $value): string
    {
        return self::text($value, 'title', 1, 160);
    }

    public static function body(mixed $value): string
    {
        return self::text($value, 'body', 1, 20000, false);
    }

    public static function email(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 254) {
            self::fail('email');
        }
        $email = strtolower(trim($value));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::fail('email');
        }

        return $email;
    }

    public static function username(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[1-9][0-9]{5,15}$/D', $value) || (int) $value < 110001) {
            self::fail('username');
        }

        return $value;
    }

    public static function password(mixed $value): string
    {
        if (! is_string($value) || mb_strlen($value, 'UTF-8') < 6 || strlen($value) > 128 || trim($value) === '') {
            self::fail('password');
        }

        return $value;
    }

    public static function role(mixed $value): string
    {
        if (! in_array($value, ['user', 'admin'], true)) {
            self::fail('role');
        }

        return $value;
    }

    public static function roleName(mixed $value): string
    {
        $name = strtolower(self::text($value, 'name', 2, 50));
        if (! preg_match('/^[a-z0-9][a-z0-9 -]*$/D', $name) || in_array($name, ['admin', 'user'], true)) {
            self::fail('name');
        }

        return $name;
    }

    /** @return list<string> */
    public static function permissions(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > count(Permission::ALL)) {
            self::fail('permissions');
        }
        foreach ($value as $permission) {
            if (! is_string($permission) || ! in_array($permission, Permission::ALL, true)) {
                self::fail('permissions');
            }
        }
        if (count($value) !== count(array_unique($value))) {
            self::fail('permissions');
        }

        return $value;
    }

    public static function roleId(mixed $value): ?string
    {
        return $value === null ? null : self::objectId($value);
    }

    /** @return list<string> */
    public static function interests(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > 20) {
            self::fail('interests');
        }
        $normalized = [];
        foreach ($value as $interest) {
            if (! is_string($interest)) {
                self::fail('interests');
            }
            $item = strtolower(trim($interest));
            if (strlen($item) < 2 || strlen($item) > 50 || ! preg_match('/^[a-z0-9][a-z0-9 -]*$/D', $item)) {
                self::fail('interests');
            }
            $normalized[] = $item;
        }
        if (count($normalized) !== count(array_unique($normalized))) {
            self::fail('interests');
        }

        return $normalized;
    }

    public static function interest(mixed $value): string
    {
        $items = self::interests([$value]);

        return $items[0];
    }

    public static function objectId(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[a-f0-9]{24}$/D', $value)) {
            self::fail('id');
        }

        return $value;
    }

    /** Accepts the public username while retaining existing ObjectId links. */
    public static function userReference(mixed $value): string
    {
        if (is_string($value) && preg_match('/^[a-f0-9]{24}$/D', $value)) {
            return $value;
        }

        return self::username($value);
    }

    /** @param array<string, string> $query
     *  @return array{page: int, per_page: int}
     */
    public static function pagination(array $query): array
    {
        return [
            'page' => self::positiveInt($query['page'] ?? '1', 'page', 10000),
            'per_page' => self::positiveInt($query['per_page'] ?? '20', 'per_page', 100),
        ];
    }

    private static function text(mixed $value, string $field, int $min, int $max, bool $trim = true): string
    {
        if (! is_string($value)) {
            self::fail($field);
        }
        $cleaned = $trim ? trim($value) : $value;
        $length = mb_strlen($cleaned);
        if ($length < $min || $length > $max || trim($cleaned) === '') {
            self::fail($field);
        }

        return $cleaned;
    }

    private static function positiveInt(string $value, string $field, int $max): int
    {
        if (! preg_match('/^[1-9][0-9]*$/D', $value) || (int) $value > $max) {
            self::fail($field);
        }

        return (int) $value;
    }

    private static function fail(string $field): never
    {
        throw new ApiException('VALIDATION_FAILED', "Invalid {$field}.", 422);
    }
}
