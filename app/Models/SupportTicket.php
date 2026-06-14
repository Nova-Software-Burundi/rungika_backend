<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo,
    HasMany,
    MorphTo
};

class SupportTicket extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'support_category_id',
        'assigned_to',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'priority',
        'status',
        'first_response_at',
        'due_at',
        'resolved_at',
        'closed_at',
        'sla_first_response_due_at',
        'sla_first_response_at',
        'sla_resolution_due_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'due_at'           => 'datetime',
        'resolved_at'      => 'datetime',
        'closed_at'        => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Constants
     |------------------------------------------------------------------*/

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING     = 'waiting';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_CLOSED      = 'closed';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /* -----------------------------------------------------------------
     |  Relationships
     |------------------------------------------------------------------*/

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'support_category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /* -----------------------------------------------------------------
     |  Helpers (read-only for now)
     |------------------------------------------------------------------*/

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING,
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ]);
    }

    protected static function booted()
    {
        static::creating(function (SupportTicket $ticket) {
            $year = now()->year;

            $latest = self::whereYear('created_at', $year)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextNumber = $latest
                ? ((int) substr($latest->reference, -6)) + 1
                : 1;

            $ticket->reference = sprintf(
                'SUP-%d-%06d',
                $year,
                $nextNumber
            );

            //SLA
            $sla = config('support.sla.' . $ticket->priority);

            if ($sla) {
                $now = now();

                $ticket->sla_first_response_due_at =
                    $now->copy()->addMinutes($sla['first_response_minutes']);

                $ticket->sla_resolution_due_at =
                    $now->copy()->addMinutes($sla['resolution_minutes']);
            }
        });
    }

    public function isFirstResponseBreached(): bool
    {
        return is_null($this->sla_first_response_at)
            && now()->greaterThan($this->sla_first_response_due_at);
    }

    public function isResolutionBreached(): bool
    {
        return !in_array($this->status, ['resolved', 'closed'])
            && now()->greaterThan($this->sla_resolution_due_at);
    }

}
