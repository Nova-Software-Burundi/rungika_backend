<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class SupportTicketMessageController extends Controller
{
    public function store(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $message = $ticket->messages()->create([
            ...$data,
            'user_id' => auth()->id(),
        ]);

        if (
            auth()->user()->hasRole(['super_admin', 'Admin', 'Operator', 'Agent'])
            && !$request->boolean('is_internal')
            && is_null($ticket->sla_first_response_at)
        ) {
            $ticket->update([
                'sla_first_response_at' => now(),
            ]);
        }

        $ticket->events()->create([
            'user_id' => auth()->id(),
            'type' => 'message_added',
        ]);

        // Notify ticket owner when staff sends a non-internal reply
        if (!$request->boolean('is_internal') && $ticket->user_id) {
            $ticketOwner = \App\Models\User::find($ticket->user_id);
            if ($ticketOwner) {
                $service = app(NotificationService::class);
                $service->sendWithData(
                    $ticketOwner,
                    'Support Ticket Reply',
                    auth()->user()->name . " replied to your ticket #{$ticket->reference}",
                    [
                        'ticket_id' => (string) $ticket->id,
                        'navigate_to_tab' => 'support',
                    ]
                );
            }
        }

        return response()->json($message->load('author:id,name'), 201);
    }
}
