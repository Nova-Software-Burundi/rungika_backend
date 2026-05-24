<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'base_currency_id',
        'target_currency_id',
        'rate',
        'valid_from',
        'valid_to',
        'created_by',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to'   => 'datetime',
    ];

    /**
     * Scope to get rate valid at given datetime
     */
    public function scopeValidAt($query, string $base, string $target, \DateTimeInterface $date)
    {
        return $query->where('base_currency', strtoupper($base))
            ->where('target_currency', strtoupper($target))
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from');
    }

    protected static function booted()
    {
        static::creating(function (ExchangeRate $rate) {
            // Find the latest existing rate for the same currency pair
            $previous = ExchangeRate::where('base_currency_id', $rate->base_currency_id)
                ->where('target_currency_id', $rate->target_currency_id)
                ->whereNull('valid_to')
                ->latest('valid_from')
                ->first();

            if ($previous) {
                // Close the old rate validity to the minute before new rate starts
                $previous->update([
                    'valid_to' => $rate->valid_from->copy()->subMinute(),
                ]);
            }
        });
    }

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function targetCurrency()
    {
        return $this->belongsTo(Currency::class, 'target_currency_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
