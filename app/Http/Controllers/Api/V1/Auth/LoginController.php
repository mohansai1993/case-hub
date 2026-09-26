<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Auth\AccountInactive;
use App\Exceptions\Auth\InvalidCredentials;
use App\Exceptions\Auth\MobileNotVerified;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\UserOtp;
use App\Services\AppAuth\ApiAuthenticator;
use App\Services\AppAuth\UserOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LoginController extends Controller
{
    public function __construct(
        private readonly ApiAuthenticator $authenticator,
        private readonly UserOtpService $otps,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        try {
            $user = $this->authenticator->attempt(
                $request->userType(),
                $request->identifier(),
                $request->string('password')->toString(),
            );
        } catch (InvalidCredentials) {
            $request->hitRateLimiter();

            // One answer for unknown account / wrong password / wrong tab.
            return response()->json([
                'message' => 'These credentials do not match our records.',
                'code' => 'invalid_credentials',
            ], 401);
        } catch (AccountInactive) {
            return response()->json([
                'message' => 'Your account is not active. Please contact support.',
                'code' => 'account_inactive',
            ], 403);
        } catch (MobileNotVerified $e) {
            // Password was right but registration was never finished: send a
            // fresh code (subject to the resend cooldown) and let the app open
            // the OTP screen.
            $this->sendVerificationOtp($e);

            return response()->json([
                'message' => 'Please verify your mobile number to continue.',
                'code' => 'mobile_not_verified',
                'data' => [
                    'mobile' => $e->user->mobile,
                    'resend_in' => (int) config('otp.app.resend_after'),
                ],
            ], 403);
        }

        $request->clearRateLimiter();

        $issued = $this->authenticator->issueToken($user, $request->deviceName(), $request->shouldRemember());

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => new UserResource($user->load('lawyerProfile', 'practiceAreas')),
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'expires_at' => $issued['expires_at']->toIso8601String(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['user' => new UserResource($request->user('sanctum')->load('lawyerProfile', 'practiceAreas'))],
        ]);
    }

    /** Signs out this device only. */
    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /** Signs out every device. */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user('sanctum')->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    private function sendVerificationOtp(MobileNotVerified $e): void
    {
        try {
            $this->otps->send($e->user, UserOtp::PURPOSE_VERIFY_MOBILE);
        } catch (Throwable $error) {
            report($error);
        }
    }
}
