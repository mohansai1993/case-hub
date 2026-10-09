<?php

namespace App\Services\Admin;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Exceptions\InvalidStateTransition;
use App\Models\AccountAction;
use App\Models\Admin;
use App\Models\LawyerProfile;
use App\Models\User;
use App\Notifications\LawyerApproved;
use App\Notifications\LawyerRejected;
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
    public function __construct(private readonly AdminAlertDispatcher $alerts)
    {
    }

    /** Blocks the account from logging in and signs it out everywhere. */
    public function suspend(User $user, Admin $by, string $reason): User
    {
        $result = $this->transition($user, function (User $locked) use ($by, $reason) {
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

        $this->alerts->staffActionTaken($by, "suspended {$result->name}'s account.", $this->accountUrl($result));

        return $result;
    }

    /** Lets a suspended / inactive account log in again. */
    public function activate(User $user, Admin $by): User
    {
        $result = $this->transition($user, function (User $locked) use ($by) {
            if ($locked->status === UserStatus::Active) {
                throw new InvalidStateTransition('This account is already active.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => UserStatus::Active])->save();

            $this->record($locked, $by, AccountAction::ACTIVATED, $from->value, UserStatus::Active->value);
        });

        $this->alerts->staffActionTaken($by, "activated {$result->name}'s account.", $this->accountUrl($result));

        return $result;
    }

    /** Approves a lawyer's verification request so they can sign in. */
    public function approveLawyer(User $lawyer, Admin $by): User
    {
        $this->guardIsLawyer($lawyer);

        $result = DB::transaction(function () use ($lawyer, $by) {
            $profile = LawyerProfile::whereKey($lawyer->getKey())->lockForUpdate()->firstOrFail();

            if ($profile->verification_status === VerificationStatus::Verified) {
                throw new InvalidStateTransition('This advocate is already verified.');
            }

            $from = $profile->verification_status;
            $profile->forceFill([
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ])->save();

            $this->record($lawyer, $by, AccountAction::LAWYER_APPROVED, $from->value, VerificationStatus::Verified->value);

            $lawyer->notify(new LawyerApproved);

            return $lawyer->refresh();
        });

        $this->alerts->staffActionTaken($by, "approved {$result->name} as a lawyer.", $this->accountUrl($result));

        return $result;
    }

    /** Rejects a lawyer's verification request; they cannot sign in until approved. */
    public function rejectLawyer(User $lawyer, Admin $by, string $reason): User
    {
        $this->guardIsLawyer($lawyer);

        $result = DB::transaction(function () use ($lawyer, $by, $reason) {
            $profile = LawyerProfile::whereKey($lawyer->getKey())->lockForUpdate()->firstOrFail();

            if ($profile->verification_status === VerificationStatus::Rejected) {
                throw new InvalidStateTransition('This advocate has already been rejected.');
            }

            $from = $profile->verification_status;
            $profile->forceFill([
                'verification_status' => VerificationStatus::Rejected,
                'verified_at' => null,
            ])->save();

            $this->record($lawyer, $by, AccountAction::LAWYER_REJECTED, $from->value, VerificationStatus::Rejected->value, $reason);

            $lawyer->notify(new LawyerRejected($reason));

            return $lawyer->refresh();
        });

        $this->alerts->staffActionTaken($by, "rejected {$result->name}'s lawyer application.", $this->accountUrl($result));

        return $result;
    }

    private function accountUrl(User $user): string
    {
        return $user->type === UserType::Lawyer
            ? route('admin.lawyer-details', $user->user_id)
            : route('admin.client-details', $user->user_id);
    }

    private function guardIsLawyer(User $user): void
    {
        if ($user->type !== UserType::Lawyer) {
            throw new InvalidStateTransition('This account is not a lawyer.');
        }
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
