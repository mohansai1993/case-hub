<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\CaseMessageController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PracticeAreaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile app API (clients and lawyers)  -  /api/v1
|--------------------------------------------------------------------------
|
| Stateless: Sanctum bearer tokens, JSON only. Admin-panel authentication is
| separate (session guard `admin`, see routes/web.php).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Reference data for forms
    Route::get('practice-areas', [PracticeAreaController::class, 'index'])
        ->middleware('throttle:api')
        ->name('practice-areas');

    Route::prefix('auth')->name('auth.')->group(function () {
        // Registration (two forms behind the Client / Lawyer toggle)
        Route::post('register/client', [RegisterController::class, 'client'])
            ->middleware('throttle:api-register')->name('register.client');
        Route::post('register/lawyer', [RegisterController::class, 'lawyer'])
            ->middleware('throttle:api-register')->name('register.lawyer');

        // "Verify Your Account" screen
        Route::post('otp/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:api-otp-verify')->name('otp.verify');
        Route::post('otp/resend', [OtpController::class, 'resend'])
            ->middleware('throttle:api-otp-send')->name('otp.resend');

        Route::post('login', [LoginController::class, 'login'])
            ->middleware('throttle:api-login')->name('login');

        // Forgot password
        Route::post('forgot-password', [PasswordResetController::class, 'sendOtp'])
            ->middleware('throttle:api-otp-send')->name('password.send');
        Route::post('forgot-password/verify', [PasswordResetController::class, 'verifyOtp'])
            ->middleware('throttle:api-otp-verify')->name('password.verify');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:api-password-reset')->name('password.reset');

        // Signed-in
        Route::middleware(['auth:sanctum', 'api.active', 'throttle:api'])->group(function () {
            Route::get('me', [LoginController::class, 'me'])->name('me');
            Route::put('password', [LoginController::class, 'updatePassword'])->name('password.update');
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');
            Route::post('logout-all', [LoginController::class, 'logoutAll'])->name('logout-all');
        });
    });

    // Signed-in (push notifications)
    Route::middleware(['auth:sanctum', 'api.active', 'throttle:api'])->group(function () {
        Route::post('device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');

        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('read');
            Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        });

        // Cases + the per-case real-time chat (see docs/04-realtime-chat.md).
        Route::prefix('cases')->name('cases.')->group(function () {
            Route::get('/', [CaseController::class, 'index'])->name('index');
            Route::post('/', [CaseController::class, 'store'])->name('store');
            Route::get('{case}', [CaseController::class, 'show'])->name('show');
            Route::post('{case}/accept', [CaseController::class, 'accept'])->name('accept');
            Route::post('{case}/reject', [CaseController::class, 'reject'])->name('reject');

            Route::get('{case}/messages', [CaseMessageController::class, 'index'])->name('messages.index');
            Route::post('{case}/messages', [CaseMessageController::class, 'store'])->name('messages.store');
            Route::post('{case}/messages/read', [CaseMessageController::class, 'markRead'])->name('messages.read');
        });
    });
});
