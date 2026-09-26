<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

/**
 * JSON endpoints behind the three-step "Forgot password" screen.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $service)
    {
    }

    /**
     * Always answers the same way, whether or not the account exists, so this
     * endpoint cannot be used to discover which emails/mobiles are registered.
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $this->service->sendOtp($request->identifier(), $request->ip());

        return response()->json([
            'message' => 'If the account exists, an OTP has been sent.',
            'resend_in' => (int) config('otp.resend_after'),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $token = $this->service->verifyOtp(
            $request->identifier(),
            $request->string('otp')->toString(),
        );

        if ($token === null) {
            return response()->json([
                'message' => 'Invalid or expired OTP. Please try again or request a new one.',
            ], 422);
        }

        return response()->json(['reset_token' => $token]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $done = $this->service->resetPassword(
            $request->identifier(),
            $request->string('reset_token')->toString(),
            $request->string('password')->toString(),
        );

        if (! $done) {
            return response()->json([
                'message' => 'This reset session has expired. Please start again.',
            ], 422);
        }

        return response()->json(['message' => 'Password updated. Please login with your new password.']);
    }
}
