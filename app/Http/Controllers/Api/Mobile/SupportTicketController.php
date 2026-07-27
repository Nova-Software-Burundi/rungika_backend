<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SupportTicket::with('category')
            ->where('user_id', $user->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate((int) $request->get('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'support_category_id' => 'required|exists:support_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $ticket = SupportTicket::create([
            ...$data,
            'user_id' => $user->id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        $ticket->events()->create([
            'user_id' => $user->id,
            'type' => 'created',
        ]);

        // Notify admins
        User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['super_admin', 'Admin']);
        })->get()->each(function ($admin) use ($ticket, $user) {
            $this->notificationService->sendWithData(
                $admin,
                'New Support Ticket',
                "{$user->name} opened ticket #{$ticket->reference}: {$ticket->title}",
                [
                    'ticket_id' => (string) $ticket->id,
                    'navigate_to_tab' => 'support',
                ]
            );
        });

        return response()->json(
            $ticket->load('category'),
            201
        );
    }

    public function show(SupportTicket $ticket, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(
            $ticket->load([
                'category',
                'messages.author:id,name',
                'events.actor:id,name',
            ])
        );
    }

    public function sendMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            return response()->json(['message' => 'Cannot reply to a closed ticket.'], 422);
        }

        $data = $request->validate([
            'message' => 'required_without:attachment|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('ticket-attachments', 'public');
        }

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $data['message'] ?? '',
            'attachment_path' => $attachmentPath,
            'is_internal' => false,
        ]);

        // Notify admins about new message
        User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['super_admin', 'Admin']);
        })->get()->each(function ($admin) use ($ticket, $user) {
            $this->notificationService->sendWithData(
                $admin,
                'New Message on Ticket',
                "{$user->name} replied to ticket #{$ticket->reference}",
                [
                    'ticket_id' => (string) $ticket->id,
                    'navigate_to_tab' => 'support',
                ]
            );
        });

        return response()->json($message->load('author:id,name'), 201);
    }
}
