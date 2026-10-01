<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once a client's grace period has expired, everything is locked except
 * billing (subscribe/upgrade/cancel) and their own profile - those routes
 * are deliberately placed outside this middleware's group. Lawyers have no
 * subscription at all, so this never affects them.
 */
class EnsureSubscriptionNotRestricted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user?->isClient() && $user->subscription?->isRestricted()) {
            return response()->json([
                'message' => 'Your account is restricted. Subscribe to a plan to restore access.',
                'code' => 'subscription_restricted',
            ], 403);
        }

        return $next($request);
    }
}
