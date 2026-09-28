<?php

namespace App\Services\Admin;

use App\Enums\UserStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\AccountAction;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin decisions on client and lawyer accounts.
 *
 * Every method re-reads the row under a lock, validates the transition against
 * the *current* state (two admins clicking at once cannot double-apply), writes
 * the change and its audit row in one transaction, and returns the fresh model.
 */
class AccountModerationService
{
    /** Blocks the account from logging in and signs it out everywhere. */
    public function suspend(User $user, Admin $by, string $reason): User
    {
        return $this->transition($user, function (User $locked) use ($by, $reason) {
            if ($locked->status === UserStatus::Suspended) {
                throw new InvalidStateTransition('This account is already suspended.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => UserStatus::Suspended])->save();

            // Tokens are also refused by middleware for non-active accounts;
            // deleting them means nothing is left to resurrect on reactivation.
            $locked->tokens()->delete();

            $this->record($locked, $by, AccountAction::SUSPENDED, $from->value, UserStatus::Suspended->value, $reason);
        });
    }

    /** Lets a suspended / inactive account log in again. */
    public function activate(User $user, Admin $by): User
    {
        return $this->transition($user, function (User $locked) use ($by) {
            if ($locked->status === UserStatus::Active) {
                throw new InvalidStateTransition('This account is already active.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => UserStatus::Active])->save();

            $this->record($locked, $by, AccountAction::ACTIVATED, $from->value, UserStatus::Active->value);
        });
    }

    /** @param  callable(User): void  $change */
    private function transition(User $user, callable $change): User
    {
        return DB::transaction(function () use ($user, $change) {
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $change($locked);

            return $locked->refresh();
        });
    }

    private function record(User $user, Admin $by, string $action, ?string $from, ?string $to, ?string $reason = null): void
    {
        AccountAction::create([
            'user_id' => $user->getKey(),
            'admin_id' => $by->getKey(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null ? trim($reason) : null,
        ]);
    }
}
