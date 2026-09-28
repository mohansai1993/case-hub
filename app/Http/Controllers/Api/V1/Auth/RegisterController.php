<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterClientRequest;
use App\Http\Requests\Api\Auth\RegisterLawyerRequest;
use App\Http\Resources\UserResource;
use App\Services\AppAuth\RegistrationResult;
use App\Services\AppAuth\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function client(RegisterClientRequest $request): JsonResponse
    {
        return $this->respond($this->registration->registerClient($request->validated()));
    }

    public function lawyer(RegisterLawyerRequest $request): JsonResponse
    {
        return $this->respond($this->registration->registerLawyer(
            $request->validated(),
            $request->file('photo'),
        ));
    }

    /**
     * The account exists but cannot log in until the email is verified; the
     * response tells the app to open the OTP screen.
     */
    private function respond(RegistrationResult $result): JsonResponse
    {
        $user = $result->user->load('lawyerProfile', 'practiceAreas');

        return response()->json([
            'message' => $result->otpSent
                ? 'Account created. We have sent an OTP to your email address.'
                : 'Account created, but the OTP could not be sent. Please request a new one.',
            'data' => [
                'user' => new UserResource($user),
                'otp' => [
                    'sent' => $result->otpSent,
                    'length' => (int) config('otp.app.length'),
                    'resend_in' => (int) config('otp.app.resend_after'),
                    'expires_in' => (int) config('otp.app.ttl') * 60,
                ],
            ],
        ], 201);
    }
}
