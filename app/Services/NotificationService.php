<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        $user->notifications()->create([
            'id'   => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TradeNotification',
            'data' => array_merge(['title' => $title, 'body' => $body], $data),
        ]);
    }

    public function markAsRead(string $notificationId, User $user): void
    {
        $notification = $user->notifications()->where('id', $notificationId)->first();
        if ($notification && !$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
