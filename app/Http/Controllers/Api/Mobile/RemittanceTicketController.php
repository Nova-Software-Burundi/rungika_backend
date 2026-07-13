<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemittanceTicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'remittance_id' => ['required', 'integer', 'exists:money_transfers,id'],
            'support_category_id' => ['required', 'integer', 'exists:support_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        /** @var MoneyTransfer $remittance */
        $remittance = MoneyTransfer::findOrFail($data['remittance_id']);

        if ($remittance->initiated_by !== $user->id && $remittance->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'You are not a party to this remittance.'], 403);
        }

        if ($remittance->isClosed()) {
            return response()->json(['message' => 'Cannot open a ticket for a closed remittance.'], 422);
        }

        $ticket = DB::transaction(function () use ($data, $user, $remittance) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'support_category_id' => $data['support_category_id'],
                'subject_type' => 'money-transfer',
                'subject_id' => $remittance->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => SupportTicket::STATUS_OPEN,
            ]);

            $ticket->events()->create([
                'user_id' => $user->id,
                'type' => 'created',
                'payload' => [
                    'remittance_reference' => $remittance->reference,
                    'remittance_status' => $remittance->status,
                ],
            ]);

            if ($remittance->status !== MoneyTransfer::STATUS_DISPUTED) {
                $oldStatus = $remittance->status;
                $remittance->update(['status' => MoneyTransfer::STATUS_DISPUTED]);

                $remittance->events()->create([
                    'user_id' => $user->id,
                    'type' => 'disputed',
                    'from_status' => $oldStatus,
                    'to_status' => MoneyTransfer::STATUS_DISPUTED,
                    'payload' => [
                        'ticket_id' => $ticket->id,
                        'ticket_reference' => $ticket->reference,
                    ],
                ]);
            }

            return $ticket;
        });

        return response()->json(
            $ticket->load(['category', 'subject']),
            201
        );
    }
}
