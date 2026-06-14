<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferencePrice extends Model
{
    protected $fillable = [
        'asset_id', 'fiat_currency_id', 'price', 'source', 'valid_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'valid_at' => 'datetime',
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
