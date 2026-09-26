<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\MobileRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AppAuth\AccountVerificationService;
use App\Services\AppAuth\ApiAuthenticator;
use Illuminate\Http\JsonResponse;

/** The "Verify Your Account" screen. */
class OtpController extends Controller
{
    public function __construct(
        private readonly AccountVerificationService $verification,
        private readonly ApiAuthenticator $authenticator,
    ) {
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->verification->verify($request->mobile(), $request->string('otp')->toString());

        if (! $user) {
            return response()->json([
                'message' => 'Invalid or expired OTP. Please try again or request a new one.',
                'code' => 'invalid_otp',
            ], 422);
        }

        // Verification completes registration: sign the user straight in.
        $issued = $this->authenticator->issueToken($user, $request->deviceName());

        return response()->json([
            'message' => 'Account verified.',
            'data' => [
                'user' => new UserResource($user->load('lawyerProfile', 'practiceAreas')),
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'expires_at' => $issued['expires_at']->toIso8601String(),
            ],
        ]);
    }

    /** Same answer whether or not the number is registered. */
    public function resend(MobileRequest $request): JsonResponse
    {
        $this->verification->resend($request->mobile());

        return response()->json([
            'message' => 'If a pending account exists for this number, an OTP has been sent.',
            'data' => ['resend_in' => (int) config('otp.app.resend_after')],
        ]);
    }
}
