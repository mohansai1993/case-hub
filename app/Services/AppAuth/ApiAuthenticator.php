<?php

namespace App\Services\AppAuth;

use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Exceptions\Auth\AccountInactive;
use App\Exceptions\Auth\EmailNotVerified;
use App\Exceptions\Auth\InvalidCredentials;
use App\Exceptions\Auth\LawyerNotVerified;
use App\Models\User;
use App\Support\DecoyPassword;
use App\Support\Identifier;
use Illuminate\Support\Facades\Hash;

class ApiAuthenticator
{
    /** Devices signed in at once; the oldest tokens are revoked beyond this. */
    private const MAX_TOKENS_PER_USER = 10;

    /**
     * Verifies credentials for the given login tab (client / lawyer).
     *
     * @throws InvalidCredentials  unknown account, wrong password or wrong tab (indistinguishable)
     * @throws AccountInactive     deactivated / suspended (only after the password was right)
     * @throws EmailNotVerified    registration not completed (only after the password was right)
     * @throws LawyerNotVerified   a lawyer account an admin hasn't approved yet
     */
    public function attempt(UserType $type, ?Identifier $identifier, string $password): User
    {
        $user = $identifier ? User::findByIdentifier($identifier)?->load('lawyerProfile') : null;

        $passwordMatches = DecoyPassword::check($password, $user?->password);

        // A lawyer signing in on the Client tab (or vice versa) gets the same
        // answer as a wrong password.
        if (! $user || ! $passwordMatches || $user->type !== $type) {
            throw new InvalidCredentials;
        }

        if (! $user->isActive()) {
            throw new AccountInactive;
        }

        if (! $user->hasVerifiedEmail()) {
            throw new EmailNotVerified($user);
        }

        if ($user->isLawyer() && $user->lawyerProfile?->verification_status !== VerificationStatus::Verified) {
            throw new LawyerNotVerified($user);
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $password;
            $user->save();
        }

        return $user;
    }

    /**
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueToken(User $user, string $deviceName, bool $remember = false): array
    {
        $expiresAt = now()->addMinutes(
            $remember ? config('casehub.api_tokens.remember_ttl') : config('casehub.api_tokens.ttl'),
        );

        $newToken = $user->createToken($deviceName, [$user->type->value], $expiresAt);

        $this->pruneOldTokens($user);

        return ['token' => $newToken->plainTextToken, 'expires_at' => $expiresAt];
    }

    private function pruneOldTokens(User $user): void
    {
        $keep = $user->tokens()->latest('id')->limit(self::MAX_TOKENS_PER_USER)->pluck('id');

        $user->tokens()->whereNotIn('id', $keep)->delete();
    }
}
