<?php

namespace App\Services\Admin;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
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

    public function approveLawyer(User $lawyer, Admin $by): User
    {
        return $this->transition($lawyer, function (User $locked) use ($by) {
            $profile = $this->lawyerProfile($locked);

            if ($profile->verification_status === VerificationStatus::Verified) {
                throw new InvalidStateTransition('This lawyer is already verified.');
            }

            $from = $profile->verification_status;
            $profile->verification_status = VerificationStatus::Verified;
            $profile->verified_at = now();
            $profile->save();

            $this->record($locked, $by, AccountAction::LAWYER_APPROVED, $from->value, VerificationStatus::Verified->value);
        });
    }

    public function rejectLawyer(User $lawyer, Admin $by, string $reason): User
    {
        return $this->transition($lawyer, function (User $locked) use ($by, $reason) {
            $profile = $this->lawyerProfile($locked);

            if ($profile->verification_status === VerificationStatus::Rejected) {
                throw new InvalidStateTransition('This lawyer is already rejected.');
            }

            $from = $profile->verification_status;
            $profile->verification_status = VerificationStatus::Rejected;
            $profile->verified_at = null;
            $profile->save();

            $this->record($locked, $by, AccountAction::LAWYER_REJECTED, $from->value, VerificationStatus::Rejected->value, $reason);
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

    private function lawyerProfile(User $user): \App\Models\LawyerProfile
    {
        if (! $user->isLawyer() || ! $user->lawyerProfile) {
            throw new InvalidStateTransition('This account is not a lawyer.');
        }

        return $user->lawyerProfile;
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
