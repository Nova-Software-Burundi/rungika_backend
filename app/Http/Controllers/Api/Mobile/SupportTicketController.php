<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
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
                'events.user:id,name',
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
            'message' => 'required|string|max:5000',
        ]);

        $message = $ticket->messages()->create([
            'author_id' => $user->id,
            'message' => $data['message'],
            'is_staff' => false,
        ]);

        return response()->json($message, 201);
    }
}
