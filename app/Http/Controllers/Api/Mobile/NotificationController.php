<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}

    public function index(Request $request)
    {
        $notifications = auth()->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $notifications;
    }

    public function read($id)
    {
        $this->notificationService->markAsRead($id, auth()->user());
        return response()->json(['message' => 'Marked as read.']);
    }

    public function readAll()
    {
        $this->notificationService->markAllAsRead(auth()->user());
        return response()->json(['message' => 'All marked as read.']);
    }

    public function unreadCount()
    {
        $count = auth()->user()->unreadNotifications()->count();
        return response()->json(['unread_count' => $count]);
    }
}
