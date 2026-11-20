<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\InterestController;
use App\Controller\NoteController;
use App\Controller\PostController;
use App\Controller\RoleController;
use App\Controller\UserController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequirePermission;
use App\Http\Request;
use App\Http\Router;
use App\Infrastructure\FileRateLimiter;

return static function (
    Router $router,
    HealthController $health,
    AuthController $auth,
    NoteController $notes,
    PostController $posts,
    UserController $users,
    InterestController $interests,
    RoleController $roles,
    Authenticate $authenticate,
    EnsureAdmin $admin,
    RequirePermission $permission,
    FileRateLimiter $authLimiter,
): void {
    $authenticated = [$authenticate->handle(...)];
    $adminOnly = [$authenticate->handle(...), $admin->handle(...)];
    $notesRead = [$authenticate->handle(...), $permission->for('notes.read')];
    $notesWrite = [$authenticate->handle(...), $permission->for('notes.write')];
    $postsWrite = [$authenticate->handle(...), $permission->for('posts.write')];
    $usersManage = [$authenticate->handle(...), $permission->for('users.manage')];
    $interestsRead = [$authenticate->handle(...), $permission->for('interests.read')];
    $throttleAuth = [static fn (Request $request) => $authLimiter->check($request->clientIp)];

    $router->get('/api/health', $health->health(...));
    $router->get('/api/ready', $health->ready(...));

    $router->post('/api/auth/register', $auth->register(...), $throttleAuth);
    $router->post('/api/auth/login', $auth->login(...), $throttleAuth);
    $router->get('/api/auth/me', $auth->me(...), [], $authenticated);

    $router->get('/api/posts', $posts->index(...), ['page', 'per_page']);
    $router->get('/api/posts/{id}', $posts->show(...));
    $router->get('/api/users/{username}/posts', $posts->byUser(...), ['page', 'per_page']);
    $router->post('/api/posts', $posts->store(...), $postsWrite);
    $router->patch('/api/posts/{id}', $posts->update(...), $postsWrite);
    $router->delete('/api/posts/{id}', $posts->destroy(...), $postsWrite);

    $router->get('/api/notes', $notes->index(...), ['page', 'per_page', 'scope'], $notesRead);
    $router->post('/api/notes', $notes->store(...), $notesWrite);
    $router->get('/api/notes/{id}', $notes->show(...), [], $notesRead);
    $router->patch('/api/notes/{id}', $notes->update(...), $notesWrite);
    $router->delete('/api/notes/{id}', $notes->destroy(...), $notesWrite);

    $router->get('/api/admin/users', $users->index(...), ['page', 'per_page'], $usersManage);
    $router->post('/api/admin/users', $users->store(...), $usersManage);
    $router->get('/api/admin/users/{id}', $users->show(...), [], $usersManage);
    $router->patch('/api/admin/users/{id}', $users->update(...), $usersManage);
    $router->delete('/api/admin/users/{id}', $users->destroy(...), $usersManage);
    $router->get('/api/admin/interests', $interests->index(...), ['page', 'per_page'], $interestsRead);
    $router->get('/api/admin/interests/{interest}/users', $interests->members(...), ['page', 'per_page'], $interestsRead);

    $router->get('/api/admin/permissions', $roles->permissions(...), [], $adminOnly);
    $router->get('/api/admin/roles', $roles->index(...), ['page', 'per_page'], $adminOnly);
    $router->post('/api/admin/roles', $roles->store(...), $adminOnly);
    $router->get('/api/admin/roles/{id}', $roles->show(...), [], $adminOnly);
    $router->patch('/api/admin/roles/{id}', $roles->update(...), $adminOnly);
    $router->delete('/api/admin/roles/{id}', $roles->destroy(...), $adminOnly);
};
