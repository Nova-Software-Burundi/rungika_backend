<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'reference',
        'ad_id', 'asset_id', 'fiat_currency_id',
        'buyer_id', 'seller_id',
        'status',
        'asset_amount', 'fiat_amount', 'price',
        'payment_method_id', 'payment_details',
        'proof_path', 'proof_uploaded_at',
        'seller_confirmed_at', 'completed_at',
        'cancelled_at', 'cancelled_by',
        'dispute_reason', 'dispute_opened_at',
        'dispute_resolved_at', 'dispute_resolved_by', 'dispute_resolution',
        'fee_buyer', 'fee_seller',
    ];

    protected function casts(): array
    {
        return [
            'asset_amount' => 'decimal:8',
            'fiat_amount' => 'decimal:8',
            'price' => 'decimal:8',
            'fee_buyer' => 'decimal:8',
            'fee_seller' => 'decimal:8',
            'proof_uploaded_at' => 'datetime',
            'seller_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'dispute_opened_at' => 'datetime',
            'dispute_resolved_at' => 'datetime',
        ];
    }

    public function ad()
    {
        return $this->belongsTo(Advertisement::class, 'ad_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fiatCurrency()
    {
        return $this->belongsTo(Currency::class, 'fiat_currency_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function disputeResolvedBy()
    {
        return $this->belongsTo(User::class, 'dispute_resolved_by');
    }

    public function events()
    {
        return $this->hasMany(TradeEvent::class);
    }
}
