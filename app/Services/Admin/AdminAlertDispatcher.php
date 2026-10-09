<?php

namespace App\Services\Admin;

use App\Enums\UserType;
use App\Models\Admin;
use App\Models\ClientSubscription;
use App\Models\User;
use App\Notifications\Admin\NewAccountRegistered;
use App\Notifications\Admin\StaffActionTaken;
use App\Notifications\Admin\SubscriptionRestricted as SubscriptionRestrictedAlert;
use Illuminate\Notifications\Notification as NotificationClass;
use Illuminate\Support\Facades\Notification;

/**
 * Fans event-driven system alerts out to every admin who can act on them -
 * the bell icon's source of truth. Each admin's copy has its own read state
 * (Notification::send() writes one row per recipient).
 */
class AdminAlertDispatcher
{
    public function newAccountRegistered(User $user): void
    {
        $permission = $user->type === UserType::Lawyer ? 'lawyers.view' : 'clients.view';

        $this->notify($permission, new NewAccountRegistered($user));
    }

    /** Oversight: Super Admins get an alert whenever a non-super-admin staff member moderates an account. */
    public function staffActionTaken(Admin $by, string $summary, ?string $actionUrl = null): void
    {
        if ($by->isSuperAdmin()) {
            return;
        }

        $recipients = Admin::with('role')->get()->filter(fn (Admin $admin) => $admin->isSuperAdmin());

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new StaffActionTaken($by, $summary, $actionUrl));
    }

    public function subscriptionRestricted(ClientSubscription $subscription): void
    {
        $this->notify('subscriptions.view', new SubscriptionRestrictedAlert($subscription));
    }

    private function notify(string $permission, NotificationClass $notification): void
    {
        $recipients = Admin::with('role')->get()->filter(fn (Admin $admin) => $admin->hasPermission($permission));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
