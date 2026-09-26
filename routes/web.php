<?php

use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::redirect('/login', '/');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    // Forgot password (JSON, called by the login screen)
    Route::prefix('forgot-password')->name('password.')->group(function () {
        Route::post('/', [PasswordResetController::class, 'sendOtp'])
            ->middleware('throttle:otp-send')
            ->name('otp.send');

        Route::post('/verify', [PasswordResetController::class, 'verifyOtp'])
            ->middleware('throttle:otp-verify')
            ->name('otp.verify');
    });

    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('password.reset');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth:admin')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| Every route needs a signed-in, active admin. Each section additionally
| requires the matching permission (config/permissions.php); Roles and Staff
| are Super Admin only. The pages are still static designs - swap the
| Route::view calls for controllers as each module gets its backend, keeping
| the middleware.
|
*/

Route::middleware(['auth:admin', 'admin.active', 'no-store'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::view('/no-access', 'admin.no-access')->name('no-access');

        Route::view('/dashboard', 'admin.dashboard')
            ->middleware('permission:dashboard.view')->name('dashboard');

        Route::middleware('permission:clients.view')->group(function () {
            Route::view('/clients', 'admin.clients')->name('clients');
            Route::view('/client-details', 'admin.client-details')->name('client-details');
        });

        Route::middleware('permission:lawyers.view')->group(function () {
            Route::view('/lawyers', 'admin.lawyers')->name('lawyers');
            Route::view('/lawyer-details', 'admin.lawyer-details')->name('lawyer-details');
        });

        Route::middleware('permission:subscriptions.view')->group(function () {
            Route::view('/subscriptions', 'admin.subscriptions')->name('subscriptions');
            Route::view('/subscription-details', 'admin.subscription-details')->name('subscription-details');
        });
        Route::view('/create-plan', 'admin.create-plan')
            ->middleware('permission:subscriptions.manage')->name('create-plan');

        Route::view('/notifications', 'admin.notifications')
            ->middleware('permission:notifications.view')->name('notifications');

        Route::view('/settings', 'admin.settings')
            ->middleware('permission:settings.view')->name('settings');

        // Not delegable: only the Super Admin manages roles and staff.
        Route::middleware('super')->group(function () {
            Route::view('/roles', 'admin.roles')->name('roles');
            Route::view('/create-role', 'admin.create-role')->name('create-role');
            Route::view('/role-details', 'admin.role-details')->name('role-details');
            Route::view('/staff', 'admin.staff')->name('staff');
            Route::view('/create-staff', 'admin.create-staff')->name('create-staff');
            Route::view('/staff-details', 'admin.staff-details')->name('staff-details');
        });
    });
