<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MoneyTransfer extends Model
{
    protected $fillable = [
        'reference',
        'initiated_by',
        'sender_user_id',
        'recipient_user_id',
        'assigned_agent_id',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'recipient_location',
        'destinator_name',
        'destinator_phone',
        'destinator_address',
        'destinator_payment_method_id',
        'destinator_account_number',
        'destinator_notes',
        'send_amount',
        'send_currency',
        'usdt_amount',
        'exchange_rate',
        'payout_currency',
        'payout_amount',
        'status',
        'requester_proof_path',
        'requester_debt',
        'requester_proof_uploaded_at',
        'executor_proof_path',
        'executor_debt',
        'executor_proof_uploaded_at',
        'payout_reference',
        'payout_proof_path',
        'payout_proof_uploaded_at',
        'payout_confirmed_by',
        'payout_confirmed_at',
        'accepted_at',
        'executed_at',
        'completed_at',
        'notes',
        'agent_notes',
    ];

    protected $casts = [
        'send_amount' => 'decimal:2',
        'usdt_amount' => 'decimal:6',
        'exchange_rate' => 'decimal:6',
        'payout_amount' => 'decimal:2',
        'requester_debt' => 'boolean',
        'executor_debt' => 'boolean',
        'requester_proof_uploaded_at' => 'datetime',
        'executor_proof_uploaded_at' => 'datetime',
        'payout_proof_uploaded_at' => 'datetime',
        'payout_confirmed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'executed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // New remittance statuses
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_EXECUTED  = 'executed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISPUTED  = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';

    // Legacy USDT statuses (kept for backward compat)
    public const STATUS_INITIATED           = 'initiated';
    public const STATUS_USDT_PROOF_SUBMITTED = 'usdt_proof_submitted';
    public const STATUS_USDT_RECEIVED       = 'usdt_received';

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'destinator_payment_method_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MoneyTransferEvent::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function hasDebt(): bool
    {
        return $this->requester_debt || $this->executor_debt;
    }

    protected static function booted(): void
    {
        static::creating(function (MoneyTransfer $transfer) {
            if (empty($transfer->reference)) {
                $transfer->reference = 'MT-' . now()->format('Ymd') . '-' . strtoupper(Str::substr(Str::uuid()->toString(), 0, 6));
            }
        });
    }
}
