<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

use App\Config\AppConfig;
use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\InterestController;
use App\Controller\NoteController;
use App\Controller\PostController;
use App\Controller\RoleController;
use App\Controller\UserController;
use App\Exception\ErrorHandler;
use App\Http\Kernel;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\JsonMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Infrastructure\DataServiceClient;
use App\Infrastructure\FileRateLimiter;
use App\Services\InterestService;
use App\Services\JwtService;
use App\Services\NoteService;
use App\Services\PostService;
use App\Services\RoleService;
use App\Services\UserService;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $basePath = dirname(__DIR__);
    $config = AppConfig::load($basePath);
    $router = new Router;
    $data = new DataServiceClient($config);
    $jwt = new JwtService($config);
    $userService = new UserService($data, $jwt);
    (require $basePath.'/routes/api.php')(
        $router,
        new HealthController($data),
        new AuthController($userService),
        new NoteController(new NoteService($data)),
        new PostController(new PostService($data)),
        new UserController($userService),
        new InterestController(new InterestService($data)),
        new RoleController(new RoleService($data)),
        new Authenticate($jwt, $userService),
        new EnsureAdmin,
        new RequirePermission,
        new FileRateLimiter($config->rateLimitDirectory.'/auth', 10),
    );

    $kernel = new Kernel(
        $router,
        new CorsMiddleware($config),
        new JsonMiddleware,
        new FileRateLimiter($config->rateLimitDirectory, $config->rateLimitPerMinute),
        new ErrorHandler,
    );
    $response = $kernel->handle(Request::fromGlobals());
} catch (Throwable $error) {
    $response = (new ErrorHandler)->render($error);
}

$response->send();
