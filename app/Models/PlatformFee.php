<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformFee extends Model
{
    protected $fillable = [
        'asset_id', 'fiat_currency_id',
        'buyer_fee_type', 'buyer_fee_value',
        'seller_fee_type', 'seller_fee_value',
        'min_fee', 'max_fee', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'buyer_fee_value' => 'decimal:8',
            'seller_fee_value' => 'decimal:8',
            'min_fee' => 'decimal:8',
            'max_fee' => 'decimal:8',
            'enabled' => 'boolean',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fiatCurrency()
    {
        return $this->belongsTo(Currency::class, 'fiat_currency_id');
    }
}
