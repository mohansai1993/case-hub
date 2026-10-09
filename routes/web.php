<?php

use App\Http\Controllers\Admin\AccountActionController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\LawyerController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PracticeAreaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
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

        // Topbar bell icon: every signed-in admin has their own feed, no permission gate.
        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/', [AlertController::class, 'index'])->name('index');
            Route::post('/read-all', [AlertController::class, 'markAllRead'])->name('read-all');
            Route::post('/{id}/read', [AlertController::class, 'markRead'])->name('read');
        });

        Route::get('/dashboard', DashboardController::class)
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

        // Lawyers: same account controls as clients, plus a verification
        // approval step - a lawyer cannot sign in until an admin approves them.
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
            Route::post('/lawyers/{id}/approve', [AccountActionController::class, 'approve'])
                ->defaults('type', 'lawyer')->whereUuid('id')->name('lawyers.approve');
            Route::post('/lawyers/{id}/reject', [AccountActionController::class, 'reject'])
                ->defaults('type', 'lawyer')->whereUuid('id')->name('lawyers.reject');
        });

        Route::get('/subscriptions', [PlanController::class, 'index'])
            ->middleware('permission:subscriptions.view')->name('subscriptions');
        Route::middleware('permission:subscriptions.manage')->group(function () {
            Route::get('/plans/create', [PlanController::class, 'create'])->name('create-plan');
            Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
            Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
            Route::post('/plans/{plan}/toggle', [PlanController::class, 'toggle'])->name('plans.toggle');
            Route::post('/plans/bulk-deactivate', [PlanController::class, 'bulkDeactivate'])->name('plans.bulk-deactivate');
            Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
        });

        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
            Route::get('/notifications/recipients', [NotificationController::class, 'recipients'])->name('notifications.recipients');
        });
        Route::middleware('permission:notifications.create')->group(function () {
            Route::post('/notifications/drafts', [NotificationController::class, 'storeDraft'])->name('notifications.drafts.store');
            Route::delete('/notifications/drafts/{draft}', [NotificationController::class, 'destroyDraft'])->name('notifications.drafts.destroy');
            Route::post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
        });

        Route::middleware('permission:settings.view')->group(function () {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
            Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
            Route::delete('/settings/account', [SettingsController::class, 'destroy'])->name('settings.destroy');
        });

        // Not delegable: only the Super Admin manages roles and staff.
        Route::middleware('super')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles');
            Route::get('/roles/create', [RoleController::class, 'create'])->name('create-role');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->name('role-details');
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::post('/roles/{role}/toggle', [RoleController::class, 'toggle'])->name('roles.toggle');

            Route::get('/staff', [StaffController::class, 'index'])->name('staff');
            Route::get('/staff/create', [StaffController::class, 'create'])->name('create-staff');
            Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
            Route::get('/staff/{admin}', [StaffController::class, 'show'])->whereUuid('admin')->name('staff-details');
            Route::get('/staff/{admin}/edit', [StaffController::class, 'edit'])->whereUuid('admin')->name('staff.edit');
            Route::put('/staff/{admin}', [StaffController::class, 'update'])->whereUuid('admin')->name('staff.update');
            Route::post('/staff/{admin}/toggle', [StaffController::class, 'toggle'])->whereUuid('admin')->name('staff.toggle');
            Route::post('/staff/{admin}/role', [StaffController::class, 'updateRole'])->whereUuid('admin')->name('staff.update-role');
            Route::post('/staff/{admin}/reset-password', [StaffController::class, 'resetPassword'])->whereUuid('admin')->name('staff.reset-password');
        });
    });
