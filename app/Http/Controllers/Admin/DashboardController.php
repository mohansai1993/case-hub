<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountAction;
use App\Models\ClientSubscription;
use App\Models\LegalCase;
use App\Models\SubscriptionCharge;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Every number here is a real query against existing tables - no sample/mock
 * data. "Recent Platform Activity" merges three real event sources
 * (registrations, subscription charges, account actions) since there is no
 * single unified event log table to read from.
 */
class DashboardController extends Controller
{
    private const ACTIVITY_LIMIT = 8;
    private const FAILED_PAYMENTS_LIMIT = 5;

    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'clientStats' => $this->clientStats(),
            'lawyerStats' => $this->lawyerStats(),
            'caseStats' => $this->caseStats(),
            'activeSubscriptions' => ClientSubscription::where('status', SubscriptionStatus::Active)->count(),
            'restrictedSubscriptions' => ClientSubscription::where('status', SubscriptionStatus::Restricted)->count(),
            'failedPayments' => SubscriptionCharge::where('status', 'failed')
                ->with(['subscription.client', 'plan'])
                ->latest()
                ->limit(self::FAILED_PAYMENTS_LIMIT)
                ->get(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /**
     * No admin action ever sets a client to Inactive (only suspend/activate
     * exist), so that bucket isn't shown here - it would always read 0 and
     * imply a feature that doesn't exist.
     *
     * @return array{total: int, active: int, suspended: int}
     */
    private function clientStats(): array
    {
        return [
            'total' => User::clients()->count(),
            'active' => User::clients()->where('status', UserStatus::Active)->count(),
            'suspended' => User::clients()->where('status', UserStatus::Suspended)->count(),
        ];
    }

    /**
     * No admin action ever rejects a lawyer (only suspend/activate exist),
     * so "rejected" isn't shown here - it would always read 0 and imply a
     * feature that doesn't exist. Suspended takes priority over verification
     * status so a verified-but-suspended lawyer isn't double-counted.
     *
     * @return array{total: int, verified: int, pending: int, suspended: int}
     */
    private function lawyerStats(): array
    {
        $notSuspended = fn () => User::lawyers()->where('status', '!=', UserStatus::Suspended);

        return [
            'total' => User::lawyers()->count(),
            'verified' => $notSuspended()->whereHas('lawyerProfile', fn ($q) => $q->where('verification_status', VerificationStatus::Verified))->count(),
            'pending' => $notSuspended()->whereHas('lawyerProfile', fn ($q) => $q->where('verification_status', VerificationStatus::Pending))->count(),
            'suspended' => User::lawyers()->where('status', UserStatus::Suspended)->count(),
        ];
    }

    /** @return array{pending: int, accepted: int, rejected: int, closed: int} */
    private function caseStats(): array
    {
        return [
            'pending' => LegalCase::where('status', CaseStatus::Pending)->count(),
            'accepted' => LegalCase::where('status', CaseStatus::Accepted)->count(),
            'rejected' => LegalCase::where('status', CaseStatus::Rejected)->count(),
            'closed' => LegalCase::where('status', CaseStatus::Closed)->count(),
        ];
    }

    /** @return Collection<int, array{at: \Illuminate\Support\Carbon, title: string, body: string}> */
    private function recentActivity(): Collection
    {
        $registrations = User::latest('created_at')->limit(self::ACTIVITY_LIMIT)->get()
            ->map(fn (User $user) => [
                'at' => $user->created_at,
                'title' => $user->isLawyer() ? 'New lawyer registered' : 'New client registered',
                'body' => "{$user->name} ({$user->email})",
            ]);

        $charges = SubscriptionCharge::where('status', 'succeeded')
            ->with(['subscription.client', 'plan'])
            ->latest()
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->filter(fn (SubscriptionCharge $charge) => $charge->subscription?->client)
            ->map(fn (SubscriptionCharge $charge) => [
                'at' => $charge->created_at,
                'title' => match ($charge->reason) {
                    'subscribe' => 'Subscription purchased',
                    'upgrade' => 'Subscription upgraded',
                    'downgrade' => 'Subscription downgraded',
                    default => 'Subscription renewed',
                },
                'body' => "{$charge->subscription->client->name} - {$charge->plan->name} ("
                    . config('billing.currency_symbol') . number_format($charge->amount) . ')',
            ]);

        $accountActions = AccountAction::with(['user', 'admin'])
            ->latest('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->filter(fn (AccountAction $action) => $action->user)
            ->map(fn (AccountAction $action) => [
                'at' => $action->created_at,
                'title' => $action->label(),
                'body' => $action->user->name . ($action->admin ? " by {$action->admin->name}" : ''),
            ]);

        return $registrations->concat($charges)->concat($accountActions)
            ->sortByDesc('at')
            ->take(self::ACTIVITY_LIMIT)
            ->values();
    }
}
