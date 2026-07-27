<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;

class NotificationService
{
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        try {
            $user->notifications()->create([
                'id'   => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\TradeNotification',
                'data' => array_merge(['title' => $title, 'body' => $body], $data),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store database notification: ' . $e->getMessage());
        }

        $this->sendFcmPush($user, $title, $body, $data);
    }

    public function sendWithData(User $user, string $title, string $body, array $data): void
    {
        try {
            $user->notifications()->create([
                'id'   => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\TradeNotification',
                'data' => array_merge(['title' => $title, 'body' => $body], $data),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store database notification: ' . $e->getMessage());
        }

        $this->sendFcmPush($user, $title, $body, $data);
    }

    private function sendFcmPush(User $user, string $title, string $body, array $data): void
    {
        try {
            $messaging = Firebase::messaging();

            $tokens = DeviceToken::where('user_id', $user->id)
                ->pluck('token')
                ->toArray();

            if (empty($tokens)) {
                return;
            }

            $dataPayload = array_merge(['title' => $title, 'body' => $body], $data);
            $dataPayload = array_map('strval', $dataPayload);

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification(['title' => $title, 'body' => $body])
                        ->withData($dataPayload);

                    $messaging->send($message);
                } catch (\Throwable $e) {
                    Log::warning('FCM send failed for token: ' . $e->getMessage());
                    if (str_contains($e->getMessage(), 'NOT_FOUND') || str_contains($e->getMessage(), 'invalid')) {
                        DeviceToken::where('token', $token)->delete();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('FCM push notification failed: ' . $e->getMessage());
        }
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
