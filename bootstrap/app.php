<?php

use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureSubscriptionNotRestricted;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    // Mobile app uses Sanctum bearer tokens, not the session cookie that
    // Broadcast::routes() defaults to - so channel auth needs its own
    // middleware, and lives under /api to match the rest of the mobile API.
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
            'permission' => RequirePermission::class,
            'super' => RequireSuperAdmin::class,
            'no-store' => NoStore::class,
            'api.active' => EnsureUserIsActive::class,
            'subscription.active' => EnsureSubscriptionNotRestricted::class,
        ]);

        // Not signed in -> login page. Already signed in -> their home section.
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('admin.home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The mobile API always answers in JSON, even without an Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
