<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgentOrderController extends Controller
{
    /**
     * List orders assigned to this agent.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json(['message' => 'Only agents can access this endpoint.'], 403);
        }

        $query = MoneyTransfer::with([
            'initiator:id,name,phone',
            'paymentMethod:id,name',
        ])
            ->where('assigned_agent_id', $user->id)
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
     * Show order detail with full destinator info.
     */
    public function show(MoneyTransfer $moneyTransfer, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(
            $moneyTransfer->load([
                'initiator:id,name,phone',
                'paymentMethod:id,name',
                'events.user:id,name',
            ])
        );
    }

    /**
     * Accept an order (moves from pending to accepted).
     */
    public function accept(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($moneyTransfer->status !== MoneyTransfer::STATUS_PENDING) {
            return response()->json(['message' => 'Can only accept pending orders.'], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($moneyTransfer, $oldStatus, $user) {
            $moneyTransfer->update([
                'status' => MoneyTransfer::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            $moneyTransfer->events()->create([
                'user_id' => $user->id,
                'type' => 'accepted',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_ACCEPTED,
            ]);
        });

        return response()->json(
            $moneyTransfer->fresh()->load(['events.user:id,name'])
        );
    }

    /**
     * Execute an order — mark as executed, optionally upload proof.
     */
    public function execute(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($moneyTransfer->status !== MoneyTransfer::STATUS_ACCEPTED) {
            return response()->json(['message' => 'Order must be accepted before execution.'], 422);
        }

        $request->validate([
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'agent_notes' => ['nullable', 'string', 'max:2000'],
            'payout_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($request, $moneyTransfer, $oldStatus, $user) {
            $updateData = [
                'status' => MoneyTransfer::STATUS_EXECUTED,
                'executed_at' => now(),
                'agent_notes' => $request->input('agent_notes', $moneyTransfer->agent_notes),
                'payout_reference' => $request->input('payout_reference', $moneyTransfer->payout_reference),
            ];

            if ($request->hasFile('proof')) {
                $path = $request->file('proof')->store('remittance-proofs/executor', 'public');
                $updateData['executor_proof_path'] = $path;
                $updateData['executor_debt'] = false;
                $updateData['executor_proof_uploaded_at'] = now();
            } else {
                $updateData['executor_debt'] = true;
            }

            $moneyTransfer->update($updateData);

            $moneyTransfer->events()->create([
                'user_id' => $user->id,
                'type' => 'executed',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_EXECUTED,
                'payload' => [
                    'has_proof' => $request->hasFile('proof'),
                    'is_debt' => !$request->hasFile('proof'),
                    'notes' => $request->input('agent_notes'),
                ],
            ]);
        });

        return response()->json(
            $moneyTransfer->fresh()->load(['events.user:id,name'])
        );
    }

    /**
     * Upload execution proof after the fact (resolve agent debt).
     */
    public function uploadProof(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!in_array($moneyTransfer->status, [
            MoneyTransfer::STATUS_EXECUTED,
            MoneyTransfer::STATUS_COMPLETED,
        ], true)) {
            return response()->json(['message' => 'Cannot upload proof for this remittance status.'], 422);
        }

        if (!$moneyTransfer->executor_debt) {
            return response()->json(['message' => 'Proof has already been uploaded.'], 422);
        }

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('remittance-proofs/executor', 'public');

        DB::transaction(function () use ($moneyTransfer, $path) {
            if ($moneyTransfer->executor_proof_path) {
                Storage::disk('public')->delete($moneyTransfer->executor_proof_path);
            }

            $moneyTransfer->update([
                'executor_proof_path' => $path,
                'executor_debt' => false,
                'executor_proof_uploaded_at' => now(),
            ]);

            $moneyTransfer->events()->create([
                'user_id' => auth()->id(),
                'type' => 'executor_proof_uploaded',
                'from_status' => $moneyTransfer->status,
                'to_status' => $moneyTransfer->status,
                'payload' => ['proof_path' => $path],
            ]);
        });

        return response()->json(
            $moneyTransfer->fresh()->load(['events.user:id,name'])
        );
    }
}
