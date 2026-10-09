<?php

namespace App\Notifications\Admin;

use App\Models\ClientSubscription;
use Illuminate\Notifications\Notification;

/**
 * Powers the admin topbar's bell icon when a client's grace period expires
 * and their subscription flips to Restricted - staff with subscriptions
 * access should know without having to notice it on the subscriptions page.
 */
class SubscriptionRestricted extends Notification
{
    public function __construct(private readonly ClientSubscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $client = $this->subscription->client;

        return [
            'title' => 'Subscription restricted',
            'body' => ($client?->name ?? 'A client') . "'s grace period ended - account access is now restricted.",
            'action_url' => $client ? route('admin.client-details', $client->user_id) : null,
        ];
    }
}
