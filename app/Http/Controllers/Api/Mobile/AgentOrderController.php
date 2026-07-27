<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgentOrderController extends Controller
{
    private const TAB_STATUS_MAP = [
        'new'     => [MoneyTransfer::STATUS_PENDING],
        'active'  => [MoneyTransfer::STATUS_ACCEPTED],
        'history' => [MoneyTransfer::STATUS_EXECUTED, MoneyTransfer::STATUS_COMPLETED],
    ];

    public function __construct(protected NotificationService $notificationService) {}

    /**
     * List orders assigned to this agent.
     *
     * Client sends: ?tab=new|active|history|debts&page=1
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json(['message' => 'Only agents can access this endpoint.'], 403);
        }

        $query = MoneyTransfer::with([
            'initiator:id,name,phone',
            'agent:id,name,phone',
            'paymentMethod:id,name',
        ])
            ->where('assigned_agent_id', $user->id)
            ->orderByDesc('created_at');

        // Tab-based filtering (client sends "tab" not "status")
        if ($request->filled('tab')) {
            $tab = $request->input('tab');

            if (isset(self::TAB_STATUS_MAP[$tab])) {
                $query->whereIn('status', self::TAB_STATUS_MAP[$tab]);
            } elseif ($tab === 'debts') {
                $query->where('executor_debt', true);
            }
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginated = $query->paginate((int) $request->get('per_page', 20));

        // Transform items to match client field expectations
        $paginated->setCollection(
            $paginated->getCollection()->map(fn ($t) => $this->toMobileArray($t))
        );

        return response()->json($paginated);
    }

    /**
     * Show order detail (agent's own orders only).
     */
    public function show(MoneyTransfer $moneyTransfer, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($moneyTransfer->assigned_agent_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $moneyTransfer->load([
            'initiator:id,name,phone',
            'agent:id,name,phone',
            'paymentMethod:id,name',
            'events.user:id,name',
        ]);

        $data = $this->toMobileArray($moneyTransfer);
        $data['events'] = $moneyTransfer->events->sortBy('id')->map->toMobileArray()->values();

        return response()->json($data);
    }

    /**
     * Accept an order (pending → accepted).
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

        // Notify requester
        if ($moneyTransfer->initiated_by) {
            $requester = \App\Models\User::find($moneyTransfer->initiated_by);
            if ($requester) {
                $this->notificationService->sendWithData(
                    $requester,
                    'Order Accepted',
                    "Agent {$user->name} accepted your remittance of {$moneyTransfer->send_amount} {$moneyTransfer->send_currency}",
                    [
                        'remittance_id' => (string) $moneyTransfer->id,
                        'navigate_to_tab' => 'remittance',
                    ]
                );
            }
        }

        return response()->json(['message' => 'Order accepted successfully']);
    }

    /**
     * Execute an order (accepted → executed).
     * Accepts both application/json and multipart/form-data.
     *
     * Client sends field "notes" (not "agent_notes").
     * At least one of notes / payout_reference / proof must be provided.
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
            'proof'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'payout_reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Client-side validates that at least one is non-empty, but enforce server-side too
        $hasNotes   = $request->filled('notes');
        $hasRef     = $request->filled('payout_reference');
        $hasProof   = $request->hasFile('proof');

        if (!$hasNotes && !$hasRef && !$hasProof) {
            return response()->json([
                'message' => 'At least one of notes, payout_reference, or proof is required.',
            ], 422);
        }

        $oldStatus = $moneyTransfer->status;

        DB::transaction(function () use ($request, $moneyTransfer, $oldStatus, $user) {
            $updateData = [
                'status'           => MoneyTransfer::STATUS_EXECUTED,
                'executed_at'      => now(),
                'agent_notes'      => $request->input('notes', $moneyTransfer->agent_notes),
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
                    'is_debt'   => !$request->hasFile('proof'),
                    'notes'     => $request->input('notes'),
                ],
            ]);
        });

        // Notify requester
        if ($moneyTransfer->initiated_by) {
            $requester = \App\Models\User::find($moneyTransfer->initiated_by);
            if ($requester) {
                $isDebt = !$request->hasFile('proof');
                $this->notificationService->sendWithData(
                    $requester,
                    'Order Executed',
                    $isDebt
                        ? "Agent {$user->name} executed your remittance (proof pending)"
                        : "Agent {$user->name} executed your remittance with proof",
                    [
                        'remittance_id' => (string) $moneyTransfer->id,
                        'navigate_to_tab' => 'remittance',
                    ]
                );
            }
        }

        return response()->json(['message' => 'Order executed successfully']);
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

    /**
     * Transform a MoneyTransfer to the format the mobile client expects.
     *
     * Maps internal field names to client field names:
     *   initiator          → requester
     *   executor_proof_path → agent_proof_path
     *   executor_debt      → agent_debt
     *   paymentMethod.name → destinator_payment_method_name
     */
    private function toMobileArray(MoneyTransfer $t): array
    {
        $data = $t->toArray();

        // Alias: requester (client doesn't know "initiator")
        if (isset($data['initiator'])) {
            $data['requester'] = $data['initiator'];
            unset($data['initiator']);
        }

        // Flat payment method name
        $data['destinator_payment_method_name'] = $t->paymentMethod?->name;

        // Alias: agent_proof_path (client doesn't know "executor_proof_path")
        $data['agent_proof_path'] = $data['executor_proof_path'] ?? null;

        // Alias: agent_debt (client doesn't know "executor_debt")
        $data['agent_debt'] = $data['executor_debt'] ?? false;

        return $data;
    }
}
