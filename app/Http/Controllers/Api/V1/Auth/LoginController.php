<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\VerificationStatus;
use App\Exceptions\Auth\AccountInactive;
use App\Exceptions\Auth\EmailNotVerified;
use App\Exceptions\Auth\InvalidCredentials;
use App\Exceptions\Auth\LawyerNotVerified;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\UpdatePasswordRequest;
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
        } catch (EmailNotVerified $e) {
            // Password was right but registration was never finished: send a
            // fresh code (subject to the resend cooldown) and let the app open
            // the OTP screen.
            $this->sendVerificationOtp($e);

            return response()->json([
                'message' => 'Please verify your email to continue.',
                'code' => 'email_not_verified',
                'data' => [
                    'email' => $e->user->email,
                    'resend_in' => (int) config('otp.app.resend_after'),
                ],
            ], 403);
        } catch (LawyerNotVerified $e) {
            $status = $e->user->lawyerProfile->verification_status;

            return response()->json([
                'message' => $status === VerificationStatus::Rejected
                    ? 'Your advocate application was not approved. Contact support for details.'
                    : 'Your account is awaiting admin approval. We will notify you once it is reviewed.',
                'code' => 'lawyer_not_verified',
                'data' => ['verification_status' => $status->value],
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

    /**
     * Changes the password for the signed-in client or lawyer (one endpoint,
     * same User model either way). Keeps this device signed in; every other
     * device's token is revoked, same spirit as a forgot-password reset.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $currentToken = $user->currentAccessToken();

        $user->forceFill(['password' => $request->password()])->save();

        $user->tokens()->when($currentToken, fn ($query) => $query->where('id', '!=', $currentToken->id))->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    private function sendVerificationOtp(EmailNotVerified $e): void
    {
        try {
            $this->otps->send($e->user, UserOtp::PURPOSE_VERIFY_EMAIL, 'email');
        } catch (Throwable $error) {
            report($error);
        }
    }
}
