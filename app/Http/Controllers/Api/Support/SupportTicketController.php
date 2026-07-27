<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}
    public function index(Request $request)
    {
        $query = SupportTicket::with(['category', 'assignee', 'user'])
            ->latest();

        if (!auth()->user()->hasRole(['super_admin', 'Admin', 'Operator', 'Agent'])) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('category_id')) {
            $query->where('support_category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'support_category_id' => 'required|exists:support_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $ticket = SupportTicket::create([
            ...$data,
            'user_id' => auth()->id(),
        ]);

        $ticket->events()->create([
            'user_id' => auth()->id(),
            'type' => 'created',
        ]);

        return response()->json($ticket, 201);
    }

    public function show(SupportTicket $ticket)
    {
        return $ticket->load([
            'messages.author',
            'events.actor',
            'category',
            'assignee',
            'user',
            'subject',
        ]);
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,waiting,resolved,closed',
        ]);

        $old = $ticket->status;
        $ticket->update(['status' => $request->status]);

        if ($request->status === 'resolved') {
            $ticket->update(['resolved_at' => now()]);
        }

        if ($request->status === 'closed') {
            $ticket->update(['closed_at' => now()]);
        }

        $ticket->events()->create([
            'user_id' => auth()->id(),
            'type' => 'status_changed',
            'payload' => ['from' => $old, 'to' => $request->status],
        ]);

        // When a remittance-linked ticket is resolved or closed, revert from disputed
        if (in_array($request->status, ['resolved', 'closed'], true) && $ticket->subject_type === 'money-transfer') {
            /** @var \App\Models\MoneyTransfer|null $remittance */
            $remittance = $ticket->subject;

            if ($remittance && $remittance->status === \App\Models\MoneyTransfer::STATUS_DISPUTED) {
                $lastEvent = $remittance->events()
                    ->where('type', 'disputed')
                    ->latest()
                    ->first();

                $previousStatus = $lastEvent?->from_status ?? \App\Models\MoneyTransfer::STATUS_EXECUTED;

                $remittance->update(['status' => $previousStatus]);

                $remittance->events()->create([
                    'user_id' => auth()->id(),
                    'type' => 'dispute_resolved',
                    'from_status' => \App\Models\MoneyTransfer::STATUS_DISPUTED,
                    'to_status' => $previousStatus,
                    'payload' => ['ticket_id' => $ticket->id],
                ]);
            }
        }

        // Notify ticket owner of status change
        if ($ticket->user_id) {
            $ticketOwner = \App\Models\User::find($ticket->user_id);
            if ($ticketOwner) {
                $statusLabel = ucfirst(str_replace('_', ' ', $request->status));
                $this->notificationService->sendWithData(
                    $ticketOwner,
                    "Ticket {$statusLabel}",
                    "Your ticket #{$ticket->reference} has been {$request->status}",
                    [
                        'ticket_id' => (string) $ticket->id,
                        'navigate_to_tab' => 'support',
                    ]
                );
            }
        }

        return $ticket;
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $ticket->update(['assigned_to' => $request->assigned_to]);

        $ticket->events()->create([
            'user_id' => auth()->id(),
            'type' => 'assigned',
            'payload' => ['assigned_to' => $request->assigned_to],
        ]);

        return $ticket;
    }
}
