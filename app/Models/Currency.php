<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'symbol', 'is_default', 'enabled'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function exchangeRatesAsBase()
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency_id');
    }

    public function exchangeRatesAsTarget()
    {
        return $this->hasMany(ExchangeRate::class, 'target_currency_id');
    }
}
