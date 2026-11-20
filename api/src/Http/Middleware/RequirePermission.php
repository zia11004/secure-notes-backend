<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exception\ApiException;
use App\Http\Request;
use App\Support\Permission;
use Closure;

/** Restricts a route to actors with its current database-backed permission. */
final class RequirePermission
{
    public function for(string $permission): Closure
    {
        return static function (Request $request) use ($permission): void {
            if (! Permission::allows($request->actor(), $permission)) {
                throw new ApiException('FORBIDDEN', 'You cannot access this resource.', 403);
            }
        };
    }
}
