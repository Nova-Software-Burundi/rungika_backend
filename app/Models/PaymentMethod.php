<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'code', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
