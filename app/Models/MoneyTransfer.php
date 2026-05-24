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
        'assigned_agent_id',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'recipient_location',
        'send_amount',
        'send_currency',
        'usdt_amount',
        'exchange_rate',
        'payout_currency',
        'payout_amount',
        'status',
        'usdt_proof_path',
        'usdt_proof_uploaded_at',
        'usdt_confirmed_by',
        'usdt_confirmed_at',
        'payout_reference',
        'payout_proof_path',
        'payout_proof_uploaded_at',
        'payout_confirmed_by',
        'payout_confirmed_at',
        'notes',
        'agent_notes',
    ];

    protected $casts = [
        'send_amount' => 'decimal:2',
        'usdt_amount' => 'decimal:6',
        'exchange_rate' => 'decimal:6',
        'payout_amount' => 'decimal:2',
        'usdt_proof_uploaded_at' => 'datetime',
        'usdt_confirmed_at' => 'datetime',
        'payout_proof_uploaded_at' => 'datetime',
        'payout_confirmed_at' => 'datetime',
    ];

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_USDT_PROOF_SUBMITTED = 'usdt_proof_submitted';
    public const STATUS_USDT_RECEIVED = 'usdt_received';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function usdtConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usdt_confirmed_by');
    }

    public function payoutConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payout_confirmed_by');
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

    protected static function booted(): void
    {
        static::creating(function (MoneyTransfer $transfer) {
            if (empty($transfer->reference)) {
                $transfer->reference = 'MT-' . now()->format('Ymd') . '-' . strtoupper(Str::substr(Str::uuid()->toString(), 0, 6));
            }
        });
    }
}
