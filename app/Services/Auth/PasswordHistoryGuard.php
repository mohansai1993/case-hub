<?php

namespace App\Services\Auth;

use App\Models\PasswordHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Blocks reusing any of an account's last N passwords, for both guards
 * (Admin and the client/lawyer User model share this).
 *
 * The live password on the account counts as the most recent one, so a
 * 5-password limit only needs the 4 before it kept in `password_histories`.
 */
class PasswordHistoryGuard
{
    private const DEFAULT_LIMIT = 5;

    /** Retention: how many old hashes to keep around per account. */
    private const KEEP = 10;

    /** @throws ValidationException if $plainPassword matches one of the last $limit passwords */
    public function reject(Model $authenticatable, string $plainPassword, int $limit = self::DEFAULT_LIMIT): void
    {
        if ($this->wasRecentlyUsed($authenticatable, $plainPassword, $limit)) {
            throw ValidationException::withMessages([
                'password' => "You cannot reuse one of your last {$limit} passwords.",
            ]);
        }
    }

    public function wasRecentlyUsed(Model $authenticatable, string $plainPassword, int $limit = self::DEFAULT_LIMIT): bool
    {
        if ($authenticatable->password && Hash::check($plainPassword, $authenticatable->password)) {
            return true;
        }

        return $this->history($authenticatable)
            ->limit(max($limit - 1, 0))
            ->pluck('password_hash')
            ->contains(fn (string $hash) => Hash::check($plainPassword, $hash));
    }

    /** Call BEFORE overwriting $authenticatable->password with the new one. */
    public function remember(Model $authenticatable): void
    {
        if (! $authenticatable->password) {
            return;
        }

        PasswordHistory::create([
            'authenticatable_type' => $authenticatable->getMorphClass(),
            'authenticatable_id' => $authenticatable->getKey(),
            'password_hash' => $authenticatable->password,
        ]);

        $staleIds = $this->history($authenticatable)->pluck('id')->slice(self::KEEP);

        if ($staleIds->isNotEmpty()) {
            PasswordHistory::whereIn('id', $staleIds)->delete();
        }
    }

    private function history(Model $authenticatable)
    {
        return PasswordHistory::query()
            ->where('authenticatable_type', $authenticatable->getMorphClass())
            ->where('authenticatable_id', $authenticatable->getKey())
            ->latest('id');
    }
}
