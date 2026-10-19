<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backs the chat "online" / "last seen" indicator (User::isOnline()). Updates
 * last_active_at on any authenticated API request - no dedicated heartbeat
 * endpoint needed. Throttled to one write per minute per user so this
 * doesn't add a DB write to every single request.
 */
class TouchLastActive
{
    private const THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user && (! $user->last_active_at || $user->last_active_at->lt(now()->subSeconds(self::THROTTLE_SECONDS)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
