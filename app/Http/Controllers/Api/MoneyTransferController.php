<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MoneyTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MoneyTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MoneyTransfer::with([
            'initiator:id,name,email',
            'senderUser:id,name,email,phone',
            'agent:id,name,email',
            'usdtConfirmer:id,name,email',
            'payoutConfirmer:id,name,email',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%")
                    ->orWhere('sender_phone', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->get('per_page', 15)));
    }

    public function stats(): JsonResponse
    {
        $statusCounts = MoneyTransfer::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total' => MoneyTransfer::count(),
            'initiated' => (int) ($statusCounts[MoneyTransfer::STATUS_INITIATED] ?? 0),
            'awaiting_agent' => (int) ($statusCounts[MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED] ?? 0),
            'ready_for_payout' => (int) ($statusCounts[MoneyTransfer::STATUS_USDT_RECEIVED] ?? 0),
            'completed' => (int) ($statusCounts[MoneyTransfer::STATUS_COMPLETED] ?? 0),
            'cancelled' => (int) ($statusCounts[MoneyTransfer::STATUS_CANCELLED] ?? 0),
            'send_volume' => MoneyTransfer::whereNotIn('status', [MoneyTransfer::STATUS_CANCELLED])->sum('send_amount'),
            'payout_volume' => MoneyTransfer::where('status', MoneyTransfer::STATUS_COMPLETED)->sum('payout_amount'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:50'],
            'sender_user_id' => ['nullable', 'integer', 'exists:users,id'],
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

        $transfer = DB::transaction(function () use ($data, $request) {
            $senderUserId = $data['sender_user_id'] ?? null;

            if (!$senderUserId && ($data['sender_phone'] ?? null)) {
                $existing = User::where('phone', $data['sender_phone'])->first();
                if ($existing) {
                    $senderUserId = $existing->id;
                }
            }

            if (!$senderUserId && ($data['sender_phone'] ?? null)) {
                $user = User::create([
                    'name' => $data['sender_name'],
                    'phone' => $data['sender_phone'],
                    'email' => 'sender-' . now()->format('YmdHis') . '-' . mt_rand(1000, 9999) . '@rungika.app',
                    'password' => Hash::make(str()->random(32)),
                    'kyc_status' => 'pending',
                ]);

                $user->assignRole('Customer');

                Contact::create([
                    'user_id' => $user->id,
                    'type' => 'phone',
                    'value' => $data['sender_phone'],
                    'is_primary' => true,
                ]);

                $senderUserId = $user->id;
            }

            if ($senderUserId && ($data['sender_phone'] ?? null)) {
                Contact::firstOrCreate([
                    'user_id' => $senderUserId,
                    'type' => 'phone',
                    'value' => $data['sender_phone'],
                ]);
            }

            $transfer = MoneyTransfer::create([
                ...$data,
                'sender_user_id' => $senderUserId,
                'initiated_by' => $request->user()->id,
                'send_currency' => $data['send_currency'] ?? 'USD',
                'payout_currency' => $data['payout_currency'] ?? 'ZMW',
                'status' => MoneyTransfer::STATUS_INITIATED,
            ]);

            $this->recordEvent($transfer, 'initiated', null, MoneyTransfer::STATUS_INITIATED, [
                'sender_name' => $transfer->sender_name,
                'recipient_name' => $transfer->recipient_name,
                'send_amount' => $transfer->send_amount,
                'send_currency' => $transfer->send_currency,
            ]);

            return $transfer;
        });

        return response()->json($transfer->load(['initiator:id,name,email', 'senderUser:id,name,email,phone', 'events.user:id,name,email']), 201);
    }

    public function show(MoneyTransfer $moneyTransfer): JsonResponse
    {
        return response()->json($moneyTransfer->load([
            'initiator:id,name,email',
            'senderUser:id,name,email,phone',
            'agent:id,name,email',
            'usdtConfirmer:id,name,email',
            'payoutConfirmer:id,name,email',
            'events.user:id,name,email',
        ]));
    }

    public function uploadUsdtProof(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $request->validate([
            'usdt_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($moneyTransfer->isClosed()) {
            return response()->json(['message' => 'This transfer is already closed.'], 422);
        }

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

            $this->recordEvent($moneyTransfer, 'usdt_proof_uploaded', $oldStatus, MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED, [
                'proof_path' => $path,
                'notes' => $request->input('notes'),
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['initiator:id,name,email', 'events.user:id,name,email']));
    }

    public function confirmUsdt(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $request->validate([
            'agent_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!$moneyTransfer->usdt_proof_path) {
            return response()->json(['message' => 'A USDT transfer screenshot is required before confirmation.'], 422);
        }

        if ($moneyTransfer->isClosed()) {
            return response()->json(['message' => 'This transfer is already closed.'], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($moneyTransfer, $request, $oldStatus) {
            $moneyTransfer->update([
                'assigned_agent_id' => $moneyTransfer->assigned_agent_id ?: $request->user()->id,
                'usdt_confirmed_by' => $request->user()->id,
                'usdt_confirmed_at' => now(),
                'agent_notes' => $request->filled('agent_notes') ? $request->input('agent_notes') : $moneyTransfer->agent_notes,
                'status' => MoneyTransfer::STATUS_USDT_RECEIVED,
            ]);

            $this->recordEvent($moneyTransfer, 'usdt_confirmed', $oldStatus, MoneyTransfer::STATUS_USDT_RECEIVED, [
                'agent_notes' => $request->input('agent_notes'),
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['agent:id,name,email', 'events.user:id,name,email']));
    }

    public function uploadPayoutProof(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $request->validate([
            'payout_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'payout_reference' => ['nullable', 'string', 'max:255'],
            'payout_amount' => ['nullable', 'numeric', 'min:0'],
            'agent_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($moneyTransfer->status !== MoneyTransfer::STATUS_USDT_RECEIVED) {
            return response()->json(['message' => 'The USDT receipt must be confirmed before payout proof can be uploaded.'], 422);
        }

        $oldStatus = $moneyTransfer->status;
        $path = $request->file('payout_proof')->store('money-transfers/payout-proofs', 'public');

        DB::transaction(function () use ($moneyTransfer, $request, $path, $oldStatus) {
            if ($moneyTransfer->payout_proof_path) {
                Storage::disk('public')->delete($moneyTransfer->payout_proof_path);
            }

            $moneyTransfer->update([
                'assigned_agent_id' => $moneyTransfer->assigned_agent_id ?: $request->user()->id,
                'payout_reference' => $request->input('payout_reference', $moneyTransfer->payout_reference),
                'payout_amount' => $request->input('payout_amount', $moneyTransfer->payout_amount),
                'payout_proof_path' => $path,
                'payout_proof_uploaded_at' => now(),
                'payout_confirmed_by' => $request->user()->id,
                'payout_confirmed_at' => now(),
                'agent_notes' => $request->filled('agent_notes') ? $request->input('agent_notes') : $moneyTransfer->agent_notes,
                'status' => MoneyTransfer::STATUS_COMPLETED,
            ]);

            $this->recordEvent($moneyTransfer, 'payout_completed', $oldStatus, MoneyTransfer::STATUS_COMPLETED, [
                'proof_path' => $path,
                'payout_reference' => $request->input('payout_reference'),
                'payout_amount' => $request->input('payout_amount'),
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['agent:id,name,email', 'events.user:id,name,email']));
    }

    public function updateStatus(Request $request, MoneyTransfer $moneyTransfer): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                MoneyTransfer::STATUS_CANCELLED,
            ])],
            'agent_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($moneyTransfer->isClosed()) {
            return response()->json(['message' => 'This transfer is already closed.'], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($moneyTransfer, $request, $data, $oldStatus) {
            $moneyTransfer->update([
                'status' => $data['status'],
                'agent_notes' => $request->filled('agent_notes') ? $request->input('agent_notes') : $moneyTransfer->agent_notes,
            ]);

            $this->recordEvent($moneyTransfer, 'status_changed', $oldStatus, $data['status'], [
                'agent_notes' => $request->input('agent_notes'),
            ]);
        });

        return response()->json($moneyTransfer->fresh()->load(['events.user:id,name,email']));
    }

    private function recordEvent(MoneyTransfer $transfer, string $type, ?string $fromStatus, ?string $toStatus, array $payload = []): void
    {
        $transfer->events()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'payload' => array_filter($payload, fn ($value) => $value !== null),
        ]);
    }
}
