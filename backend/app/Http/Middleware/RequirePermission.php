<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequirePermission — route middleware: `permission:users.manage`.
 * Returns 403 JSON when the authenticated user lacks the permission.
 * Super Admin bypasses via the wildcard grant in the seeder (has all).
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Forbidden. Missing required permission: '.$permission,
            ], 403);
        }

        return $next($request);
    }
}
