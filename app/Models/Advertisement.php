<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    protected $fillable = [
        'user_id', 'type', 'asset_id', 'fiat_currency_id',
        'price_type', 'price', 'margin',
        'min_order', 'max_order', 'available_quantity',
        'payment_methods', 'terms', 'status', 'auto_reply',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'margin' => 'decimal:2',
            'min_order' => 'decimal:8',
            'max_order' => 'decimal:8',
            'available_quantity' => 'decimal:8',
            'payment_methods' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
