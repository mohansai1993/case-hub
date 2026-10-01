<?php

namespace App\Notifications\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Powers the admin topbar's bell icon. Delivered synchronously on purpose
 * (no ShouldQueue): the bell is polled right after this fires, so a queued
 * delivery sitting behind a worker that isn't running would make the alert
 * silently never show up.
 */
class NewAccountRegistered extends Notification
{
    public function __construct(private readonly User $user)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isLawyer = $this->user->type === UserType::Lawyer;

        return [
            'title' => $isLawyer ? 'New lawyer registered' : 'New client registered',
            'body' => "{$this->user->name} ({$this->user->email})",
            'action_url' => $isLawyer
                ? route('admin.lawyer-details', $this->user->user_id)
                : route('admin.client-details', $this->user->user_id),
        ];
    }
}
