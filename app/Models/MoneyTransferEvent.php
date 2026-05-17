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
}
