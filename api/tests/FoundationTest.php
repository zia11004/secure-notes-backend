<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Exception\ApiException;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\JsonMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Infrastructure\FileRateLimiter;
use App\Support\Input;

require dirname(__DIR__).'/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function expectStatus(int $status, Closure $operation): ApiException
{
    try {
        $operation();
    } catch (ApiException $error) {
        check($error->status === $status, "Expected HTTP {$status}, received {$error->status}.");

        return $error;
    }

    throw new RuntimeException("Expected HTTP {$status}, but no error was raised.");
}

foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://127.0.0.1:8000',
    'JWT_SECRET' => str_repeat('j', 32),
    'JWT_ISSUER' => 'secure-notes-test',
    'JWT_TTL_MINUTES' => '60',
    'DATA_SERVICE_URL' => 'http://127.0.0.1:3001',
    'DATA_SERVICE_TOKEN' => str_repeat('t', 32),
    'FRONTEND_ORIGINS' => 'http://localhost:5173',
    'RATE_LIMIT_PER_MINUTE' => '120',
] as $name => $value) {
    $_ENV[$name] = $value;
}

$config = AppConfig::load(dirname(__DIR__));
check(Input::username('110001') === '110001', 'A generated username was rejected.');
check(Input::userReference('110001') === '110001', 'A username reference was rejected.');
check(Input::userReference('507f1f77bcf86cd799439011') === '507f1f77bcf86cd799439011', 'An existing user link was rejected.');
expectStatus(422, static fn () => Input::username('110000'));
expectStatus(422, static fn () => Input::username('11x001'));
check(Input::password('123456') === '123456', 'A six-character password was rejected.');
check(Input::roleName('Support Agent') === 'support agent', 'Role name normalization failed.');
check(Input::permissions(['notes.read', 'users.manage']) === ['notes.read', 'users.manage'], 'Permission set was rejected.');
expectStatus(422, static fn () => Input::roleName('admin'));
expectStatus(422, static fn () => Input::permissions(['notes.read', 'notes.read']));
expectStatus(422, static fn () => Input::permissions(['roles.manage']));
expectStatus(422, static fn () => Input::password('12345'));
expectStatus(422, static fn () => Input::email('invalid-email'));
$_ENV['DATA_SERVICE_URL'] = 'http://public.example.test:3001';
try {
    AppConfig::load(dirname(__DIR__));
    throw new RuntimeException('A public data-service URL was accepted.');
} catch (InvalidArgumentException) {
    $_ENV['DATA_SERVICE_URL'] = 'http://127.0.0.1:3001';
}
$_ENV['APP_ENV'] = 'production';
try {
    AppConfig::load(dirname(__DIR__));
    throw new RuntimeException('An HTTP production origin was accepted.');
} catch (InvalidArgumentException) {
    $_ENV['APP_ENV'] = 'testing';
}

$router = new Router;
$router->get('/api/items/{id}', static fn (Request $request): Response => Response::json(200, [
    'id' => $request->routeParameter('id'),
    'page' => $request->queryParameters()['page'] ?? null,
]), ['page']);
$router->post('/api/items', static fn (Request $request): Response => Response::json(201, [
    'title' => $request->jsonBody()['title'] ?? null,
]));

$makeRequest = static fn (string $method, string $path, string $query = '', array $headers = [], string $body = ''): Request =>
    new Request($method, $path, $query, $headers, $body, '127.0.0.1');

$result = $router->dispatch($makeRequest('GET', '/api/items/item-1', 'page=2'));
check($result->status === 200 && json_decode($result->body, true) === ['id' => 'item-1', 'page' => '2'], 'Route and query dispatch failed.');

foreach (['page=1&page=2', 'unknown=1', 'page=%GG', 'page=%00'] as $query) {
    expectStatus(422, static fn () => $router->dispatch($makeRequest('GET', '/api/items/item-1', $query)));
}
expectStatus(422, static fn () => $router->dispatch($makeRequest('GET', '/api/items/a%2Fb')));
$wrongMethod = expectStatus(405, static fn () => $router->dispatch($makeRequest('DELETE', '/api/items/item-1')));
check($wrongMethod->headers['Allow'] === 'GET', 'Method response omitted its Allow header.');
expectStatus(404, static fn () => $router->dispatch($makeRequest('GET', '/api/missing')));

$json = new JsonMiddleware;
$write = $makeRequest('POST', '/api/items', '', ['content-type' => 'application/json'], '{"title":"Note"}');
$json->validate($write);
check($router->dispatch($write)->status === 201, 'Valid JSON write was rejected.');
expectStatus(400, static fn () => $json->validate($makeRequest('POST', '/api/items', '', ['content-type' => 'application/json'], '{bad')));
expectStatus(415, static fn () => $json->validate($makeRequest('POST', '/api/items', '', ['content-type' => 'text/plain'], 'text')));

$cors = new CorsMiddleware($config);
$preflight = $cors->preflight($makeRequest('OPTIONS', '/api/items', '', [
    'origin' => 'http://localhost:5173', 'access-control-request-method' => 'POST',
]));
check($preflight?->status === 204, 'Allowed browser preflight failed.');
expectStatus(403, static fn () => $cors->preflight($makeRequest('OPTIONS', '/api/items', '', [
    'origin' => 'https://unknown.test', 'access-control-request-method' => 'POST',
])));

$directory = sys_get_temp_dir().'/secure-notes-test-'.bin2hex(random_bytes(6));
try {
    $limiter = new FileRateLimiter($directory, 1);
    $limiter->check('127.0.0.1');
    expectStatus(429, static fn () => $limiter->check('127.0.0.1'));
} finally {
    foreach (glob($directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}

echo "PHP foundation checks passed.\n";
