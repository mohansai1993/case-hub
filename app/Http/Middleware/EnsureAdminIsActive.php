<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating an admin must take effect immediately, not when their session
 * happens to expire. Runs on every panel request after authentication.
 */
class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin && ! $admin->isActive()) {
            Auth::guard('admin')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Your account has been deactivated.');
            }

            return redirect()->route('login')
                ->withErrors(['identifier' => 'Your account has been deactivated. Please contact the Super Admin.']);
        }

        return $next($request);
    }
}
