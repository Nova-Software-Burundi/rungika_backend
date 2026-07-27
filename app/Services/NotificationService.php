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
        // Store in database notifications
        $user->notifications()->create([
            'id'   => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TradeNotification',
            'data' => array_merge(['title' => $title, 'body' => $body], $data),
        ]);

        // Send FCM push notification with data payload
        $this->sendFcmPush($user, $title, $body, $data);
    }

    /**
     * Send a push notification with a data payload for deep linking.
     * The mobile app's onMessageReceived() extracts type, remittance_id, ticket_id from data.
     */
    public function sendWithData(User $user, string $title, string $body, array $data): void
    {
        // Store in database notifications
        $user->notifications()->create([
            'id'   => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TradeNotification',
            'data' => array_merge(['title' => $title, 'body' => $body], $data),
        ]);

        // Send FCM push — data payload is what the mobile app uses for deep linking
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

            // Build data payload — all values must be strings for FCM data messages
            $dataPayload = array_merge(['title' => $title, 'body' => $body], $data);
            $dataPayload = array_map('strval', $dataPayload);

            // Send to each token with both notification + data payload
            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification(['title' => $title, 'body' => $body])
                        ->withData($dataPayload);

                    $messaging->send($message);
                } catch (\Throwable $e) {
                    Log::warning('FCM send failed for token: ' . $e->getMessage());
                    // Remove invalid tokens
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
