<?php

use App\Http\Controllers\Admin\AccountActionController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\LawyerController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PracticeAreaController;
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
    // Keep the alias GET-only so cached routes cannot intercept login POSTs.
    Route::get('/login', fn () => redirect('/'));

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

        // Clients: list / details need clients.view; suspend / activate need clients.update.
        Route::middleware('permission:clients.view')->group(function () {
            Route::get('/clients', [ClientController::class, 'index'])->name('clients');
            Route::get('/clients/{id}', [ClientController::class, 'show'])->whereUuid('id')->name('client-details');
        });
        Route::middleware('permission:clients.update')->group(function () {
            Route::post('/clients/{id}/suspend', [AccountActionController::class, 'suspend'])
                ->defaults('type', 'client')->whereUuid('id')->name('clients.suspend');
            Route::post('/clients/{id}/activate', [AccountActionController::class, 'activate'])
                ->defaults('type', 'client')->whereUuid('id')->name('clients.activate');
        });

        // Lawyers: same account controls as clients (no separate verification step).
        Route::middleware('permission:lawyers.view')->group(function () {
            Route::get('/lawyers', [LawyerController::class, 'index'])->name('lawyers');
            Route::get('/lawyers/{id}', [LawyerController::class, 'show'])->whereUuid('id')->name('lawyer-details');
        });
        // The specialization chips lawyers pick from at registration.
        Route::middleware('permission:lawyers.practice_areas')->prefix('practice-areas')->name('practice-areas.')->group(function () {
            Route::get('/', [PracticeAreaController::class, 'index'])->name('index');
            Route::post('/', [PracticeAreaController::class, 'store'])->name('store');
            Route::put('/{practiceArea}', [PracticeAreaController::class, 'update'])->name('update');
            Route::post('/{practiceArea}/toggle', [PracticeAreaController::class, 'toggle'])->name('toggle');
            Route::delete('/{practiceArea}', [PracticeAreaController::class, 'destroy'])->name('destroy');
        });
        Route::middleware('permission:lawyers.update')->group(function () {
            Route::post('/lawyers/{id}/suspend', [AccountActionController::class, 'suspend'])
                ->defaults('type', 'lawyer')->whereUuid('id')->name('lawyers.suspend');
            Route::post('/lawyers/{id}/activate', [AccountActionController::class, 'activate'])
                ->defaults('type', 'lawyer')->whereUuid('id')->name('lawyers.activate');
        });

        Route::middleware('permission:subscriptions.view')->group(function () {
            Route::view('/subscriptions', 'admin.subscriptions')->name('subscriptions');
            Route::view('/subscription-details', 'admin.subscription-details')->name('subscription-details');
        });
        Route::view('/create-plan', 'admin.create-plan')
            ->middleware('permission:subscriptions.manage')->name('create-plan');

        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
            Route::get('/notifications/recipients', [NotificationController::class, 'recipients'])->name('notifications.recipients');
        });
        Route::middleware('permission:notifications.create')->group(function () {
            Route::post('/notifications/drafts', [NotificationController::class, 'storeDraft'])->name('notifications.drafts.store');
            Route::post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
        });

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
