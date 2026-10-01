<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\VerifyResetOtpRequest;
use App\Services\AppAuth\AppPasswordResetService;
use Illuminate\Http\JsonResponse;

/** "Forgot password?" - OTP to whichever channel the identifier matches, then a new password. */
class PasswordResetController extends Controller
{
    public function __construct(private readonly AppPasswordResetService $service)
    {
    }

    /** Same answer whether or not the account exists. */
    public function sendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        $this->service->sendOtp($request->identifier());

        return response()->json([
            'message' => 'If the account exists, an OTP has been sent.',
            'data' => ['resend_in' => (int) config('otp.app.resend_after')],
        ]);
    }

    public function verifyOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $token = $this->service->verifyOtp($request->identifier(), $request->string('otp')->toString());

        if ($token === null) {
            return response()->json([
                'message' => 'Invalid or expired OTP. Please try again or request a new one.',
                'code' => 'invalid_otp',
            ], 422);
        }

        return response()->json(['data' => ['reset_token' => $token]]);
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
                'code' => 'invalid_reset_token',
            ], 422);
        }

        return response()->json(['message' => 'Password updated. Please login with your new password.']);
    }
}
