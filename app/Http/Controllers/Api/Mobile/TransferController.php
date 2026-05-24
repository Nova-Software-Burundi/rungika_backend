<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $transfers = MoneyTransfer::with([
            'initiator:id,name,phone',
            'agent:id,name,phone',
        ])
            ->where(function ($q) use ($user) {
                $q->where('initiated_by', $user->id)
                    ->orWhere('assigned_agent_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->kyc_status !== 'verified') {
            return response()->json(['message' => 'KYC verification required. Please wait for approval.'], 403);
        }

        $data = $request->validate([
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_location' => ['nullable', 'string', 'max:255'],
            'send_amount' => ['required', 'numeric', 'min:0.01'],
            'send_currency' => ['nullable', 'string', 'max:10'],
            'usdt_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'payout_currency' => ['nullable', 'string', 'max:10'],
            'payout_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transfer = DB::transaction(function () use ($data, $user) {
            $transfer = MoneyTransfer::create([
                ...$data,
                'initiated_by' => $user->id,
                'assigned_agent_id' => $user->id,
                'send_currency' => $data['send_currency'] ?? 'USD',
                'payout_currency' => $data['payout_currency'] ?? 'ZMW',
                'status' => MoneyTransfer::STATUS_INITIATED,
            ]);

            $transfer->events()->create([
                'user_id' => $user->id,
                'type' => 'initiated',
                'from_status' => null,
                'to_status' => MoneyTransfer::STATUS_INITIATED,
                'payload' => [
                    'sender_name' => $transfer->sender_name,
                    'recipient_name' => $transfer->recipient_name,
                    'send_amount' => $transfer->send_amount,
                    'send_currency' => $transfer->send_currency,
                ],
            ]);

            return $transfer;
        });

        return response()->json(
            $transfer->load(['initiator:id,name,phone', 'events']),
            201
        );
    }

    public function show(MoneyTransfer $moneyTransfer, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->initiated_by !== $user->id && $moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(
            $moneyTransfer->load(['initiator:id,name,phone', 'agent:id,name,phone', 'events.user:id,name'])
        );
    }

    public function uploadUsdtProof(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->initiated_by !== $user->id && $moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($moneyTransfer->isClosed()) {
            return response()->json(['message' => 'This transfer is already closed.'], 422);
        }

        $request->validate([
            'usdt_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $moneyTransfer->status;
        $path = $request->file('usdt_proof')->store('money-transfers/usdt-proofs', 'public');

        DB::transaction(function () use ($moneyTransfer, $request, $path, $oldStatus) {
            if ($moneyTransfer->usdt_proof_path) {
                Storage::disk('public')->delete($moneyTransfer->usdt_proof_path);
            }

            $moneyTransfer->update([
                'usdt_proof_path' => $path,
                'usdt_proof_uploaded_at' => now(),
                'notes' => $request->filled('notes') ? $request->input('notes') : $moneyTransfer->notes,
                'status' => MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED,
            ]);

            $moneyTransfer->events()->create([
                'user_id' => $request->user()->id,
                'type' => 'usdt_proof_uploaded',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED,
                'payload' => ['notes' => $request->input('notes')],
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['initiator:id,name,phone', 'events']));
    }

    public function confirmUsdt(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Only the assigned agent can confirm USDT receipt.'], 403);
        }

        if (!$moneyTransfer->usdt_proof_path) {
            return response()->json(['message' => 'A USDT transfer screenshot is required before confirmation.'], 422);
        }

        if ($moneyTransfer->isClosed()) {
            return response()->json(['message' => 'This transfer is already closed.'], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($moneyTransfer, $request, $oldStatus) {
            $moneyTransfer->update([
                'usdt_confirmed_by' => $request->user()->id,
                'usdt_confirmed_at' => now(),
                'status' => MoneyTransfer::STATUS_USDT_RECEIVED,
            ]);

            $moneyTransfer->events()->create([
                'user_id' => $request->user()->id,
                'type' => 'usdt_confirmed',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_USDT_RECEIVED,
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['agent:id,name,phone', 'events']));
    }

    public function uploadPayoutProof(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Only the assigned agent can upload payout proof.'], 403);
        }

        if ($moneyTransfer->status !== MoneyTransfer::STATUS_USDT_RECEIVED) {
            return response()->json(['message' => 'USDT receipt must be confirmed first.'], 422);
        }

        $request->validate([
            'payout_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'payout_reference' => ['nullable', 'string', 'max:255'],
            'agent_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $moneyTransfer->status;
        $path = $request->file('payout_proof')->store('money-transfers/payout-proofs', 'public');

        DB::transaction(function () use ($moneyTransfer, $request, $path, $oldStatus) {
            if ($moneyTransfer->payout_proof_path) {
                Storage::disk('public')->delete($moneyTransfer->payout_proof_path);
            }

            $moneyTransfer->update([
                'payout_reference' => $request->input('payout_reference', $moneyTransfer->payout_reference),
                'payout_proof_path' => $path,
                'payout_proof_uploaded_at' => now(),
                'payout_confirmed_by' => $request->user()->id,
                'payout_confirmed_at' => now(),
                'agent_notes' => $request->filled('agent_notes') ? $request->input('agent_notes') : $moneyTransfer->agent_notes,
                'status' => MoneyTransfer::STATUS_COMPLETED,
            ]);

            $moneyTransfer->events()->create([
                'user_id' => $request->user()->id,
                'type' => 'payout_completed',
                'from_status' => $oldStatus,
                'to_status' => MoneyTransfer::STATUS_COMPLETED,
                'payload' => [
                    'payout_reference' => $request->input('payout_reference'),
                ],
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['agent:id,name,phone', 'events']));
    }
}
