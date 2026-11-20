<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Exception\ApiException;
use App\Support\Input;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/** Issues short-lived signed bearer tokens and rejects invalid claims. */
final readonly class JwtService
{
    public function __construct(private AppConfig $config) {}

    public function issue(string $userId): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $this->config->jwtIssuer,
            'sub' => $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->config->jwtTtlMinutes * 60,
        ], $this->config->jwtSecret, 'HS256');
    }

    public function subject(string $token): string
    {
        try {
            $claims = JWT::decode($token, new Key($this->config->jwtSecret, 'HS256'));
        } catch (Throwable) {
            throw new ApiException('UNAUTHORIZED', 'The access token is invalid or expired.', 401);
        }

        if (($claims->iss ?? null) !== $this->config->jwtIssuer) {
            throw new ApiException('UNAUTHORIZED', 'The access token is invalid.', 401);
        }
        try {
            return Input::objectId($claims->sub ?? null);
        } catch (ApiException) {
            throw new ApiException('UNAUTHORIZED', 'The access token is invalid.', 401);
        }
    }
}
