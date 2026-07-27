<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyTransferEvent extends Model
{
    protected $fillable = [
        'money_transfer_id',
        'user_id',
        'type',
        'from_status',
        'to_status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(MoneyTransfer::class, 'money_transfer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Format event for mobile client consumption.
     * Maps internal event types to actor_type, actor_name, and description.
     */
    public function toMobileArray(): array
    {
        $actorType = match ($this->type) {
            'initiated', 'requester_proof_uploaded', 'completed', 'cancelled' => 'requester',
            'accepted', 'executed', 'executor_proof_uploaded' => 'agent',
            'disputed', 'dispute_resolved', 'status_changed', 'assigned' => 'system',
            default => $this->user_id ? 'requester' : 'system',
        };

        $description = match ($this->type) {
            'initiated' => 'Remittance created',
            'accepted' => 'Order accepted by agent',
            'requester_proof_uploaded' => 'Proof of payment uploaded',
            'executed' => isset($this->payload['is_debt']) && $this->payload['is_debt']
                ? 'Payment executed (proof pending)'
                : 'Payment executed, proof uploaded',
            'executor_proof_uploaded' => 'Execution proof uploaded',
            'completed' => 'Remittance confirmed completed',
            'cancelled' => 'Remittance cancelled',
            'disputed' => 'Support ticket #' . ($this->payload['ticket_id'] ?? '') . ' opened',
            'dispute_resolved' => 'Dispute resolved',
            'status_changed' => 'Status changed from '
                . ($this->payload['from'] ?? '?') . ' to ' . ($this->payload['to'] ?? '?'),
            'assigned' => 'Ticket assigned',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };

        return [
            'id' => $this->id,
            'actor_type' => $actorType,
            'actor_name' => $this->user?->name ?? 'System',
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'description' => $description,
            'created_at' => $this->created_at,
        ];
    }
}
