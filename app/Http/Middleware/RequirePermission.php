<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:clients.view')
 *        ->middleware('permission:clients.update,clients.delete')  // any of
 *
 * Authorization is enforced here on the server; hiding a menu item in the
 * sidebar is only cosmetic.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin && $admin->hasAnyPermission(...$permissions), 403, 'You do not have permission to access this page.');

        return $next($request);
    }
}
