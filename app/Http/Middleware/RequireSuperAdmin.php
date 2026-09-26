<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For areas that can never be delegated to a role (Roles & Permissions, Staff).
 */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin && $admin->isActive() && $admin->isSuperAdmin(), 403, 'Only the Super Admin can access this page.');

        return $next($request);
    }
}
