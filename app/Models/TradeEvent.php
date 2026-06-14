<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeEvent extends Model
{
    protected $fillable = [
        'trade_id', 'actor_id', 'actor_type',
        'from_status', 'to_status', 'notes',
    ];

    public function trade()
    {
        return $this->belongsTo(Trade::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
