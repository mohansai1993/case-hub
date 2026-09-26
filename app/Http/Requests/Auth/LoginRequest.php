<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\AuthAuditLogger;
use App\Support\Identifier;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Failed attempts allowed per identifier + IP before a lockout. */
    private const MAX_ATTEMPTS = 5;

    /** Lockout length in seconds. */
    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Please enter your email or mobile number.',
            'password.required' => 'Please enter your password.',
        ];
    }

    public function identifier(): ?Identifier
    {
        return Identifier::parse($this->input('identifier'));
    }

    public function shouldRemember(): bool
    {
        return $this->boolean('remember');
    }

    /** @throws ValidationException */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));
        app(AuthAuditLogger::class)->record(AuthAuditLogger::LOGIN_LOCKOUT, null, $this->identifier());

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Keyed on the *normalised* identifier + IP, so "A@x.com" and "a@x.com"
     * (or "+91 98..." and "98...") cannot be used to dodge the limit.
     */
    private function throttleKey(): string
    {
        $identifier = $this->identifier()?->value ?? Str::lower(trim((string) $this->input('identifier')));

        return 'admin-login|' . sha1($identifier . '|' . $this->ip());
    }
}
