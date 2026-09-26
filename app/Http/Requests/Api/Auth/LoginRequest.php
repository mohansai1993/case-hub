<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\UserType;
use App\Support\Identifier;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LoginRequest extends ApiFormRequest
{
    /** Failed attempts allowed per identifier + IP before a lockout. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function rules(): array
    {
        return [
            // The Client / Lawyer toggle on the login screen.
            'type' => ['required', Rule::enum(UserType::class)],
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function userType(): UserType
    {
        return UserType::from($this->input('type'));
    }

    public function identifier(): ?Identifier
    {
        return Identifier::parse($this->input('identifier'));
    }

    public function deviceName(): string
    {
        return $this->input('device_name') ?: 'mobile';
    }

    public function shouldRemember(): bool
    {
        return $this->boolean('remember');
    }

    /** @throws TooManyRequestsHttpException */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw new TooManyRequestsHttpException($seconds, "Too many login attempts. Please try again in {$seconds} seconds.");
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        $identifier = $this->identifier()?->value ?? Str::lower(trim((string) $this->input('identifier')));

        return 'api-login|' . sha1($identifier . '|' . $this->ip());
    }
}
