<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RemittanceController extends Controller
{
    /**
     * List my remittances (as requester).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = MoneyTransfer::with([
            'initiator:id,name,phone',
            'agent:id,name,phone,agent_photo_path,last_activity_at',
            'agent.country',
            'paymentMethod:id,name',
        ])
            ->where('initiated_by', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('debt')) {
            $query->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            });
        }

        return response()->json($query->paginate((int) $request->get('per_page', 20)));
    }

    /**
     * Create a remittance, choosing an agent and providing destinator details.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:users,id'],
            'destinator_name' => ['required', 'string', 'max:255'],
            'destinator_phone' => ['nullable', 'string', 'max:50'],
            'destinator_address' => ['nullable', 'string', 'max:255'],
            'destinator_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'destinator_account_number' => ['nullable', 'string', 'max:255'],
            'destinator_notes' => ['nullable', 'string', 'max:2000'],
            'send_amount' => ['required', 'numeric', 'min:0.01'],
            'send_currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $agent = \App\Models\User::findOrFail($data['agent_id']);

        if (!$agent->hasRole('Agent')) {
            return response()->json(['message' => 'Selected user is not an agent.'], 422);
        }

        $transfer = DB::transaction(function () use ($data, $user) {
            $transfer = MoneyTransfer::create([
                'initiated_by' => $user->id,
                'assigned_agent_id' => $data['agent_id'],
                'sender_name' => $user->name,
                'sender_phone' => $user->phone,
                'recipient_name' => $data['destinator_name'],
                'recipient_phone' => $data['destinator_phone'] ?? null,
                'recipient_location' => $data['destinator_address'] ?? null,
                'destinator_name' => $data['destinator_name'],
                'destinator_phone' => $data['destinator_phone'] ?? null,
                'destinator_address' => $data['destinator_address'] ?? null,
                'destinator_payment_method_id' => $data['destinator_payment_method_id'] ?? null,
                'destinator_account_number' => $data['destinator_account_number'] ?? null,
                'destinator_notes' => $data['destinator_notes'] ?? null,
                'send_amount' => $data['send_amount'],
                'send_currency' => $data['send_currency'] ?? 'USD',
                'status' => MoneyTransfer::STATUS_PENDING,
            ]);

            $transfer->events()->create([
                'user_id' => $user->id,
                'type' => 'initiated',
                'from_status' => null,
                'to_status' => MoneyTransfer::STATUS_PENDING,
                'payload' => [
                    'agent_id' => $data['agent_id'],
                    'destinator_name' => $data['destinator_name'],
                    'send_amount' => $data['send_amount'],
                    'send_currency' => $data['send_currency'] ?? 'USD',
                ],
            ]);

            return $transfer;
        });

        return response()->json(
            $transfer->load([
                'initiator:id,name,phone',
                'agent:id,name,phone',
                'paymentMethod:id,name',
                'events.user:id,name',
            ]),
            201
        );
    }

    /**
     * Show a single remittance detail.
     */
    public function show(MoneyTransfer $moneyTransfer, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->initiated_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(
            $moneyTransfer->load([
                'initiator:id,name,phone',
                'agent:id,name,phone,agent_photo_path,last_activity_at',
                'agent.country',
                'paymentMethod:id,name',
                'events.user:id,name',
            ])
        );
    }

    /**
     * Upload requester proof of payment (optional — skip to mark as debt).
     */
    public function uploadRequesterProof(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->initiated_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($moneyTransfer->isClosed()) {
            return response()->json(['message' => 'This remittance is already closed.'], 422);
        }

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('remittance-proofs/requester', 'public');

        DB::transaction(function () use ($moneyTransfer, $path) {
            if ($moneyTransfer->requester_proof_path) {
                Storage::disk('public')->delete($moneyTransfer->requester_proof_path);
            }

            $moneyTransfer->update([
                'requester_proof_path' => $path,
                'requester_debt' => false,
                'requester_proof_uploaded_at' => now(),
            ]);

            $moneyTransfer->events()->create([
                'user_id' => auth()->id(),
                'type' => 'requester_proof_uploaded',
                'from_status' => $moneyTransfer->status,
                'to_status' => $moneyTransfer->status,
                'payload' => ['proof_path' => $path],
            ]);
        });

        return response()->json(
            $moneyTransfer->fresh()->load(['events.user:id,name'])
        );
    }

    /**
     * Confirm that the agent completed the remittance (moves to completed).
     */
    public function confirm(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->initiated_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($moneyTransfer->status !== MoneyTransfer::STATUS_EXECUTED) {
            return response()->json(['message' => 'Remittance must be executed by the agent before you can confirm.'], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($moneyTransfer, $oldStatus) {
            $moneyTransfer->update([
                'status' => MoneyTransfer::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $moneyTransfer->events()->create([
                'user_id' => auth()->id(),
                'type' => 'completed',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_COMPLETED,
            ]);
        });

        return response()->json(
            $moneyTransfer->fresh()->load(['events.user:id,name'])
        );
    }

    /**
     * Cancel a remittance (before agent accepts).
     */
    public function cancel(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->initiated_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!in_array($moneyTransfer->status, [MoneyTransfer::STATUS_PENDING], true)) {
            return response()->json(['message' => 'Can only cancel a pending remittance.'], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($moneyTransfer, $oldStatus, $user) {
            $moneyTransfer->update([
                'status' => MoneyTransfer::STATUS_CANCELLED,
            ]);

            $moneyTransfer->events()->create([
                'user_id' => $user->id,
                'type' => 'cancelled',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_CANCELLED,
                'payload' => ['cancelled_by' => 'requester'],
            ]);
        });

        return response()->json(
            $moneyTransfer->fresh()->load(['events.user:id,name'])
        );
    }

    /**
     * List debts — remittances with outstanding proofs.
     */
    public function debts(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = MoneyTransfer::with([
            'initiator:id,name,phone',
            'agent:id,name,phone',
            'paymentMethod:id,name',
        ])
            ->where('initiated_by', $user->id)
            ->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            })
            ->orderByDesc('created_at');

        if ($request->filled('side')) {
            if ($request->input('side') === 'my_debts') {
                $query->where('requester_debt', true);
            } elseif ($request->input('side') === 'owed_to_me') {
                $query->where('executor_debt', true);
            }
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        return response()->json($query->paginate((int) $request->get('per_page', 20)));
    }
}
