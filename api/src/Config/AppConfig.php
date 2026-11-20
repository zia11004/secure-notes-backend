<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;
use InvalidArgumentException;

/** Validated runtime settings shared by the API's infrastructure classes. */
final readonly class AppConfig
{
    /** @param list<string> $frontendOrigins */
    private function __construct(
        public string $environment,
        public bool $debug,
        public string $appUrl,
        public string $jwtSecret,
        public string $jwtIssuer,
        public int $jwtTtlMinutes,
        public string $dataServiceUrl,
        public string $dataServiceToken,
        public array $frontendOrigins,
        public int $rateLimitPerMinute,
        public string $rateLimitDirectory,
    ) {}

    /** Fails before request handling if a deployment setting is unsafe or incomplete. */
    public static function load(string $basePath): self
    {
        Dotenv::createImmutable($basePath)->safeLoad();

        $environment = self::required('APP_ENV');
        if (! in_array($environment, ['local', 'testing', 'staging', 'production'], true)) {
            throw new InvalidArgumentException('APP_ENV is invalid.');
        }

        $debugValue = self::required('APP_DEBUG');
        if (! in_array($debugValue, ['true', 'false'], true)) {
            throw new InvalidArgumentException('APP_DEBUG must be true or false.');
        }
        $debug = $debugValue === 'true';
        if ($environment === 'production' && $debug) {
            throw new InvalidArgumentException('APP_DEBUG must be false in production.');
        }

        $appUrl = self::origin(self::required('APP_URL'), 'APP_URL', $environment === 'production');
        $dataServiceUrl = self::origin(self::required('DATA_SERVICE_URL'), 'DATA_SERVICE_URL', false);
        $serviceHost = parse_url($dataServiceUrl, PHP_URL_HOST);
        if (parse_url($dataServiceUrl, PHP_URL_SCHEME) !== 'http'
            || ! in_array($serviceHost, ['127.0.0.1', 'data-service'], true)) {
            throw new InvalidArgumentException('DATA_SERVICE_URL must use a private local or Compose service host.');
        }

        $originList = self::required('FRONTEND_ORIGINS');
        $frontendOrigins = [];
        foreach (explode(',', $originList) as $origin) {
            $frontendOrigins[] = self::origin(trim($origin), 'FRONTEND_ORIGINS', $environment === 'production');
        }
        if (count($frontendOrigins) !== count(array_unique($frontendOrigins))) {
            throw new InvalidArgumentException('FRONTEND_ORIGINS contains duplicates.');
        }

        $limit = self::required('RATE_LIMIT_PER_MINUTE');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 1000) {
            throw new InvalidArgumentException('RATE_LIMIT_PER_MINUTE must be between 1 and 1000.');
        }

        $issuer = self::required('JWT_ISSUER');
        if (! preg_match('/^[a-zA-Z0-9._:-]{3,100}$/D', $issuer)) {
            throw new InvalidArgumentException('JWT_ISSUER must be a stable identifier.');
        }
        $ttl = self::required('JWT_TTL_MINUTES');
        if (! ctype_digit($ttl) || (int) $ttl < 5 || (int) $ttl > 1440) {
            throw new InvalidArgumentException('JWT_TTL_MINUTES must be between 5 and 1440.');
        }

        return new self(
            $environment,
            $debug,
            $appUrl,
            self::secret('JWT_SECRET'),
            $issuer,
            (int) $ttl,
            $dataServiceUrl,
            self::secret('DATA_SERVICE_TOKEN'),
            $frontendOrigins,
            (int) $limit,
            $basePath.'/var/rate-limit',
        );
    }

    private static function required(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (! is_string($value) || $value === '' || trim($value) !== $value) {
            throw new InvalidArgumentException("{$name} is missing or invalid.");
        }

        return $value;
    }

    private static function secret(string $name): string
    {
        $value = self::required($name);
        if (strlen($value) < 32 || preg_match('/\s/', $value) || str_starts_with($value, 'replace-')) {
            throw new InvalidArgumentException("{$name} must be a random secret of at least 32 characters.");
        }

        return $value;
    }

    private static function origin(string $value, string $name, bool $httpsOnly): string
    {
        $parts = parse_url($value);
        if (! is_array($parts) || filter_var($value, FILTER_VALIDATE_URL) === false
            || ! in_array($parts['scheme'] ?? null, $httpsOnly ? ['https'] : ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException("{$name} must contain valid origins without paths or credentials.");
        }

        return $value;
    }
}
