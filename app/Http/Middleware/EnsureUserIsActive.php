<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token stays valid until it expires, so suspending or deactivating an
 * account must be enforced on every API request, not only at login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (! $user || ! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your account is not active. Please contact support.',
                'code' => 'account_inactive',
            ], 403);
        }

        return $next($request);
    }
}
