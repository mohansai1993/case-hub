<?php

namespace App\Services\Admin;

use App\Enums\UserType;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\Admin\NewAccountRegistered;
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

    private function notify(string $permission, NotificationClass $notification): void
    {
        $recipients = Admin::with('role')->get()->filter(fn (Admin $admin) => $admin->hasPermission($permission));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
